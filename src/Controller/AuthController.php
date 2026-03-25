<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Jwt;
use Core\Response;
use Middleware\GuestMiddleware;
use Model\AccountModel;
use Model\PasswordResetModel;
use Model\ConnectionModel;
use Service\MailService;

class AuthController extends Controller
{
    private AccountModel $accounts;
    private PasswordResetModel $resets;
    private ConnectionModel $connections;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts    = new AccountModel();
        $this->resets      = new PasswordResetModel();
        $this->connections = new ConnectionModel();
    }

    // ----------------------------------------------------------------
    // POST /{lang}/connexion
    // ----------------------------------------------------------------

    public function login(array $params): void
    {
        GuestMiddleware::handle();
        $lang = $params['lang'];

        $rawBack   = $this->request->post('redirect_back', '');
        $validBack = preg_match('#^/[^/]#', $rawBack) && !str_contains($rawBack, '://');
        $safeBack  = $validBack ? $rawBack : "/{$lang}";

        if (!$this->verifyCsrf()) {
            $this->flash('modal_error', __('error.csrf'));
            Response::redirect($safeBack);
        }

        $email    = strtolower(trim($this->request->post('email', '')));
        $password = $this->request->post('password', '');
        $account  = $this->accounts->findByEmail($email);

        if (!$account || $account['password'] === null || !password_verify($password, $account['password'])) {
            $this->flash('modal_error', __('auth.invalid_credentials'));
            Response::redirect($safeBack);
        }

        if (!$account['email_verified_at']) {
            $this->flash('modal_error', __('auth.account_inactive'));
            Response::redirect($safeBack);
        }

        $expiry = (int) ($_ENV['JWT_EXPIRY'] ?? 3600);
        $token  = Jwt::generate((int) $account['id'], $account['role']);

        setcookie('auth_token', $token, [
            'expires'  => time() + $expiry,
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($account['lang'] !== $lang) {
            $this->accounts->updateLang((int) $account['id'], $lang);
        }

        $deviceToken = $_COOKIE['device_token'] ?? null;
        if ($deviceToken === null) {
            $deviceToken = bin2hex(random_bytes(32));
            setcookie('device_token', $deviceToken, [
                'expires'  => time() + (90 * 24 * 3600),
                'path'     => '/',
                'secure'   => APP_ENV === 'production',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->connections->create(
            (int) $account['id'],
            $token,
            $deviceToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            $this->deriveDeviceName($ua),
            'password',
            $expiry
        );

        Response::redirect($safeBack);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/deconnexion
    // ----------------------------------------------------------------

    public function logout(array $params): void
    {
        $token = $_COOKIE['auth_token'] ?? null;

        if ($token) {
            $this->connections->revoke($token);
            setcookie('auth_token', '', time() - 1, '/', '', APP_ENV === 'production', true);
        }

        Response::redirect('/' . $params['lang']);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/inscription
    // ----------------------------------------------------------------

    public function register(array $params): void
    {
        GuestMiddleware::handle();
        $lang = $params['lang'];

        if (!$this->verifyCsrf()) {
            $this->flash('modal_error', __('error.csrf'));
            Response::redirect("/{$lang}");
        }

        $accountType = $this->request->post('account_type', '');
        $email       = strtolower(trim($this->request->post('email', '')));
        $password    = $this->request->post('password', '');
        $confirm     = $this->request->post('password_confirm', '');
        $civility    = $this->request->post('civility', '');
        $lastname    = trim($this->request->post('lastname', ''));
        $firstname   = trim($this->request->post('firstname', ''));
        $company     = trim($this->request->post('company_name', ''));
        $newsletter  = $this->request->post('newsletter', '0') === '1' ? 1 : 0;

        $errors = $this->validateRegister(
            $accountType,
            $email,
            $password,
            $confirm,
            $civility,
            $lastname,
            $firstname,
            $company
        );
        $old = compact('accountType', 'civility', 'lastname', 'firstname', 'email', 'company', 'newsletter');

        if ($errors) {
            $_SESSION['flash']['register_errors'] = $errors;
            $_SESSION['flash']['register_old']    = $old;
            Response::redirect("/{$lang}");
        }

        if ($this->accounts->findByEmail($email)) {
            $_SESSION['flash']['register_errors'] = ['email' => __('auth.email_taken')];
            $_SESSION['flash']['register_old']    = $old;
            Response::redirect("/{$lang}");
        }

        $verificationToken = bin2hex(random_bytes(32));
        $displayName       = $accountType === 'company' ? $company : "{$firstname} {$lastname}";

        $this->accounts->create(
            $accountType,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            $lang,
            $newsletter,
            $verificationToken,
            $civility,
            $lastname,
            $firstname,
            $company
        );

        $verifyUrl = APP_URL . "/{$lang}/verification/{$verificationToken}";

        try {
            $mail = new MailService();
            $mail->sendEmailVerification($email, $displayName, $verifyUrl, $lang);
        } catch (\Throwable $e) {
            error_log('Mail verification error: ' . $e->getMessage());
        }

        $this->flash('info', __('auth.register_success'));
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/verification/{token}
    // ----------------------------------------------------------------

    public function verifyEmail(array $params): void
    {
        $lang    = $params['lang'];
        $token   = $params['token'] ?? '';
        $account = $this->accounts->findByVerificationToken($token);

        if (!$account) {
            $this->view('auth/verify', [
                'lang'    => $lang,
                'success' => false,
                'message' => __('auth.verify_invalid'),
            ]);
            return;
        }

        if ($account['email_verified_at']) {
            $this->flash('info', __('auth.already_verified'));
            Response::redirect("/{$lang}");
        }

        $this->accounts->verifyEmail((int) $account['id']);

        $this->view('auth/verify', [
            'lang'    => $lang,
            'success' => true,
            'message' => __('auth.verify_success'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mot-de-passe-oublie
    // ----------------------------------------------------------------

    public function forgotForm(array $params): void
    {
        GuestMiddleware::handle();
        Response::redirect('/' . $params['lang']);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mot-de-passe-oublie
    // ----------------------------------------------------------------

    public function forgot(array $params): void
    {
        GuestMiddleware::handle();
        $lang  = $params['lang'];

        if (!$this->verifyCsrf()) {
            Response::redirect("/{$lang}/mot-de-passe-oublie");
        }

        $email   = strtolower(trim($this->request->post('email', '')));
        $account = $this->accounts->findByEmail($email);

        // Toujours afficher le succès (anti-énumération)
        $this->flash('info', __('auth.reset_email_sent'));

        if ($account && $account['email_verified_at']) {
            $token = bin2hex(random_bytes(32));
            $this->resets->create((int) $account['id'], $token);
            $resetUrl = APP_URL . "/{$lang}/reinitialisation/{$token}";

            try {
                $displayName = $account['account_type'] === 'company'
                    ? ($account['company_name'] ?? 'Client')
                    : (($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
                $mail = new MailService();
                $mail->sendPasswordReset(
                    $account['email'],
                    $displayName,
                    $resetUrl,
                    $lang
                );
            } catch (\Throwable $e) {
                error_log('Mail reset error: ' . $e->getMessage());
            }
        }

        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/reinitialisation/{token}
    // ----------------------------------------------------------------

    public function resetForm(array $params): void
    {
        $lang  = $params['lang'];
        $token = $params['token'] ?? '';
        $reset = $this->resets->findByToken($token);

        $_SESSION['reset_modal'] = [
            'token' => $token,
            'valid' => (bool) $reset,
            'error' => null,
        ];

        Response::redirect("/{$lang}?modal=reset");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/reinitialisation/{token}
    // ----------------------------------------------------------------

    public function reset(array $params): void
    {
        $lang  = $params['lang'];
        $token = $params['token'] ?? '';

        if (!$this->verifyCsrf()) {
            Response::redirect("/{$lang}/reinitialisation/{$token}");
        }

        $reset = $this->resets->findByToken($token);

        if (!$reset) {
            $_SESSION['reset_modal'] = ['token' => $token, 'valid' => false, 'error' => null];
            Response::redirect("/{$lang}?modal=reset");
        }

        $password = $this->request->post('password', '');
        $confirm  = $this->request->post('password_confirm', '');

        if (strlen($password) < 12) {
            $_SESSION['reset_modal'] = ['token' => $token, 'valid' => true, 'error' => __('validation.password_min')];
            Response::redirect("/{$lang}?modal=reset");
        }

        if ($password !== $confirm) {
            $_SESSION['reset_modal'] = ['token' => $token, 'valid' => true, 'error' => __('validation.password_match')];
            Response::redirect("/{$lang}?modal=reset");
        }

        $this->accounts->updatePassword((int) $reset['user_id'], password_hash($password, PASSWORD_BCRYPT));
        $this->resets->deleteByUserId((int) $reset['user_id']);

        unset($_SESSION['reset_modal']);
        $this->flash('info', __('auth.password_updated'));
        Response::redirect("/{$lang}?login=1");
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    private function validateRegister(
        string $accountType,
        string $email,
        string $password,
        string $confirm,
        string $civility,
        string $lastname,
        string $firstname,
        string $company
    ): array {
        $errors = [];

        if (!in_array($accountType, ['individual', 'company'], true)) {
            $errors['account_type'] = __('validation.required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email');
        }
        if (strlen($password) < 12) {
            $errors['password'] = __('validation.password_min');
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = __('validation.password_match');
        }
        if ($accountType === 'individual') {
            if (strlen($lastname) < 2) {
                $errors['lastname'] = __('validation.required');
            }
            if (strlen($firstname) < 2) {
                $errors['firstname'] = __('validation.required');
            }
            if (!in_array($civility, ['M', 'F', 'other'], true)) {
                $errors['civility'] = __('validation.required');
            }
        }
        if ($accountType === 'company' && strlen($company) < 2) {
            $errors['company_name'] = __('validation.required');
        }

        return $errors;
    }

    private function deriveDeviceName(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg')                                        => 'Edge',
            str_contains($ua, 'Chrome') && !str_contains($ua, 'Chromium')  => 'Chrome',
            str_contains($ua, 'Firefox')                                    => 'Firefox',
            str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')    => 'Safari',
            default                                                         => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android')                              => 'Android',
            str_contains($ua, 'Windows')                              => 'Windows',
            str_contains($ua, 'Macintosh')                            => 'macOS',
            str_contains($ua, 'Linux')                                => 'Linux',
            default                                                   => 'Unknown',
        };
        return "{$browser} · {$os}";
    }

    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }
}
