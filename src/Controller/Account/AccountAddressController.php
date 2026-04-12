<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Controller;
use Core\Response;
use Middleware\AuthMiddleware;
use Model\AccountModel;
use Model\AddressModel;
use Model\OrderModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;

/**
 * Gère le CRUD des adresses de livraison/facturation du compte client.
 */
class AccountAddressController extends Controller
{
    private UserRepository $userRepo;
    private AddressModel $addresses;
    private OrderModel $orders;

    /**
     * Initialise le contrôleur et ses dépendances.
     *
     * @param \Core\Request       $request  Requête HTTP courante
     * @param UserRepository|null $userRepo Dépôt utilisateur (null → MysqlUserRepository par défaut)
     */
    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request);
        $this->userRepo  = $userRepo ?? new MysqlUserRepository(new AccountModel());
        $this->addresses = new AddressModel();
        $this->orders    = new OrderModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/adresses
    // ----------------------------------------------------------------

    /**
     * Affiche la liste des adresses du compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
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

    /**
     * Crée une nouvelle adresse pour le compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
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

        if (
            $firstname === '' || $lastname === '' || $street === ''
            || $city === '' || $zipCode === '' || $phone === ''
        ) {
            $_SESSION['flash']['address_error'] = __('account.address_required_fields');
            Response::redirect($back);
        }

        if ($country === 'France' && !$this->isValidFranceMetroZip($zipCode)) {
            $_SESSION['flash']['address_error'] = __('account.address_zip_invalid');
            Response::redirect($back);
        }

        $this->addresses->create(
            $userId,
            $type,
            $firstname,
            $lastname,
            $civility,
            $street,
            $city,
            $zipCode,
            $country,
            $phone
        );
        $_SESSION['flash']['address_success'] = __('account.address_added');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/adresses/{id}/modifier
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire de modification d'une adresse.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
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

    /**
     * Met à jour une adresse existante.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
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

        if (
            $firstname === '' || $lastname === '' || $street === ''
            || $city === '' || $zipCode === '' || $phone === ''
        ) {
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

        $this->addresses->update(
            $id,
            $userId,
            $firstname,
            $lastname,
            $civility,
            $street,
            $city,
            $zipCode,
            $country,
            $phone
        );
        $_SESSION['flash']['address_success'] = __('account.address_updated');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/adresses/{id}/supprimer
    // ----------------------------------------------------------------

    /**
     * Supprime une adresse du compte.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
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
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Normalise un numéro de téléphone.
     *
     * @param string $phone Numéro brut
     * @return string Numéro normalisé
     */
    private function normalizePhone(string $phone): string
    {
        return trim((string) preg_replace('/\s{2,}/', ' ', $phone));
    }

    /**
     * Valide un code postal France métropolitaine hors Corse.
     *
     * @param string $zip Code postal à valider
     * @return bool
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

    /**
     * Redirige les comptes société vers le dashboard (vues adresses réservées aux particuliers).
     *
     * @param int    $userId Identifiant utilisateur
     * @param string $lang   Langue courante
     * @return void
     */
    private function requireIndividual(int $userId, string $lang): void
    {
        $account = $this->userRepo->findById($userId);
        if ($account && $account['account_type'] === 'company') {
            $_SESSION['flash']['info'] = __('account.b2b_restricted');
            Response::redirect("/{$lang}/mon-compte");
        }
    }
}
