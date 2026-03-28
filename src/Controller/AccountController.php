<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\CookieHelper;
use Core\Response;
use Middleware\AuthMiddleware;
use Model\AccountModel;
use Model\AddressModel;
use Model\ConnectionModel;
use Model\FavoriteModel;
use Model\TrustedDeviceModel;
use Model\DeviceConfirmTokenModel;
use Model\OrderModel;

class AccountController extends Controller
{
    private const PER_PAGE          = 10;
    private const VALID_PER_PAGES   = [10, 25, 50];

    private AccountModel $accounts;
    private AddressModel $addresses;
    private FavoriteModel $favorites;
    private OrderModel $orders;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private DeviceConfirmTokenModel $deviceConfirmTokens;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts            = new AccountModel();
        $this->addresses           = new AddressModel();
        $this->favorites           = new FavoriteModel();
        $this->orders              = new OrderModel();
        $this->connections         = new ConnectionModel();
        $this->trustedDevices      = new TrustedDeviceModel();
        $this->deviceConfirmTokens = new DeviceConfirmTokenModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte
    // ----------------------------------------------------------------

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
            'addressCount'  => $isCompany ? 0 : count($this->addresses->getByUser($userId)),
            'favoriteCount' => $this->favorites->countForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes
    // ----------------------------------------------------------------

    public function orders(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->requireIndividual($userId, $lang);

        $perPage = (int) $this->request->get('per_page', (string) self::PER_PAGE);
        if (!in_array($perPage, self::VALID_PER_PAGES, true)) {
            $perPage = self::PER_PAGE;
        }

        $period = $this->request->get('period', 'all');
        $year   = null;
        if ($period !== 'all' && $period !== '3months') {
            $year   = (int) $period;
            $period = 'year';
        }

        $rawStatus    = $this->request->get('status', '');
        $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'return_requested'];
        $statusFilter  = in_array($rawStatus, $validStatuses, true) ? $rawStatus : null;

