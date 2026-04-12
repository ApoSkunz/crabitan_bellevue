<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Controller;
use Core\CookieHelper;
use Core\Response;
use Middleware\AuthMiddleware;
use Model\AccountModel;
use Model\FavoriteModel;
use Model\OrderModel;
use Service\AccountService;
use Service\MailService;

/**
 * Gère le profil, le tableau de bord et les préférences de langue du compte client.
 */
class AccountProfileController extends Controller
{
    private const CONTENT_TYPE_JSON = 'Content-Type: application/json';

    private AccountModel $accounts;
    private FavoriteModel $favorites;
    private OrderModel $orders;

    /**
     * Initialise le contrôleur et ses dépendances.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts  = new AccountModel();
        $this->favorites = new FavoriteModel();
        $this->orders    = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte
    // ----------------------------------------------------------------

    /**
     * Affiche le tableau de bord du compte utilisateur.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function index(array $params): void
    {
        $payload   = $this->requireCustomer();
        $userId    = (int) $payload['sub'];
        $lang      = $params['lang'];

        $account   = $this->accounts->findById($userId);
        $isCompany = $account && $account['account_type'] === 'company';

        $info = $_SESSION['flash']['info'] ?? null;
        unset($_SESSION['flash']['info']);

        $this->view('account/index', [
            'lang'          => $lang,
            'account'       => $account,
            'isCompany'     => $isCompany,
            'info'          => $info,
            'orderCount'    => $isCompany ? 0 : $this->orders->countForUser($userId),
            'addressCount'  => $isCompany ? 0 : count((new \Model\AddressModel())->getByUser($userId)),
            'favoriteCount' => $this->favorites->countForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/profil
    // ----------------------------------------------------------------

    /**
     * Affiche la page de profil du compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function profile(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success          = $_SESSION['flash']['profile_success'] ?? null;
        $errors           = $_SESSION['flash']['profile_errors']  ?? [];
        $emailChangeSent  = $_SESSION['flash']['email_change_sent'] ?? false;
        unset(
            $_SESSION['flash']['profile_success'],
            $_SESSION['flash']['profile_errors'],
            $_SESSION['flash']['email_change_sent']
        );

        $account = $this->accounts->findById($userId);

        $this->view('account/profile', [
            'lang'            => $lang,
            'account'         => $account,
            'maskedEmail'     => self::maskEmail((string) ($account['email'] ?? '')),
            'errors'          => $errors,
            'success'         => $success,
            'emailChangeSent' => $emailChangeSent,
            'csrf'            => $_SESSION['csrf'] ?? '',
            'ownerEmail'      => $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/profil
    // ----------------------------------------------------------------

    /**
     * Traite la mise à jour du profil (particulier ou société) et la préférence newsletter.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function updateProfile(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/profil";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['profile_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect($back);
        }

        $account     = $this->accounts->findById($userId);
        $newsletter  = $this->request->post('newsletter', '0') === '1';
        $errors      = [];

        if ($account && $account['account_type'] === 'individual') {
            $civility  = $this->request->post('civility', '');
            $firstname = trim($this->request->post('firstname', ''));
            $lastname  = trim($this->request->post('lastname', ''));
            $errors    = $this->validateAndSaveIndividual($userId, $civility, $firstname, $lastname);
        } elseif ($account && $account['account_type'] === 'company') {
            $companyName = trim($this->request->post('company_name', ''));
            $siret       = trim($this->request->post('siret', '')) ?: null;
            $errors      = $this->validateAndSaveCompany($userId, $companyName, $siret);
        }

        if ($errors !== []) {
            $_SESSION['flash']['profile_errors'] = $errors;
            Response::redirect($back);
        }

        $currentNewsletter = (bool) ($account['newsletter'] ?? false);

        if ($newsletter && !$currentNewsletter) {
            // Toggle 0→1 : double opt-in — envoie un email de confirmation
            $token = bin2hex(random_bytes(32));
            $this->accounts->storeNewsletterConfirmToken($userId, $token);
            $confirmUrl = APP_URL . "/{$lang}/newsletter/confirmation?token=" . urlencode($token);
            (new MailService())->sendNewsletterConfirmation((string) $account['email'], $confirmUrl, $lang);
            $_SESSION['flash']['profile_success'] = __('newsletter.confirm_sent');
            Response::redirect($back);
        } elseif (!$newsletter && $currentNewsletter) {
            // Toggle 1→0 : désabonnement immédiat (pas de confirmation requise)
            $this->accounts->updateNewsletter($userId, false);
        }

        $_SESSION['flash']['profile_success'] = __('account.profile_updated');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/profil/changer-email
    // ----------------------------------------------------------------

    /**
     * Initie une demande de changement d'email (double opt-in).
     *
     * @param array<string, string> $params Paramètres de route (contient 'lang')
     * @return void
     */
    public function requestEmailChange(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/profil#email-change";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['profile_errors'] = ['email' => __('error.csrf')];
            Response::redirect($back);
        }

