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
    private const PER_PAGE = 10;

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

        $page  = max(1, (int) $this->request->get('page', '1'));
        $total = $this->orders->countForUser($userId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('account/orders', [
            'lang'   => $lang,
            'orders' => $this->orders->getForUser($userId, $page, self::PER_PAGE),
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
        ]);
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
            $this->connections->revokeById($id, $userId);
        }

        Response::redirect("/{$lang}/mon-compte/securite");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/export
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
