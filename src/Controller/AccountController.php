<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Middleware\AuthMiddleware;
use Model\AccountModel;
use Model\AddressModel;
use Model\ConnectionModel;
use Model\FavoriteModel;
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

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts    = new AccountModel();
        $this->addresses   = new AddressModel();
        $this->favorites   = new FavoriteModel();
        $this->orders      = new OrderModel();
        $this->connections = new ConnectionModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $account = $this->accounts->findById($userId);

        $this->view('account/index', [
            'lang'          => $lang,
            'account'       => $account,
            'orderCount'    => $this->orders->countForUser($userId),
            'addressCount'  => count($this->addresses->getByUser($userId)),
            'favoriteCount' => $this->favorites->countForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes
    // ----------------------------------------------------------------

    public function orders(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

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

        $total = $this->orders->countForUser($userId, $period === 'all' ? null : $period, $year);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, (int) $this->request->get('page', '1')), $pages);

        $this->view('account/orders', [
            'lang'    => $lang,
            'orders'  => $this->orders->getForUser($userId, $page, $perPage, $period === 'all' ? null : $period, $year),
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'perPage' => $perPage,
            'period'  => $this->request->get('period', 'all'),
            'years'   => $this->orders->getAvailableYearsForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/commandes/{id}
    // ----------------------------------------------------------------

    public function orderDetail(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        $order = $this->orders->findDetailForUser($id, $userId);
        if (!$order) {
            Response::redirect("/{$lang}/mon-compte/commandes");
        }

        $items = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];

        $success = $_SESSION['flash']['order_success'] ?? null;
        $error   = $_SESSION['flash']['order_error']   ?? null;
        unset($_SESSION['flash']['order_success'], $_SESSION['flash']['order_error']);

        $this->view('account/order_detail', [
            'lang'    => $lang,
            'order'   => $order,
            'items'   => $items,
            'success' => $success,
            'error'   => $error,
            'csrf'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/commandes/{id}/annuler
    // ----------------------------------------------------------------

    public function cancelOrder(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/commandes/{$id}";

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
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success = $_SESSION['flash']['address_success'] ?? null;
        $error   = $_SESSION['flash']['address_error']   ?? null;
        unset($_SESSION['flash']['address_success'], $_SESSION['flash']['address_error']);

        $this->view('account/addresses', [
            'lang'      => $lang,
            'addresses' => $this->addresses->getByUser($userId),
            'success'   => $success,
            'error'     => $error,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/adresses/ajouter
    // ----------------------------------------------------------------

    public function addAddress(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/adresses";

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
        $phone     = trim($this->request->post('phone', ''));

        if ($firstname === '' || $lastname === '' || $street === '' || $city === '' || $zipCode === '') {
            $_SESSION['flash']['address_error'] = __('account.address_required_fields');
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
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

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
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/adresses";

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
        $phone     = trim($this->request->post('phone', ''));

        if ($firstname === '' || $lastname === '' || $street === '' || $city === '' || $zipCode === '') {
            $_SESSION['flash']['address_error'] = __('account.address_required_fields');
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
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);
        $back    = "/{$lang}/mon-compte/adresses";

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
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->view('account/favorites', [
            'lang'      => $lang,
            'favorites' => $this->favorites->getByUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/securite
    // ----------------------------------------------------------------

    public function security(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success = $_SESSION['flash']['security_success'] ?? null;
        $errors  = $_SESSION['flash']['security_errors']  ?? [];
        unset($_SESSION['flash']['security_success'], $_SESSION['flash']['security_errors']);

        $this->view('account/security', [
            'lang'         => $lang,
            'sessions'     => $this->connections->getActiveForUser($userId),
            'currentToken' => $_COOKIE['auth_token'] ?? null,
            'errors'       => $errors,
            'success'      => $success,
            'csrf'         => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/mot-de-passe
    // ----------------------------------------------------------------

    public function changePassword(array $params): void
    {
        $payload = AuthMiddleware::handle();
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
    // POST /{lang}/mon-compte/securite/session/{id}/revoquer
    // ----------------------------------------------------------------

    public function revokeSession(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        if ($this->verifyCsrf() && $id > 0) {
            $tokenOfRevoked = $this->connections->getTokenById($id, $userId);
            $this->connections->revokeById($id, $userId);

            // Si la session révoquée est la session courante → déconnecter
            $currentToken = $_COOKIE['auth_token'] ?? null;
            if ($tokenOfRevoked !== null && $currentToken !== null && $tokenOfRevoked === $currentToken) {
                setcookie('auth_token', '', time() - 3600, '/', '', true, true);
                Response::redirect("/{$lang}/connexion");
            }
        }

        Response::redirect("/{$lang}/mon-compte/securite");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/profil
    // ----------------------------------------------------------------

    public function profile(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success = $_SESSION['flash']['profile_success'] ?? null;
        $errors  = $_SESSION['flash']['profile_errors']  ?? [];
        unset($_SESSION['flash']['profile_success'], $_SESSION['flash']['profile_errors']);

        $this->view('account/profile', [
            'lang'    => $lang,
            'account' => $this->accounts->findById($userId),
            'errors'  => $errors,
            'success' => $success,
            'csrf'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/profil
    // ----------------------------------------------------------------

    public function updateProfile(array $params): void
    {
        $payload = AuthMiddleware::handle();
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
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/securite";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect($back);
        }

        if ($this->orders->hasActiveOrdersForUser($userId)) {
            $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_blocked_orders')];
            Response::redirect($back);
        }

        // Révoquer toutes les sessions
        $this->accounts->revokeAllSessions($userId);

        // Soft-delete du compte
        $this->accounts->delete($userId);

        // Supprimer le cookie
        setcookie('auth_token', '', time() - 3600, '/', '', true, true);

        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/export
    // ----------------------------------------------------------------

    public function exportPage(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $lang    = $params['lang'];

        $this->view('account/export', [
            'lang' => $lang,
            'csrf' => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/export/telecharger
    // ----------------------------------------------------------------

    public function exportData(array $params): void
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];

        $account   = $this->accounts->findById($userId);
        $addresses = $this->addresses->getByUser($userId);
        $favorites = $this->favorites->getByUser($userId);
        $orders    = $this->orders->getForUser($userId, 1, PHP_INT_MAX);

        $export = [
            'exported_at' => date('c'),
            'account'     => [
                'email'        => $account['email']        ?? null,
                'account_type' => $account['account_type'] ?? null,
                'lang'         => $account['lang']         ?? null,
                'created_at'   => $account['created_at']   ?? null,
                'firstname'    => $account['firstname']    ?? null,
                'lastname'     => $account['lastname']     ?? null,
                'civility'     => $account['civility']     ?? null,
                'company_name' => $account['company_name'] ?? null,
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
        ];

        $json     = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'mes-donnees-' . date('Y-m-d') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}