        $newEmail        = trim($this->request->post('new_email', ''));
        $currentPassword = $this->request->post('current_password', '');

        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash']['profile_errors'] = ['email' => __('account.email_change_invalid')];
            Response::redirect($back);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        try {
            $service = new AccountService($this->accounts, new MailService());
            $service->requestEmailChange($userId, $newEmail, $currentPassword, $ip);
        } catch (\Core\Exception\HttpException $e) {
            $_SESSION['flash']['profile_errors'] = ['email' => $e->getMessage()];
            Response::redirect($back);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
            $_SESSION['flash']['profile_errors'] = ['email' => __('error.generic')];
            Response::redirect($back);
        }

        $_SESSION['flash']['profile_success']      = __('account.email_change_sent');
        $_SESSION['flash']['email_change_sent']     = true;
        Response::redirect("/{$lang}/mon-compte/profil#email-change");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/email/confirmer?token=xxx
    // ----------------------------------------------------------------

    /**
     * Confirme le changement d'email après clic sur le lien de confirmation.
     *
     * @param array<string, string> $params Paramètres de route (contient 'lang')
     * @return void
     */
    public function confirmEmailChange(array $params): void
    {
        $lang     = $params['lang'];
        $rawToken = $this->request->get('token', '');

        if ($rawToken === '') {
            $this->view('account/email_change_confirm', [
                'lang'    => $lang,
                'success' => false,
                'error'   => __('account.email_change_token_invalid'),
            ]);
            return;
        }

        $service = new AccountService($this->accounts, new MailService());

        try {
            $service->confirmEmailChange($rawToken);
        } catch (\Core\Exception\HttpException $e) {
            $this->view('account/email_change_confirm', [
                'lang'    => $lang,
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
            return;
        }

        CookieHelper::clear();

        $this->view('account/email_change_confirm', [
            'lang'    => $lang,
            'success' => true,
            'error'   => null,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/email/revoquer?token=xxx
    // ----------------------------------------------------------------

    /**
     * Révoque une demande de changement d'email directement depuis le lien email.
     *
     * @param array<string, string> $params Paramètres de route (contient 'lang')
     * @return void
     */
    public function revokeEmailChange(array $params): void
    {
        $lang     = $params['lang'];
        $rawToken = $this->request->get('token', '');

        if ($rawToken === '') {
            $this->view('account/email_change_confirm', [
                'lang'    => $lang,
                'success' => false,
                'error'   => __('account.email_change_token_invalid'),
            ]);
            return;
        }

        $hashedToken = hash('sha256', $rawToken);
        $account     = $this->accounts->findByEmailChangeToken($hashedToken);

        if (!$account) {
            $this->view('account/email_change_confirm', [
                'lang'    => $lang,
                'success' => false,
                'error'   => __('account.email_change_token_invalid'),
            ]);
            return;
        }

        $this->accounts->clearEmailChangeToken((int) $account['id']);

        $this->view('account/email_change_confirm', [
            'lang'    => $lang,
            'success' => true,
            'revoked' => true,
            'error'   => null,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/email/annuler
    // ----------------------------------------------------------------

    /**
     * Annule la demande de changement d'email en cours.
     *
     * @param array<string, string> $params Paramètres de route (contient 'lang')
     * @return void
     */
    public function cancelEmailChange(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/profil";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['profile_errors'] = ['email' => __('error.csrf')];
            Response::redirect($back);
        }

        $this->accounts->clearEmailChangeToken($userId);

        $_SESSION['flash']['profile_success'] = __('account.email_change_cancelled');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/preference-langue — AJAX
    // ----------------------------------------------------------------

    /**
     * Met à jour la langue préférée du compte.
     *
     * Deux modes :
     * - Formulaire header (redirect_to fourni) → redirect après mise à jour.
     * - AJAX profil (pas de redirect_to) → JSON.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function updateLangPreference(array $params): void // NOSONAR php:S1172 — $params requis par le routeur
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];

        if (!$this->verifyCsrf()) {
            header(self::CONTENT_TYPE_JSON);
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'csrf']);
            return;
        }

        $newLang = $this->request->post('lang', '');
        if (!in_array($newLang, ['fr', 'en'], true)) {
            header(self::CONTENT_TYPE_JSON);
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'invalid_lang']);
            return;
        }

        $this->accounts->updateLang($userId, $newLang);

        $redirectTo = $this->request->post('redirect_to', '');
        if ($redirectTo !== '' && preg_match('#^/(fr|en)(/|$)#', $redirectTo)) {
            Response::redirect($redirectTo);
        }

        header(self::CONTENT_TYPE_JSON);
        echo json_encode(['success' => true]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Masque partiellement une adresse email pour l'affichage (protection vie privée).
     *
     * @param string $email Adresse email à masquer
     * @return string Email masqué
     */
    public static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) {
            return str_repeat('*', strlen($email));
        }
        return $email[0] . '***' . substr($email, $at);
    }

    /**
     * Valide et sauvegarde le profil d'un compte particulier.
     *
     * @param int    $userId    Identifiant utilisateur
     * @param string $civility  Civilité
     * @param string $firstname Prénom
     * @param string $lastname  Nom
     * @return array<string, string>
     */
    private function validateAndSaveIndividual(
        int $userId,
        string $civility,
        string $firstname,
        string $lastname
    ): array {
        $errors = [];
        if ($firstname === '') {
            $errors['firstname'] = __('validation.required');
        }
        if ($lastname === '') {
            $errors['lastname'] = __('validation.required');
        }
        if ($errors === []) {
            $this->accounts->updateIndividualProfile($userId, $civility, $firstname, $lastname);
        }
        return $errors;
    }

    /**
     * Valide et sauvegarde le profil d'un compte société.
     *
     * @param int         $userId      Identifiant utilisateur
     * @param string      $companyName Raison sociale
     * @param string|null $siret       Numéro SIRET (optionnel)
     * @return array<string, string>
     */
    private function validateAndSaveCompany(int $userId, string $companyName, ?string $siret): array
    {
        $errors = [];
        if ($companyName === '') {
            $errors['company_name'] = __('validation.required');
        }
        if ($errors === []) {
            $this->accounts->updateCompanyProfile($userId, $companyName, $siret);
        }
        return $errors;
    }

    /**
     * Vérifie le token CSRF de la requête.
     *
     * @return bool
     */
    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Vérifie que la requête provient d'un customer authentifié.
     *
     * @return array<string, mixed>
     */
    private function requireCustomer(): array
    {
        $payload = AuthMiddleware::handle(redirectToLogin: true);
        if (($payload['role'] ?? '') !== 'customer') {
            Response::abort(404);
        }
        return $payload;
    }
}
