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
    /** Délai de livraison estimé affiché au client (L216-1 Code conso). */
    private const DELIVERY_DELAY    = '7 à 15 jours à compter de la réception du paiement';
    private const DELIVERY_DELAY_EN = '7 to 15 days upon receipt of payment';

    private AddressModel $addresses;
    private CartModel $carts;
    private OrderModel $orders;
    private PricingRuleModel $pricing;
    private WineModel $wines;
    private string $cgvVersion;

    /**
     * Initialise les dépendances du contrôleur.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->addresses  = new AddressModel();
        $this->carts      = new CartModel();
        $this->orders     = new OrderModel();
        $this->pricing    = new PricingRuleModel();
        $this->wines      = new WineModel();
        $cgvConfig        = require_once ROOT_PATH . '/config/cgv.php';
        $this->cgvVersion = (string) ($cgvConfig['version'] ?? '1.0');
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

        $account     = (new AccountModel())->findById($userId);
        $isNewsletterSubscribed = (bool) ($account !== false ? ($account['newsletter'] ?? false) : false);

        $items    = $this->enrichItems($this->carts->getContent($row));
        $totalQty = (int) array_sum(array_column($items, 'qty'));
        $subtotal = (float) array_sum(array_map(fn(array $i): float => (float) $i['price'] * (int) $i['qty'], $items));

        $pricingRule      = $this->pricing->findForQuantity($totalQty);
        $deliveryDiscount = $this->pricing->computeDeliveryDiscount($totalQty);

        $errors = $_SESSION['flash']['checkout_errors'] ?? [];
        $post   = $_SESSION['flash']['checkout_post']   ?? [];
        unset($_SESSION['flash']['checkout_errors'], $_SESSION['flash']['checkout_post']);

        $submitToken = bin2hex(random_bytes(16));
        $_SESSION['submit_token'] = $submitToken;

        $this->view('order/checkout', [
            'lang'                   => $lang,
            'items'                  => $items,
            'totalQty'               => $totalQty,
            'subtotal'               => $subtotal,
            'deliveryDiscount'       => $deliveryDiscount,
            'total'                  => max(0.0, $subtotal - $deliveryDiscount),
            'addresses'              => $this->addresses->getByUser($userId),
            'errors'                 => $errors,
            'post'                   => $post,
            'removedItems'           => $removed,
            'deliveryDelay'          => $lang === 'en' ? self::DELIVERY_DELAY_EN : self::DELIVERY_DELAY,
            'csrfToken'              => $_SESSION['csrf'] ?? '',
            'pricingRule'            => $pricingRule,
            'isNewsletterSubscribed' => $isNewsletterSubscribed,
            'submitToken'            => $submitToken,
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

        // Valider le token de soumission (anti double-submit)
        $submitToken = $this->request->post('submit_token', '');
        if (!isset($_SESSION['submit_token']) || !hash_equals($_SESSION['submit_token'], $submitToken)) {
            $_SESSION['flash']['checkout_errors']['submit'] = __('error.csrf');
            Response::redirect($back);
        }
        unset($_SESSION['submit_token']);

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
            Response::redirect("/{$lang}/panier");
        }

        // Résoudre l'adresse de livraison (toujours requise — saisie en premier dans le formulaire)
        $deliveryAddressId = $this->resolveAddress($userId, 'delivery', 'del_', $back);

        // Valider que le code postal de livraison est en France métropolitaine
        $deliveryAddr = $this->addresses->findByIdForUser($deliveryAddressId, $userId);
        $deliveryZip  = (string) ($deliveryAddr !== null ? ($deliveryAddr['zip_code'] ?? '') : '');
        if (!$this->isMainlandFranceZip($deliveryZip)) {
            $_SESSION['flash']['checkout_errors']['delivery'] = __('checkout.error_zip_mainland');
            $_SESSION['flash']['checkout_post'] = $_POST;
            Response::redirect($back);
        }

        // Résoudre l'adresse de facturation : même que livraison si case cochée
        if ($this->request->post('same_address', '')) {
            $billingAddressId = $deliveryAddressId;
        } else {
            $billingAddressId = $this->resolveAddress($userId, 'billing', '', $back);
        }

        // Calculer les totaux (items et totalQty déjà calculés avant la vérification multiple de 12)
        $subtotal         = (float) array_sum(array_map(fn(array $i): float => (float) $i['price'] * (int) $i['qty'], $items));
        $deliveryDiscount = $this->pricing->computeDeliveryDiscount($totalQty);
        $total            = round(max(0.0, $subtotal - $deliveryDiscount), 2);

        // Charger le compte (nécessaire pour newsletter + emails)
        $account     = (new AccountModel())->findById($userId);
        $clientEmail = (string) ($account !== false ? ($account['email'] ?? '') : '');
        $clientName  = trim(($account !== false ? ($account['firstname'] ?? '') : '') . ' ' . ($account !== false ? ($account['lastname'] ?? '') : '')); // phpcs:ignore Generic.Files.LineLength

        // Newsletter opt-in
        if ($this->request->post('newsletter', '')) {
            $this->notifyNewsletterOptIn($userId, $lang, $clientEmail, $clientName);
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
            $this->cgvVersion
        );

        // Vider le panier
        $this->carts->clear($userId);
        setcookie('cb-cart', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
        if ($clientEmail !== '') {
            $this->dispatchOrderEmails($clientEmail, $clientName, $reference, $paymentMethod, $items, $total, $lang); // phpcs:ignore Generic.Files.LineLength
        }

        // Stocker pour la page de confirmation
        $_SESSION['last_order_ref']        = $reference;
        $_SESSION['last_order_payment']    = $paymentMethod;
        $_SESSION['last_order_newsletter'] = (bool) $this->request->post('newsletter', '');

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

        $newsletterOptIn = (bool) ($_SESSION['last_order_newsletter'] ?? false);
        unset($_SESSION['last_order_ref'], $_SESSION['last_order_payment'], $_SESSION['last_order_newsletter']);

        $items = json_decode((string) ($order['content'] ?? '[]'), true);
        $items = is_array($items) ? $items : [];

        $this->view('order/confirmation', [
            'lang'            => $lang,
            'order'           => $order,
            'items'           => $items,
            'newsletterOptIn' => $newsletterOptIn,
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
            $item['price']            = $wine ? (float) $wine['price'] : 0.0;
            $existingName             = (string) ($item['name'] ?? '');
            $fallbackName             = $wine ? (string) $wine['label_name'] : '';
            $item['name']             = $existingName !== '' ? $existingName : $fallbackName;
            $item['label_name']       = $item['name'];
            $item['is_cuvee_speciale'] = $wine ? (bool) ($wine['is_cuvee_speciale'] ?? false) : false;
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
     * Vérifie qu'un code postal correspond à la France métropolitaine.
     *
     * Exclut la Corse (20xxx) et les DOM-TOM (97xxx–98xxx).
     * Retourne true si le zip est vide ou non numérique (pas bloquant — la validation
     * des champs requis est faite en amont dans resolveAddress).
     *
     * @param string $zip Code postal à vérifier
     * @return bool
     */
    private function isMainlandFranceZip(string $zip): bool
    {
        if (!preg_match('/^\d{5}$/', $zip)) {
            return true; // Format invalide : laissé passer, validation champs requis faite en amont
        }
        $dept = (int) substr($zip, 0, 2);
        return $dept !== 20 && $dept < 97;
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

    /**
     * Inscrit l'utilisateur à la newsletter et envoie l'email de bienvenue.
     *
     * L'email est non bloquant : une exception lors de l'envoi est ignorée silencieusement.
     *
     * @param int    $userId     Identifiant de l'utilisateur
     * @param string $lang       Langue du client ('fr' ou 'en')
     * @param string $email      Adresse email du client
     * @param string $name       Nom complet du client
     * @return void
     */
    private function notifyNewsletterOptIn(int $userId, string $lang, string $email, string $name): void
    {
        (new AccountModel())->updateNewsletter($userId, true);
        if ($email !== '') {
            try {
                (new \Service\MailService())->sendNewsletterWelcome($email, $name, $lang);
            } catch (\Throwable $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
                // Mail non bloquant
            }
        }
    }

    /**
     * Envoie les emails de confirmation de commande au client et au propriétaire.
     *
     * Les envois sont non bloquants : toute exception est ignorée silencieusement.
     *
     * @param string               $email         Adresse email du client
     * @param string               $name          Nom complet du client
     * @param string               $reference     Référence de la commande
     * @param string               $method        Méthode de paiement (card, virement, cheque)
     * @param array<int, array<string, mixed>> $items Articles de la commande [{name, qty, price}]
     * @param float                $total         Total TTC en euros
     * @param string               $lang          Langue du client ('fr' ou 'en')
     * @return void
     */
    private function dispatchOrderEmails(
        string $email,
        string $name,
        string $reference,
        string $method,
        array $items,
        float $total,
        string $lang
    ): void {
        try {
            $mailer = new \Service\MailService();
            $mailer->sendOrderConfirmationToClient($email, $name, $reference, $method, $items, $total, $lang); // phpcs:ignore Generic.Files.LineLength
            $mailer->sendOrderConfirmationToOwner($email, $name, $reference, $method, $items, $total);
        } catch (\Throwable $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
            // Mail non bloquant — la commande est créée même en cas d'échec d'envoi
        }
    }
}
