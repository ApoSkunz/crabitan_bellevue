<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Middleware\AuthMiddleware;
use Model\AccountModel;
use Model\AddressModel;
use Model\CartModel;
use Model\OrderModel;
use Model\PricingRuleModel;
use Model\WineModel;

/**
 * Contrôleur de la page de commande (checkout).
 *
 * GET  /{lang}/commande             → affiche le formulaire de commande
 * POST /{lang}/commande/paiement    → valide et crée la commande
 * GET  /{lang}/commande/confirmation → affiche la confirmation après paiement
 */
class OrderController extends Controller
{
    /** Version des CGV intégrée dans chaque commande. */
    private const CGV_VERSION = '1.0';

    /** Délai de livraison estimé affiché au client (L216-1 Code conso). */
    private const DELIVERY_DELAY = '3 à 5 jours ouvrés';

    private AddressModel $addresses;
    private CartModel $carts;
    private OrderModel $orders;
    private PricingRuleModel $pricing;
    private WineModel $wines;

    /**
     * Initialise les dépendances du contrôleur.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->addresses = new AddressModel();
        $this->carts     = new CartModel();
        $this->orders    = new OrderModel();
        $this->pricing   = new PricingRuleModel();
        $this->wines     = new WineModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/commande
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire de commande.
     *
     * Vérifie que l'utilisateur est authentifié, son panier non vide,
     * nettoie les articles indisponibles, charge les adresses et les
     * informations tarifaires, puis rend la vue.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function checkout(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $this->resolveLang($params);

        // Nettoyer les articles indisponibles ou hors stock
        $removed = $this->carts->removeUnavailableItems(
            $userId,
            fn(int $id): ?array => $this->wines->getById($id)
        );

        $row = $this->carts->findByUserId($userId);
        if ($row === false || $this->carts->getContent($row) === []) {
            Response::redirect("/{$lang}/panier");
        }

        $items    = $this->enrichItems($this->carts->getContent($row));
        $totalQty = (int) array_sum(array_column($items, 'qty'));
        $subtotal = (float) array_sum(array_map(fn(array $i): float => (float) $i['price'] * (int) $i['qty'], $items));

        $pricingRule      = $this->pricing->findForQuantity($totalQty);
        $deliveryDiscount = $this->pricing->computeDeliveryDiscount($totalQty);

        $errors = $_SESSION['flash']['checkout_errors'] ?? [];
        $post   = $_SESSION['flash']['checkout_post']   ?? [];
        unset($_SESSION['flash']['checkout_errors'], $_SESSION['flash']['checkout_post']);

        $this->view('order/checkout', [
            'lang'             => $lang,
            'items'            => $items,
            'totalQty'         => $totalQty,
            'subtotal'         => $subtotal,
            'deliveryDiscount' => $deliveryDiscount,
            'total'            => max(0.0, $subtotal - $deliveryDiscount),
            'addresses'        => $this->addresses->getByUser($userId),
            'errors'           => $errors,
            'post'             => $post,
            'removedItems'     => $removed,
            'deliveryDelay'    => self::DELIVERY_DELAY,
            'csrfToken'        => $_SESSION['csrf'] ?? '',
            'pricingRule'      => $pricingRule,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/commande/paiement
    // ----------------------------------------------------------------

    /**
     * Valide le formulaire de commande, crée la commande et vide le panier.
     *
     * En cas d'erreur, redirige vers le checkout avec les messages flash.
     * En cas de succès, stocke la référence en session et redirige vers la confirmation.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function payment(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $this->resolveLang($params);
        $back    = "/{$lang}/commande";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['checkout_errors']['csrf'] = __('error.csrf');
            Response::redirect($back);
        }

        // Vérifier le panier
        $row = $this->carts->findByUserId($userId);
        if ($row === false || $this->carts->getContent($row) === []) {
            Response::redirect("/{$lang}/panier");
        }

        // Valider la méthode de paiement
        $paymentMethod = $this->request->post('payment_method', '');
        if (!in_array($paymentMethod, OrderModel::VALID_PAYMENT_METHODS, true)) {
            $_SESSION['flash']['checkout_errors']['payment_method'] = __('checkout.error_payment_method');
            $_SESSION['flash']['checkout_post'] = $_POST;
            Response::redirect($back);
        }

        // Valider l'acceptation des CGV
        if (!$this->request->post('cgv', '')) {
            $_SESSION['flash']['checkout_errors']['cgv'] = __('checkout.error_cgv');
            $_SESSION['flash']['checkout_post'] = $_POST;
            Response::redirect($back);
        }

        // Valider le multiple de 12
        $items    = $this->enrichItems($this->carts->getContent($row));
        $totalQty = (int) array_sum(array_column($items, 'qty'));
        if ($totalQty % 12 !== 0) {
            $_SESSION['flash']['checkout_errors']['multiple_12'] = __('checkout.error_multiple_12');
            $_SESSION['flash']['checkout_post'] = $_POST;
            Response::redirect($back);
        }

        // Résoudre l'adresse de facturation
        $billingAddressId = $this->resolveAddress($userId, 'billing', '', $back);

        // Résoudre l'adresse de livraison (optionnelle)
        $deliveryAddressId = null;
        if (!$this->request->post('same_address', '')) {
            $deliveryAddressId = $this->resolveAddress($userId, 'delivery', 'del_', $back);
        }

        // Calculer les totaux (items et totalQty déjà calculés avant la vérification multiple de 12)
        $subtotal         = (float) array_sum(array_map(fn(array $i): float => (float) $i['price'] * (int) $i['qty'], $items));
        $deliveryDiscount = $this->pricing->computeDeliveryDiscount($totalQty);
        $total            = round(max(0.0, $subtotal - $deliveryDiscount), 2);

        // Newsletter opt-in
        if ($this->request->post('newsletter', '')) {
            (new AccountModel())->updateNewsletter($userId, true);
        }

        // Créer la commande
        $reference = $this->orders->create(
            $userId,
            $items,
            $total,
            $paymentMethod,
            round($deliveryDiscount, 2),
            $billingAddressId,
            $deliveryAddressId,
            self::CGV_VERSION
        );

        // Vider le panier
        $this->carts->clear($userId);
        setcookie('cb-cart', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);

        // Stocker pour la page de confirmation
        $_SESSION['last_order_ref']     = $reference;
        $_SESSION['last_order_payment'] = $paymentMethod;

        Response::redirect("/{$lang}/commande/confirmation");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/commande/confirmation
    // ----------------------------------------------------------------

    /**
     * Affiche la page de confirmation après la création d'une commande.
     *
     * Lit la référence stockée en session par payment(), charge le détail
     * de la commande depuis la BDD et rend la vue de confirmation.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function confirmation(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $this->resolveLang($params);

        $reference = $_SESSION['last_order_ref'] ?? null;
        if ($reference === null) {
            Response::redirect("/{$lang}/mon-compte/commandes");
        }

        $order = $this->orders->findByReference((string) $reference, $userId);
        if ($order === null) {
            Response::redirect("/{$lang}/mon-compte/commandes");
        }

        unset($_SESSION['last_order_ref'], $_SESSION['last_order_payment']);

        $items = json_decode((string) ($order['content'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];

        $this->view('order/confirmation', [
            'lang'  => $lang,
            'order' => $order,
            'items' => $items,
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Résout une adresse (existante ou nouvelle) depuis les données POST.
     *
     * Si `{prefix}address_id` vaut 0, crée une nouvelle adresse depuis les champs POST.
     * Sinon vérifie que l'adresse existante appartient à l'utilisateur.
     *
     * @param int    $userId Identifiant de l'utilisateur
     * @param string $type   'billing' ou 'delivery'
     * @param string $prefix Préfixe des champs POST pour une nouvelle adresse ('', 'del_'…)
     * @param string $back   URL de redirection en cas d'erreur
     * @return int Identifiant de l'adresse
     */
    private function resolveAddress(int $userId, string $type, string $prefix, string $back): int
    {
        $fieldName = $type === 'billing' ? 'billing_address_id' : 'delivery_address_id';
        $addressId = (int) $this->request->post($fieldName, '0');

        if ($addressId > 0) {
            if (!$this->addresses->findByIdForUser($addressId, $userId)) {
                $_SESSION['flash']['checkout_errors'][$type] = __('checkout.error_address_invalid');
                Response::redirect($back);
            }
            return $addressId;
        }

        // Nouvelle adresse
        $get = fn(string $k): string => trim($this->request->post($prefix . $k, ''));

        $firstname = $get('firstname');
        $lastname  = $get('lastname');
        $street    = $get('street');
        $city      = $get('city');
        $zipCode   = $get('zip_code');
        $country   = $get('country') ?: 'France';
        $phone     = $get('phone');
        $civility  = $this->request->post($prefix . 'civility', 'M');

        if ($firstname === '' || $lastname === '' || $street === '' || $city === '' || $zipCode === '' || $phone === '') { // phpcs:ignore Generic.Files.LineLength
            $_SESSION['flash']['checkout_errors'][$type] = __('checkout.error_address_required');
            $_SESSION['flash']['checkout_post'] = $_POST;
            Response::redirect($back);
        }

        $newId = $this->addresses->create($userId, $type, $firstname, $lastname, $civility, $street, $city, $zipCode, $country, $phone); // phpcs:ignore Generic.Files.LineLength
        if ($newId === 0) {
            $_SESSION['flash']['checkout_errors'][$type] = __('checkout.error_address_invalid');
            Response::redirect($back);
        }

        return $newId;
    }

    /**
     * Enrichit les articles du panier avec le prix et le nom depuis WineModel.
     *
     * @param array<int, array<string, mixed>> $items Articles bruts du panier [{wine_id, qty}]
     * @return array<int, array<string, mixed>> Articles enrichis avec price et name
     */
    private function enrichItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $wineId = (int) ($item['wine_id'] ?? 0);
            $wine   = $this->wines->getById($wineId);
            $item['price'] = $wine ? (float) $wine['price'] : 0.0;
            $item['name']  = ($item['name'] ?? '') !== '' ? (string) $item['name'] : ($wine ? (string) $wine['label_name'] : '');
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Vérifie que l'utilisateur connecté est un client (role = customer).
     * Abort 404 sinon.
     *
     * @return array<string, mixed> Payload JWT
     */
    private function requireCustomer(): array
    {
        $payload = AuthMiddleware::handle();
        if (($payload['role'] ?? '') !== 'customer') {
            Response::abort(404);
        }
        return $payload;
    }

    /**
     * Vérifie le token CSRF posté.
     *
     * @return bool
     */
    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}