        $total = $this->orders->countForUser($userId, $period === 'all' ? null : $period, $year, $statusFilter);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, (int) $this->request->get('page', '1')), $pages);

        $this->view('account/orders', [
            'lang'         => $lang,
            'orders'       => $this->orders->getForUser($userId, $page, $perPage, $period === 'all' ? null : $period, $year, $statusFilter),
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
            'perPage'      => $perPage,
            'period'       => $this->request->get('period', 'all'),
            'statusFilter' => $rawStatus,
            'years'        => $this->orders->getAvailableYearsForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes/{id}
    // ----------------------------------------------------------------

    public function orderDetail(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        $this->requireIndividual($userId, $lang);

        $order = $this->orders->findDetailForUser($id, $userId);
        if (!$order) {
            Response::redirect("/{$lang}/mon-compte/commandes");
        }

        $items            = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $shippingDiscount = (float) ($order['shipping_discount'] ?? 0);

        $success = $_SESSION['flash']['order_success'] ?? null;
        $error   = $_SESSION['flash']['order_error']   ?? null;
        unset($_SESSION['flash']['order_success'], $_SESSION['flash']['order_error']);

        $this->view('account/order_detail', [
            'lang'             => $lang,
            'order'            => $order,
            'items'            => $items,
            'shippingDiscount' => $shippingDiscount > 0.0 ? $shippingDiscount : null,
            'success'          => $success,
            'error'            => $error,
            'csrf'             => $_SESSION['csrf'] ?? '',
            'ownerEmail'       => $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/commandes/{id}/annuler
    // ----------------------------------------------------------------

    public function cancelOrder(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/commandes/{$id}";

        $this->requireIndividual($userId, $lang);

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['order_error'] = __('error.csrf');
            Response::redirect($back);
        }

        $ok = $this->orders->cancelForUser($id, $userId);
        if ($ok) {
            $_SESSION['flash']['order_success'] = __('account.order_cancelled');
        } else {
            $_SESSION['flash']['order_error'] = __('account.order_cancel_failed');
        }

        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/adresses
    // ----------------------------------------------------------------

    public function addresses(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->requireIndividual($userId, $lang);

        $success = $_SESSION['flash']['address_success'] ?? null;
        $error   = $_SESSION['flash']['address_error']   ?? null;
        unset($_SESSION['flash']['address_success'], $_SESSION['flash']['address_error']);

        $addressList = $this->addresses->getByUser($userId);
        $addressIds  = array_map(fn ($a) => (int) $a['id'], $addressList);
        $lockedIds   = $this->orders->getAddressIdsWithActiveOrders($addressIds);

        $this->view('account/addresses', [
            'lang'      => $lang,
            'addresses' => $addressList,
            'lockedIds' => $lockedIds,
            'success'   => $success,
            'error'     => $error,
            'csrf'      => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/adresses/ajouter
    // ----------------------------------------------------------------

    public function addAddress(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/adresses";

        $this->requireIndividual($userId, $lang);

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['address_error'] = __('error.csrf');
            Response::redirect($back);
        }

        $type      = $this->request->post('type', '');
        $civility  = $this->request->post('civility', '');
        $firstname = trim($this->request->post('firstname', ''));
        $lastname  = trim($this->request->post('lastname', ''));
        $street    = trim($this->request->post('street', ''));
        $city      = trim($this->request->post('city', ''));
        $zipCode   = trim($this->request->post('zip_code', ''));
        $country   = trim($this->request->post('country', 'France'));
        $phone     = $this->normalizePhone(trim($this->request->post('phone', '')));

        if ($firstname === '' || $lastname === '' || $street === '' || $city === '' || $zipCode === '' || $phone === '') {
            $_SESSION['flash']['address_error'] = __('account.address_required_fields');
            Response::redirect($back);
        }

        if ($country === 'France' && !$this->isValidFranceMetroZip($zipCode)) {
            $_SESSION['flash']['address_error'] = __('account.address_zip_invalid');
            Response::redirect($back);
        }

        $this->addresses->create($userId, $type, $firstname, $lastname, $civility, $street, $city, $zipCode, $country, $phone);
        $_SESSION['flash']['address_success'] = __('account.address_added');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/adresses/{id}/modifier
    // ----------------------------------------------------------------

    public function editAddress(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        $this->requireIndividual($userId, $lang);

        $address = $this->addresses->findByIdForUser($id, $userId);
        if (!$address) {
            Response::redirect("/{$lang}/mon-compte/adresses");
        }

        $this->view('account/address_form', [
            'lang'    => $lang,
            'address' => $address,
            'csrf'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/adresses/{id}/modifier
    // ----------------------------------------------------------------

    public function updateAddress(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/adresses";

        $this->requireIndividual($userId, $lang);

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['address_error'] = __('error.csrf');
            Response::redirect($back);
        }

        $address = $this->addresses->findByIdForUser($id, $userId);
        if (!$address) {
            Response::redirect($back);
        }

        $civility  = $this->request->post('civility', '');
        $firstname = trim($this->request->post('firstname', ''));
        $lastname  = trim($this->request->post('lastname', ''));
        $street    = trim($this->request->post('street', ''));
        $city      = trim($this->request->post('city', ''));
        $zipCode   = trim($this->request->post('zip_code', ''));
        $country   = trim($this->request->post('country', 'France'));
        $phone     = $this->normalizePhone(trim($this->request->post('phone', '')));

        if ($firstname === '' || $lastname === '' || $street === '' || $city === '' || $zipCode === '' || $phone === '') {
            $_SESSION['flash']['address_error'] = __('account.address_required_fields');
            Response::redirect($back);
        }

        if ($country === 'France' && !$this->isValidFranceMetroZip($zipCode)) {
            $_SESSION['flash']['address_error'] = __('account.address_zip_invalid');
            Response::redirect($back);
        }

        if ($this->orders->hasActiveOrderForAddress($id)) {
            $_SESSION['flash']['address_error'] = __('account.address_edit_blocked');
            Response::redirect($back);
        }

        $this->addresses->update($id, $userId, $firstname, $lastname, $civility, $street, $city, $zipCode, $country, $phone);
        $_SESSION['flash']['address_success'] = __('account.address_updated');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/adresses/{id}/supprimer
    // ----------------------------------------------------------------

    public function deleteAddress(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/adresses";

        $this->requireIndividual($userId, $lang);

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['address_error'] = __('error.csrf');
            Response::redirect($back);
        }

        $address = $this->addresses->findByIdForUser($id, $userId);
        if (!$address) {
            Response::redirect($back);
        }

        if ($this->orders->hasActiveOrderForAddress($id)) {
            $_SESSION['flash']['address_error'] = __('account.address_delete_blocked');
            Response::redirect($back);
        }

        $this->addresses->deleteForUser($id, $userId);
        $_SESSION['flash']['address_success'] = __('account.address_deleted');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/favoris
    // ----------------------------------------------------------------

    public function favorites(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->view('account/favorites', [
            'lang'      => $lang,
            'account'   => $this->accounts->findById($userId),
            'favorites' => $this->favorites->getByUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/securite
    // ----------------------------------------------------------------

    public function security(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success = $_SESSION['flash']['security_success'] ?? null;
        $errors  = $_SESSION['flash']['security_errors']  ?? [];
        unset($_SESSION['flash']['security_success'], $_SESSION['flash']['security_errors']);

        $this->view('account/security', [
            'lang'               => $lang,
            'account'            => $this->accounts->findById($userId),
            'sessions'           => $this->connections->getActiveForUser($userId),
            'trustedDevices'     => $this->trustedDevices->getForUser($userId),
            'currentToken'       => $_COOKIE['auth_token'] ?? null,
            'currentDeviceToken' => $_COOKIE['device_token'] ?? null,
            'errors'             => $errors,
            'success'            => $success,
            'csrf'               => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/mot-de-passe
    // ----------------------------------------------------------------

    public function changePassword(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/securite";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect($back);
        }

        $current = $this->request->post('current_password', '');
        $new     = $this->request->post('new_password', '');
        $confirm = $this->request->post('new_password_confirm', '');

        $account = $this->accounts->findById($userId);
        $errors  = [];

        if (
            !$account
            || $account['password'] === null
            || !password_verify($current, $account['password'])
        ) {
            $errors['current_password'] = __('account.wrong_current_password');
        }

        if (strlen($new) < 12) {
            $errors['new_password'] = __('validation.password_min');
        }

        if ($new !== $confirm) {
            $errors['new_password_confirm'] = __('validation.password_match');
        }

        if ($errors !== []) {
            $_SESSION['flash']['security_errors'] = $errors;
            Response::redirect($back);
        }

        $this->accounts->updatePassword($userId, password_hash($new, PASSWORD_BCRYPT));
        $_SESSION['flash']['security_success'] = __('account.password_updated');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/sessions/revoquer-toutes
    // ----------------------------------------------------------------

    public function revokeAllUserSessions(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $this->accounts->revokeAllSessions($userId);

        // La session courante est révoquée aussi → déconnecter
        CookieHelper::clear();
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/session/{id}/revoquer
    // ----------------------------------------------------------------

    public function revokeSession(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        if ($this->verifyCsrf() && $id > 0) {
            $tokenOfRevoked = $this->connections->getTokenById($id, $userId);
            $this->connections->revokeById($id, $userId);

            // Si la session révoquée est la session courante → déconnecter
            $currentToken = $_COOKIE['auth_token'] ?? null;
            if ($tokenOfRevoked !== null && $currentToken !== null && $tokenOfRevoked === $currentToken) {
                CookieHelper::clear();
                Response::redirect("/{$lang}");
            }
        }

        Response::redirect("/{$lang}/mon-compte/securite");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/nouvel-appareil
    // ----------------------------------------------------------------

    public function newDevice(array $params): void
    {
        // Pas d'auth requise : l'utilisateur n'est pas encore connecté lors du MFA
        $lang    = $params['lang'];
        $pending = $_SESSION['pending_device'] ?? null;

        if (!$pending) {
            Response::redirect("/{$lang}");
        }

        $this->view('account/new_device', [
            'lang'       => $lang,
            'deviceName' => $pending['device_name'] ?? '',
            'mfaToken'   => $pending['mfa_token']   ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/appareil/confirmer  (lien email MFA)
    // ----------------------------------------------------------------

    public function confirmDevice(array $params): void
    {
        // Pas d'auth requise. Ce handler marque UNIQUEMENT le token comme confirmé.
        // Le JWT est émis par /api/mfa/poll sur la page d'attente (polling async).
        $lang  = $params['lang'];
        $token = $_GET['token'] ?? '';

        $confirmed = $this->deviceConfirmTokens->confirm($token);

        $this->view('account/device_confirmed', [
            'lang'    => $lang,
            'success' => $confirmed,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/appareils/retirer-confiance
    // ----------------------------------------------------------------

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/appareil/annuler  (lien "Ce n'était pas moi")
    // ----------------------------------------------------------------

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/reinitialiser
    // ----------------------------------------------------------------

    public function resetSecurity(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $password = $this->request->post('password', '');
        $account  = $this->accounts->findById($userId);

        if (
            !$account
            || $account['password'] === null
            || !password_verify($password, (string) $account['password'])
        ) {
            $_SESSION['flash']['security_errors'] = ['reset_password' => __('account.wrong_current_password')];
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        // Révoque toutes les sessions actives
        $this->accounts->revokeAllSessions($userId);

        // Supprime tous les appareils de confiance
        $this->trustedDevices->deleteAllForUser($userId);

        // Déconnecte l'utilisateur courant
        \Core\CookieHelper::clear();

        $_SESSION['flash']['info'] = __('account.security_reset_done');
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/appareils/supprimer-toutes
    // ----------------------------------------------------------------

    public function untrustAllDevices(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $this->trustedDevices->deleteAllForUser($userId);
        $_SESSION['flash']['security_success'] = __('account.untrust_all_done');
        Response::redirect("/{$lang}/mon-compte/securite#appareils");
    }

    // ----------------------------------------------------------------

    public function cancelMfa(array $params): void
    {
        // Pas d'auth requise — révoque le token MFA pour invalider le lien de confirmation.
        $lang  = $params['lang'];
        $token = $_GET['token'] ?? '';

        $revoked = false;
        if ($token !== '') {
            $record = $this->deviceConfirmTokens->findByToken($token);
            if ($record) {
                $this->deviceConfirmTokens->deleteByToken($token);
                $revoked = true;
            }
        }

        $this->view('account/mfa_cancelled', [
            'lang'    => $lang,
            'revoked' => $revoked,
        ]);
    }

    // ----------------------------------------------------------------

    public function untrustDevice(array $params): void
    {
        $payload     = $this->requireCustomer();
        $userId      = (int) $payload['sub'];
        $lang        = $params['lang'];
        $deviceToken = $this->request->post('device_token', '');

        if ($this->verifyCsrf() && $deviceToken !== '') {
            $this->trustedDevices->untrust($userId, $deviceToken);
        }

        Response::redirect("/{$lang}/mon-compte/securite#appareils");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/profil
    // ----------------------------------------------------------------

    public function profile(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success = $_SESSION['flash']['profile_success'] ?? null;
        $errors  = $_SESSION['flash']['profile_errors']  ?? [];
        unset($_SESSION['flash']['profile_success'], $_SESSION['flash']['profile_errors']);

        $this->view('account/profile', [
            'lang'       => $lang,
            'account'    => $this->accounts->findById($userId),
            'errors'     => $errors,
            'success'    => $success,
            'csrf'       => $_SESSION['csrf'] ?? '',
            'ownerEmail' => $_ENV['CONTACT_OWNER_EMAIL'] ?? $_ENV['MAIL_USER'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/profil
    // ----------------------------------------------------------------

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

            if ($firstname === '') {
                $errors['firstname'] = __('validation.required');
            }
            if ($lastname === '') {
                $errors['lastname'] = __('validation.required');
            }

            if ($errors === []) {
                $this->accounts->updateIndividualProfile($userId, $civility, $firstname, $lastname);
            }
        } elseif ($account && $account['account_type'] === 'company') {
            $companyName = trim($this->request->post('company_name', ''));
            $siret       = trim($this->request->post('siret', '')) ?: null;

            if ($companyName === '') {
                $errors['company_name'] = __('validation.required');
            }

            if ($errors === []) {
                $this->accounts->updateCompanyProfile($userId, $companyName, $siret);
            }
        }

        if ($errors !== []) {
            $_SESSION['flash']['profile_errors'] = $errors;
            Response::redirect($back);
        }

        $this->accounts->updateNewsletter($userId, $newsletter);
        $_SESSION['flash']['profile_success'] = __('account.profile_updated');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/supprimer-compte
    // ----------------------------------------------------------------

    public function deleteAccount(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/securite";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect($back);
        }

        // Vérification du texte de confirmation « SUPPRESSION »
        $confirmText = $this->request->post('confirm_text', '');
        if ($confirmText !== 'SUPPRESSION') {
            $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_wrong_confirm_text')];
            Response::redirect($back);
        }

        // Vérification du mot de passe saisi dans le modal de confirmation
        $confirmPwd = $this->request->post('confirm_password', '');
        $account    = $this->accounts->findById($userId);

        if (
            !$account
            || $account['password'] === null
            || !password_verify($confirmPwd, (string) $account['password'])
        ) {
            $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_wrong_password')];
            Response::redirect($back);
        }

        if ($this->orders->hasActiveOrdersForUser($userId)) {
            $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_blocked_orders')];
            Response::redirect($back);
        }

        // Révoquer toutes les sessions
        $this->accounts->revokeAllSessions($userId);

        // Soft-delete + programmation anonymisation J+30
        $this->accounts->delete($userId);

        // Email de confirmation RGPD Art. 17 avec lien de réactivation
        $name             = $account['firstname'] ?? $account['company_name'] ?? 'Client';
        $reactivationToken = $this->accounts->getReactivationToken($userId) ?? '';
        try {
            (new \Service\MailService())->sendAccountDeletionConfirmation(
                (string) $account['email'],
                (string) $name,
                $lang,
                $reactivationToken
            );
        } catch (\Throwable) {
            // L'envoi de l'email ne bloque pas la suppression
        }

        // Supprimer le cookie
        CookieHelper::clear();

        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/compte/reactiver?token=xxx
    // ----------------------------------------------------------------

    public function reactivateAccount(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');

        if ($token === '') {
            $this->view('account/reactivate', ['lang' => $lang, 'success' => false]);
            return;
        }

        $account = $this->accounts->findByReactivationToken($token);
        if ($account === false) {
            $this->view('account/reactivate', ['lang' => $lang, 'success' => false]);
            return;
        }

        $this->accounts->reactivate((int) $account['id']);
        $this->view('account/reactivate', ['lang' => $lang, 'success' => true]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/newsletter/desabonnement?token=xxx  — confirmation
    // ----------------------------------------------------------------

    public function unsubscribePage(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');

        if ($token === '' || !$this->accounts->findByUnsubscribeToken($token)) {
            $this->view('account/unsubscribe', ['lang' => $lang, 'success' => false, 'confirm' => false]);
            return;
        }

        $this->view('account/unsubscribe', [
            'lang'       => $lang,
            'success'    => false,
            'confirm'    => true,
            'unsubToken' => $token,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/newsletter/desabonnement  — désabonnement effectif
    // ----------------------------------------------------------------

    public function unsubscribe(array $params): void
    {
        $lang       = $params['lang'];
        $unsubToken = $this->request->post('unsub_token', '');
        $account    = $unsubToken !== '' ? $this->accounts->findByUnsubscribeToken($unsubToken) : false;
        $success = false;

        if ($account !== false) {
            $this->accounts->unsubscribeByToken($unsubToken);
            $success = true;
        }

        $this->view('account/unsubscribe', [
            'lang'    => $lang,
            'success' => $success,
            'confirm' => false,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/export
    // ----------------------------------------------------------------

    public function exportPage(array $params): void
    {
        $payload = $this->requireCustomer();
        $lang    = $params['lang'];

        $this->view('account/export', [
            'lang'    => $lang,
            'account' => $this->accounts->findById((int) $payload['sub']),
            'csrf'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/export/telecharger
    // ----------------------------------------------------------------

    public function exportData(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];

        $account        = $this->accounts->findById($userId);
        $addresses      = $this->addresses->getByUser($userId);
        $favorites      = $this->favorites->getByUser($userId);
        $orders         = $this->orders->getForUser($userId, 1, 9999);
        $trustedDevices = $this->trustedDevices->getForUser($userId);
        $sessions       = $this->connections->getActiveForUser($userId);

        $export = [
            'exported_at' => date('c'),
            'account'     => [
                'Email'                  => $account['email']        ?? null,
                'Type de compte'         => $account['account_type'] ?? null,
                'Langue'                 => $account['lang']         ?? null,
                'Date de création'       => $account['created_at']   ?? null,
                'Prénom'                 => $account['firstname']    ?? null,
                'Nom'                    => $account['lastname']     ?? null,
                'Civilité'               => $account['civility']     ?? null,
                'Raison sociale'         => $account['company_name'] ?? null,
                'Newsletter'             => ($account['newsletter'] ?? 0) ? 'Oui' : 'Non',
            ],
            'addresses' => array_map(fn($a) => [
                'type'      => $a['type'],
                'civility'  => $a['civility'],
                'firstname' => $a['firstname'],
                'lastname'  => $a['lastname'],
                'street'    => $a['street'],
                'city'      => $a['city'],
                'zip_code'  => $a['zip_code'],
                'country'   => $a['country'],
                'phone'     => $a['phone'],
            ], $addresses),
            'orders' => array_map(fn($o) => [
                'reference'  => $o['order_reference'],
                'status'     => $o['status'],
                'price'      => $o['price'],
                'ordered_at' => $o['ordered_at'],
            ], $orders),
            'favorites' => array_map(fn($f) => [
                'name'    => $f['name'],
                'vintage' => $f['vintage'],
            ], $favorites),
            'trusted_devices' => array_map(fn($d) => [
                'device_name'  => $d['device_name'],
                'confirmed_at' => $d['confirmed_at'],
                'last_seen'    => $d['last_seen'],
            ], $trustedDevices),
            'active_sessions' => array_map(fn($s) => [
                'device_name' => $s['device_name'],
                'ip_address'  => $s['ip_address'],
                'created_at'  => $s['created_at'],
                'expired_at'  => $s['expired_at'],
            ], $sessions),
        ];

        $date     = date('Y-m-d');
        $basename = 'mes-donnees-' . $date;
        $json     = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (class_exists('ZipArchive')) {
            $pdf    = $this->buildExportPdf($export, $date);
            $tmpZip = tempnam(sys_get_temp_dir(), 'cbv_export_');
            $zip    = new \ZipArchive();
            $zip->open($tmpZip, \ZipArchive::OVERWRITE);
            $zip->addFromString($basename . '.json', $json);
            $zip->addFromString($basename . '.pdf', $pdf);
            $zip->close();

            $content = (string) file_get_contents($tmpZip);
            unlink($tmpZip);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $basename . '.zip"');
        } else {
            // Fallback si l'extension zip n'est pas activée
            $content = $json;
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $basename . '.json"');
        }

        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    /**
     * Génère un PDF lisible (RGPD Art. 20 — portabilité) via TCPDF.
     * @param array<string, mixed> $export
     */
    private function buildExportPdf(array $export, string $date): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Crabitan Bellevue');
        $pdf->SetAuthor('Crabitan Bellevue');
        $pdf->SetTitle('Mes données personnelles — ' . $date);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $gold = [193, 161, 75];
        $dark = [30, 30, 30];

        $h = '<style>
            body { font-family: dejavusans; font-size: 10pt; color: #1e1e1e; }
            h1 { font-size: 16pt; color: #c1a14b; margin-bottom: 4px; }
            h2 { font-size: 11pt; color: #c1a14b; margin-top: 14px; margin-bottom: 4px; border-bottom: 1px solid #c1a14b; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
            th { background: #f5f0e8; font-weight: bold; padding: 4px 6px; text-align: left; }
            td { padding: 4px 6px; border-bottom: 1px solid #e8e0d0; }
            .muted { color: #888; font-size: 9pt; }
        </style>';

        $acc = $export['account'];
        $name = trim(($acc['firstname'] ?? '') . ' ' . ($acc['lastname'] ?? $acc['company_name'] ?? ''));

        $h .= '<h1>Mes données personnelles</h1>';
        $h .= '<p class="muted">Exporté le ' . htmlspecialchars($date) . ' — RGPD Art. 20</p>';

        $h .= '<h2>Compte</h2><table><tr><th>Champ</th><th>Valeur</th></tr>';
        foreach ($acc as $k => $v) {
            $h .= '<tr><td>' . htmlspecialchars($k) . '</td><td>' . htmlspecialchars((string) ($v ?? '—')) . '</td></tr>';
        }
        $h .= '</table>';

        $h .= '<h2>Commandes (' . count($export['orders']) . ')</h2>';
        if ($export['orders'] !== []) {
            $h .= '<table><tr><th>Référence</th><th>Statut</th><th>Total</th><th>Date</th></tr>';
            foreach ($export['orders'] as $o) {
                $h .= '<tr><td>' . htmlspecialchars($o['reference']) . '</td>'
                    . '<td>' . htmlspecialchars($o['status']) . '</td>'
                    . '<td>' . htmlspecialchars((string) $o['price']) . ' €</td>'
                    . '<td>' . htmlspecialchars((string) $o['ordered_at']) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p class="muted">Aucune commande.</p>';
        }

        $h .= '<h2>Adresses (' . count($export['addresses']) . ')</h2>';
        if ($export['addresses'] !== []) {
            $h .= '<table><tr><th>Type</th><th>Nom</th><th>Adresse</th><th>Ville</th><th>Pays</th></tr>';
            foreach ($export['addresses'] as $a) {
                $h .= '<tr><td>' . htmlspecialchars($a['type']) . '</td>'
                    . '<td>' . htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) . '</td>'
                    . '<td>' . htmlspecialchars($a['street']) . '</td>'
                    . '<td>' . htmlspecialchars($a['zip_code'] . ' ' . $a['city']) . '</td>'
                    . '<td>' . htmlspecialchars($a['country']) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p class="muted">Aucune adresse.</p>';
        }

        $h .= '<h2>Favoris (' . count($export['favorites']) . ')</h2>';
        if ($export['favorites'] !== []) {
            $h .= '<table><tr><th>Vin</th><th>Millésime</th></tr>';
            foreach ($export['favorites'] as $f) {
                $h .= '<tr><td>' . htmlspecialchars($f['name'] ?? '') . '</td>'
                    . '<td>' . htmlspecialchars((string) ($f['vintage'] ?? '')) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p class="muted">Aucun favori.</p>';
        }

        $h .= '<h2>Appareils de confiance (' . count($export['trusted_devices']) . ')</h2>';
        if ($export['trusted_devices'] !== []) {
            $h .= '<table><tr><th>Appareil</th><th>Confirmé le</th><th>Dernière activité</th></tr>';
            foreach ($export['trusted_devices'] as $d) {
                $h .= '<tr><td>' . htmlspecialchars($d['device_name'] ?? '—') . '</td>'
                    . '<td>' . htmlspecialchars((string) ($d['confirmed_at'] ?? '—')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($d['last_seen'] ?? '—')) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p class="muted">Aucun appareil de confiance.</p>';
        }

        $h .= '<h2>Sessions actives (' . count($export['active_sessions']) . ')</h2>';
        if ($export['active_sessions'] !== []) {
            $h .= '<table><tr><th>Appareil</th><th>Adresse IP</th><th>Connecté le</th><th>Expire le</th></tr>';
            foreach ($export['active_sessions'] as $s) {
                $h .= '<tr><td>' . htmlspecialchars($s['device_name'] ?? '—') . '</td>'
                    . '<td>' . htmlspecialchars($s['ip_address'] ?? '—') . '</td>'
                    . '<td>' . htmlspecialchars((string) ($s['created_at'] ?? '—')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($s['expired_at'] ?? '—')) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p class="muted">Aucune session active.</p>';
        }

        $pdf->writeHTML($h, true, false, true, false, '');

        return $pdf->Output('', 'S'); // retourne le PDF comme string
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Normalise un numéro de téléphone : conserve le format international tel quel,
     * nettoie simplement les espaces superflus. Accepte +33, +49, 06, etc.
     */
    private function normalizePhone(string $phone): string
    {
        // Réduction des espaces multiples, trim
        return trim((string) preg_replace('/\s{2,}/', ' ', $phone));
    }

    /**
     * France métropolitaine hors Corse : 01000–95999, sauf 20xxx (Corse) et 97/98xxx (DOM-TOM).
     */
    private function isValidFranceMetroZip(string $zip): bool
    {
        if (!preg_match('/^\d{5}$/', $zip)) {
            return false;
        }
        $num    = (int) $zip;
        $prefix = (int) substr($zip, 0, 2);
        return $num >= 1000 && $num <= 95999 && $prefix !== 20;
    }

    private function requireCustomer(): array
    {
        $payload = AuthMiddleware::handle();
        if (($payload['role'] ?? '') !== 'customer') {
            Response::abort(404);
        }
        return $payload;
    }

    /**
     * Redirige les comptes société vers le dashboard avec un message d'info.
     * Les vues commandes et adresses sont réservées aux comptes particuliers ;
     * les sociétés passeront par le panier B2B (message dédié à ce moment-là).
     */
    private function requireIndividual(int $userId, string $lang): void
    {
        $account = $this->accounts->findById($userId);
        if ($account && $account['account_type'] === 'company') {
            $_SESSION['flash']['info'] = __('account.b2b_restricted');
            Response::redirect("/{$lang}/mon-compte");
        }
    }

    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}
