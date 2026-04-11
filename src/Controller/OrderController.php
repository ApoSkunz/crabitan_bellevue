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
use Model\LegalDocumentModel;
use Model\WineModel;
use Service\StockNotificationService;

/**
 * Contrôleur de la page de commande (checkout).
 *
 * GET  /{lang}/commande                   → affiche le formulaire de commande
 * POST /{lang}/commande/paiement          → valide et crée la commande (virement/chèque)
 *                                           ou génère le formulaire CA (carte)
 * GET  /{lang}/commande/paiement-ca/ok     → retour CA paiement accepté
 * GET  /{lang}/commande/paiement-ca/annule → retour CA paiement annulé
 * GET  /{lang}/commande/paiement-ca/refuse → retour CA paiement refusé
 * GET  /{lang}/commande/confirmation       → affiche la confirmation après paiement
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

        // Lire et effacer les flash AVANT tout redirect, pour éviter qu'un flash
        // stale (ex: checkout.payment_error posé par paymentReturnOk) réapparaisse
        // lors d'une visite ultérieure si le panier était vide au premier passage.
        $errors = $_SESSION['flash']['checkout_errors'] ?? [];
        $post   = $_SESSION['flash']['checkout_post']   ?? [];
        unset($_SESSION['flash']['checkout_errors'], $_SESSION['flash']['checkout_post']);

        // Guard majorité — vérifié en premier pour que la modale s'affiche
        // même si le panier est vide (l'utilisateur doit déclarer sa majorité
        // avant de pouvoir accéder à la zone checkout)
        $accountModel = new AccountModel();
        $account      = $accountModel->findById($userId);
        $isNewsletterSubscribed = (bool) ($account !== false ? ($account['newsletter'] ?? false) : false);

        if ($account !== false && !$accountModel->hasDeclaredMajority($userId)) {
            $_SESSION['majority_pending'] = true;
            Response::redirect("/{$lang}/panier");
        }

        // Nettoyer les articles indisponibles ou hors stock
        $removed = $this->carts->removeUnavailableItems(
            $userId,
            fn(int $id): ?array => $this->wines->getById($id)
        );

        // Si des articles ont été retirés, informer l'utilisateur et le renvoyer au panier
        if (!empty($removed)) {
            $_SESSION['flash']['cart_wines_removed'] = count($removed);
            Response::redirect("/{$lang}/panier");
        }

        $row = $this->carts->findByUserId($userId);
        if ($row === false || $this->carts->getContent($row) === []) {
            Response::redirect("/{$lang}/panier");
        }

        $items    = $this->enrichItems($this->carts->getContent($row));
        $totalQty = (int) array_sum(array_column($items, 'qty'));
        $subtotal = (float) array_sum(array_map(fn(array $i): float => (float) $i['price'] * (int) $i['qty'], $items));

        $pricingRule      = $this->pricing->findForQuantity($totalQty);
        $deliveryDiscount = $this->pricing->computeDeliveryDiscount($totalQty);

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
     * Valide le formulaire de commande.
     *
     * - Pour `card` : génère une référence, stocke un snapshot en session et
     *   retourne la vue formulaire auto-submit vers Crédit Agricole (CA).
     * - Pour `virement` et `cheque` : crée la commande, vide le panier,
     *   envoie les emails et redirige vers la confirmation.
     *
     * En cas d'erreur, redirige vers le checkout avec les messages flash.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function payment(array $params): void // NOSONAR — php:S3776 : complexité flux commande multi-modes (card/virement/chèque)
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $this->resolveLang($params);
        $back    = "/{$lang}/commande";

        // Guard backend majorité — manipulation DOM côté client ne suffit pas
        $accountModel = new AccountModel();
        if (!$accountModel->hasDeclaredMajority($userId)) {
            $_SESSION['majority_pending'] = true;
            Response::redirect($back);
        }

        $this->validateCsrfAndSubmitToken($back);

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
        $subtotal         = (float) array_sum(
            array_map(fn(array $i): float => (float) $i['price'] * (int) $i['qty'], $items)
        );
        $deliveryDiscount = $this->pricing->computeDeliveryDiscount($totalQty);
        $total            = round(max(0.0, $subtotal - $deliveryDiscount), 2);
        $cgvDoc           = (new LegalDocumentModel())->findBySlug('cgv');
        $cgvVersion       = (string) ($cgvDoc['version'] ?? '1.0');

        // ---- Chemin carte bancaire (CA) ----
        if ($paymentMethod === 'card') {
            $this->checkPaymentRateLimit($userId, $lang);

            // Charger le compte (email, nom, adresse facturation pour PBX_BILLING)
            $account     = (new AccountModel())->findById($userId);
            $clientEmail = (string) ($account !== false ? ($account['email'] ?? '') : '');
            $clientName  = trim(
                ($account !== false ? ($account['firstname'] ?? '') : '')
                . ' '
                . ($account !== false ? ($account['lastname'] ?? '') : '')
            );

            // Générer la référence avant envoi à CA
            $reference = 'WEB-CB-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');

            // Récupérer l'adresse de facturation pour PBX_BILLING
            $billingAddr = $this->addresses->findByIdForUser($billingAddressId, $userId);

            $snapshot = [
                'reference'           => $reference,
                'user_id'             => $userId,
                'items'               => $items,
                'total'               => $total,
                'delivery_discount'   => $deliveryDiscount,
                'billing_address_id'  => $billingAddressId,
                'delivery_address_id' => $deliveryAddressId,
                'cgv_version'         => $cgvVersion,
                'lang'                => $lang,
                'newsletter'          => (bool) $this->request->post('newsletter', ''),
                'client_email'        => $clientEmail,
                'client_name'         => $clientName,
            ];

            // Persister le snapshot en BDD — l'IPN CA (server-to-server) n'a pas
            // accès à la session PHP du navigateur, il lira depuis payment_intents.
            (new \Model\PaymentIntentModel())->save($reference, $userId, $snapshot);

            // Conserver aussi en session pour l'UX (paymentReturnOk)
            $_SESSION['ca_payment'] = $snapshot;

            // Construire les champs PBX via PaymentService
            $paymentService = new \Service\PaymentService();
            $pbx = $paymentService->buildPbxFields(
                $reference,
                (int) round($total * 100),
                $clientEmail,
                (string) ($billingAddr !== null ? ($billingAddr['firstname'] ?? '') : ($account['firstname'] ?? '')),
                (string) ($billingAddr !== null ? ($billingAddr['lastname']  ?? '') : ($account['lastname']  ?? '')),
                (string) ($billingAddr !== null ? ($billingAddr['street']    ?? '') : ''),
                (string) ($billingAddr !== null ? ($billingAddr['zip_code']  ?? '') : ''),
                (string) ($billingAddr !== null ? ($billingAddr['city']      ?? '') : ''),
                $totalQty,
                $lang
            );

            // Rendre la vue formulaire auto-submit (pas de redirection)
            $this->view('order/payment-form', [
                'lang'   => $lang,
                'url'    => $pbx['url'],
                'fields' => $pbx['fields'],
            ]);
            return;
        }

        // ---- Chemin virement / chèque ----

        // Charger le compte (nécessaire pour newsletter + emails)
        $account     = (new AccountModel())->findById($userId);
        $clientEmail = (string) ($account !== false ? ($account['email'] ?? '') : '');
        $clientName  = trim(
            ($account !== false ? ($account['firstname'] ?? '') : '')
            . ' '
            . ($account !== false ? ($account['lastname'] ?? '') : '')
        ); // phpcs:ignore Generic.Files.LineLength

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
            $cgvVersion
        );

        // Vider le panier + décrémenter le stock
        $this->carts->clear($userId);
        setcookie('cb-cart', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
        foreach ($items as $item) {
            $wineId = (int) ($item['wine_id'] ?? 0);
            $qty    = (int) ($item['qty']     ?? 0);
            if ($wineId > 0 && $qty > 0) {
                $this->postOrderStockActions($wineId, $qty);
            }
        }
        if ($clientEmail !== '') {
            $this->dispatchOrderEmails(
                $clientEmail,
                $clientName,
                $reference,
                $paymentMethod,
                $items,
                $total,
                $lang,
                round($deliveryDiscount, 2)
            );
        }

        // Stocker pour la page de confirmation
        $_SESSION['last_order_ref']        = $reference;
        $_SESSION['last_order_payment']    = $paymentMethod;
        $_SESSION['last_order_newsletter'] = (bool) $this->request->post('newsletter', '');

        Response::redirect("/{$lang}/commande/confirmation");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/commande/paiement-ca/ok
    // ----------------------------------------------------------------

    /**
     * Gère le retour CA après un paiement carte accepté.
     *
     * Lit la référence transmise par CA via GET (?Ref=…).
     * Si la commande est déjà créée en BDD (IPN arrivé), stocke la référence
     * en session et redirige vers la confirmation.
     * Si l'IPN n'est pas encore arrivé, redirige vers les commandes avec un
     * message flash de succès en attente.
     * Nettoie toujours le snapshot de session.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function paymentReturnOk(array $params): void // NOSONAR — php:S3776 : complexité fallback IPN (vérif RSA, idempotence, createFromIpn)
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $this->resolveLang($params);

        // Sanitisation de $ref avant tout usage (log, BDD) — S5145
        $ref    = preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['Ref'] ?? '');
        $erreur = $_GET['Erreur'] ?? '';

        unset($_SESSION['ca_payment']);

        // Vérifier si la commande existe déjà (IPN déjà traité)
        $order = ($ref !== '') ? $this->orders->findByReference($ref, $userId) : null;

        if ($order === null && $ref !== '' && $erreur === '00000') {
            // Fallback : IPN absent ou en retard — créer la commande depuis payment_intents.
            // CA inclut Sign:K dans le QUERY_STRING des redirects navigateur (même mécanisme RSA que l'IPN).
            // Vérification obligatoire avant création de commande pour empêcher la forge de l'URL de retour.
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            if (!(new \Service\PaymentService())->verifyIpnSignature($qs)) {
                \Core\ErrorLogger::write([
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level'     => 'WARNING',
                    'message'   => '[RETURN_OK] Invalid RSA signature on return URL — ref=' . $ref,
                    'file'      => __FILE__,
                    'line'      => __LINE__,
                    'trace'     => '',
                    'url'       => $_SERVER['REQUEST_URI'] ?? '',
                    'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
                ]);
                $_SESSION['flash']['checkout_errors']['payment'] = __('checkout.payment_error');
                Response::redirect("/{$lang}/commande");
            }

            $snapshot = (new \Model\PaymentIntentModel())->findByReference($ref);

            if ($snapshot !== null && (int) $snapshot['user_id'] === $userId) {
                $numappel = $_GET['Appel'] ?? '';
                $numtrans = $_GET['Trans'] ?? '';

                // Idempotence : re-vérifier avant création (race condition IPN/return)
                if ($this->orders->findByReferenceOnly($ref) === null) {
                    $this->orders->createFromIpn(
                        $userId,
                        (array)  $snapshot['items'],
                        (float)  $snapshot['total'],
                        (float)  $snapshot['delivery_discount'],
                        (int)    $snapshot['billing_address_id'],
                        (int)    $snapshot['delivery_address_id'],
                        (string) $snapshot['cgv_version'],
                        $ref,
                        $numappel,
                        $numtrans
                    );

                    (new \Model\PaymentIntentModel())->delete($ref);
                    (new \Model\CartModel())->clear($userId);

                    foreach ((array) $snapshot['items'] as $item) {
                        $wineId = (int) ($item['wine_id'] ?? 0);
                        $qty    = (int) ($item['qty']     ?? 0);
                        if ($wineId > 0 && $qty > 0) {
                            $this->postOrderStockActions($wineId, $qty);
                        }
                    }

                    $this->generateAndStoreInvoice($ref);
                    try {
                        $discount = (float) ($snapshot['delivery_discount'] ?? 0.0);
                        $mail     = new \Service\MailService();
                        $mail->sendOrderConfirmationToClient(
                            (string) ($snapshot['client_email'] ?? ''),
                            (string) ($snapshot['client_name']  ?? ''),
                            $ref,
                            'card',
                            (array) $snapshot['items'],
                            (float) $snapshot['total'],
                            $lang,
                            $discount
                        );
                        $mail->sendOrderConfirmationToOwner(
                            (string) ($snapshot['client_email'] ?? ''),
                            (string) ($snapshot['client_name']  ?? ''),
                            $ref,
                            'card',
                            (array) $snapshot['items'],
                            (float) $snapshot['total'],
                            $discount
                        );
                    } catch (\Throwable $mailError) {
                        \Core\ErrorLogger::warning($mailError, '[RETURN_OK]');
                    }
                }

                $order = $this->orders->findByReference($ref, $userId);
            }
        }

        if ($order === null) {
            \Core\ErrorLogger::write([
                'timestamp' => date('Y-m-d H:i:s'),
                'level'     => 'WARNING',
                'message'   => '[RETURN_OK] Order not found and no intent — ref=' . $ref,
                'file'      => __FILE__,
                'line'      => __LINE__,
                'trace'     => '',
                'url'       => $_SERVER['REQUEST_URI'] ?? '',
                'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
            ]);
            $_SESSION['flash']['checkout_errors']['payment'] = __('checkout.payment_error');
            Response::redirect("/{$lang}/commande");
        }

        $_SESSION['last_order_ref']     = $ref;
        $_SESSION['last_order_payment'] = 'card';
        Response::redirect("/{$lang}/commande/confirmation");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/commande/paiement-ca/annule
    // ----------------------------------------------------------------

    /**
     * Gère le retour CA après une annulation de paiement carte par le client.
     *
     * Stocke un message flash d'annulation, nettoie le snapshot de session
     * et redirige vers le checkout.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function paymentReturnCancel(array $params): void
    {
        $this->requireCustomer();
        $lang = $this->resolveLang($params);

        $ref = $_GET['Ref'] ?? '';
        if ($ref !== '') {
            (new \Model\PaymentIntentModel())->delete($ref);
        }

        $_SESSION['flash']['checkout_errors']['payment'] = __('checkout.payment_cancelled');
        unset($_SESSION['ca_payment']);
        Response::redirect("/{$lang}/commande");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/commande/paiement-ca/refuse
    // ----------------------------------------------------------------

    /**
     * Gère le retour CA après un refus de paiement carte (fonds insuffisants, etc.).
     *
     * Stocke un message flash de refus, nettoie le snapshot de session
     * et redirige vers le checkout.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function paymentReturnRefuse(array $params): void
    {
        $this->requireCustomer();
        $lang = $this->resolveLang($params);

        $ref = $_GET['Ref'] ?? '';
        if ($ref !== '') {
            (new \Model\PaymentIntentModel())->delete($ref);
        }

        $_SESSION['flash']['checkout_errors']['payment'] = __('checkout.payment_refused');
        unset($_SESSION['ca_payment']);
        Response::redirect("/{$lang}/commande");
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
     * Vérifie et incrémente le compteur de tentatives de paiement carte par utilisateur.
     *
     * Limite : 10 tentatives par fenêtre glissante de 3 600 secondes.
     * Si la limite est atteinte, stocke un message flash d'erreur et redirige
     * vers le checkout. Réinitialise le compteur si la fenêtre est expirée.
     *
     * @param int    $userId Identifiant de l'utilisateur connecté
     * @param string $lang   Langue courante, utilisée pour construire l'URL de retour
     * @return void
     */
    private function checkPaymentRateLimit(int $userId, string $lang): void
    {
        $now         = time();
        $windowSecs  = 3600;
        $maxAttempts = 10;
        $back        = "/{$lang}/commande";

        $attempts = $_SESSION['payment_attempts'][$userId] ?? ['count' => 0, 'window_start' => $now];

        if (($now - (int) $attempts['window_start']) >= $windowSecs) {
            // Fenêtre expirée : réinitialiser
            $attempts = ['count' => 0, 'window_start' => $now];
        }

        $attempts['count'] = (int) $attempts['count'] + 1;
        $_SESSION['payment_attempts'][$userId] = $attempts;

        if ($attempts['count'] > $maxAttempts) {
            $_SESSION['flash']['checkout_errors']['rate_limit'] = __('checkout.error_rate_limit');
            Response::redirect($back);
        }
    }

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
            $item['vintage']           = $wine ? (int) ($wine['vintage'] ?? 0) : 0;
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
        $payload = AuthMiddleware::handle(redirectToLogin: true);
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
     * Vérifie le token CSRF et le token de soumission unique (anti double-submit).
     *
     * Redirige vers $back avec une erreur flash si l'un des deux tokens est invalide.
     * Supprime le submit_token de session après vérification réussie.
     *
     * @param string $back URL de redirection en cas d'échec
     * @return void
     */
    private function validateCsrfAndSubmitToken(string $back): void
    {
        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['checkout_errors']['csrf'] = __('error.csrf');
            Response::redirect($back);
        }

        $submitToken = $this->request->post('submit_token', '');
        if (!isset($_SESSION['submit_token']) || !hash_equals($_SESSION['submit_token'], $submitToken)) {
            $_SESSION['flash']['checkout_errors']['submit'] = __('error.csrf');
            Response::redirect($back);
        }
        unset($_SESSION['submit_token']);
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
            } catch (\Throwable $e) {
                \Core\ErrorLogger::warning($e, '[ORDER]');
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
    /**
     * Génère la facture PDF, la stocke sur disque et met à jour la commande en BDD.
     * Non bloquant : toute erreur est journalisée sans interrompre le flux.
     *
     * @param string $reference Référence de la commande
     */
    private function generateAndStoreInvoice(string $reference): void
    {
        try {
            $order = $this->orders->findByReferenceWithAddresses($reference);
            if ($order === null) {
                return;
            }
            $invoiceNumber = sprintf('WEB-INVOICE-%s-%04d', date('Y'), (int) $order['id']);
            $invoiceDir    = ROOT_PATH . '/storage/invoices';
            if (!is_dir($invoiceDir)) {
                mkdir($invoiceDir, 0755, true);
            }
            $invoicePath = 'storage/invoices/' . $invoiceNumber . '.pdf';
            file_put_contents(
                ROOT_PATH . '/' . $invoicePath, // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
                (new \Service\InvoiceService())->generate($order, $invoiceNumber)
            );
            $this->orders->updateInvoiceData((int) $order['id'], $invoiceNumber, $invoicePath);

            // Envoi facture par email — non bloquant
            $clientEmail = (string) ($order['email']     ?? '');
            $firstname   = (string) ($order['firstname'] ?? '');
            $lastname    = (string) ($order['lastname']  ?? '');
            $clientName  = trim($firstname . ' ' . $lastname) ?: $clientEmail;
            $clientLang  = (string) ($order['lang']      ?? 'fr');
            if ($clientEmail !== '') {
                try {
                    (new \Service\MailService())->sendInvoiceToClient(
                        $clientEmail,
                        $clientName,
                        $reference,
                        $invoiceNumber,
                        realpath(ROOT_PATH . '/' . $invoicePath) ?: ROOT_PATH . '/' . $invoicePath,
                        $clientLang
                    );
                } catch (\Throwable $mailErr) {
                    \Core\ErrorLogger::warning($mailErr, '[ORDER]');
                }
            }
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[ORDER]');
        }
    }

    /**
     * Envoie les emails de confirmation de commande au client et au propriétaire.
     *
     * Les envois sont non bloquants : toute exception est ignorée silencieusement.
     *
     * @param string               $email            Adresse email du client
     * @param string               $name             Nom complet du client
     * @param string               $reference        Référence de la commande
     * @param string               $method           Méthode de paiement (card, virement, cheque)
     * @param array<int, array<string, mixed>> $items Articles de la commande [{name, qty, price}]
     * @param float                $total            Total TTC en euros
     * @param string               $lang             Langue du client ('fr' ou 'en')
     * @param float                $shippingDiscount Remise livraison
     * @return void
     */
    /**
     * Décrémente le stock après commande, notifie si seuil franchi et plafonne les paniers.
     * Si le stock atteint 0, marque le vin indisponible et purge tous les paniers.
     *
     * @param int $wineId Identifiant du vin
     * @param int $qty    Quantité commandée
     * @return void
     */
    private function postOrderStockActions(int $wineId, int $qty): void
    {
        $wine = $this->wines->getById($wineId);
        $this->wines->decrementStock($wineId, $qty);

        if ($wine === null) {
            return;
        }

        $newStock = max(0, (int) ($wine['stock_quantity'] ?? 0) - $qty);

        try {
            (new StockNotificationService())->notifyIfThresholdCrossed($wine, $newStock);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[ORDER]');
        }

        (new CartModel())->capWineQtyAcrossAllCarts($wineId, $newStock);

        // Stock à 0 → vin indisponible + purge paniers
        if ($newStock === 0) {
            $this->wines->setUnavailable($wineId);
            (new CartModel())->purgeWineFromAllCarts($wineId);
        }
    }

    private function dispatchOrderEmails( // NOSONAR — php:S107 : 8 paramètres requis pour envoyer les emails de confirmation commande
        string $email,
        string $name,
        string $reference,
        string $method,
        array $items,
        float $total,
        string $lang,
        float $shippingDiscount = 0.0
    ): void {
        try {
            $mailer = new \Service\MailService();
            $mailer->sendOrderConfirmationToClient(
                $email,
                $name,
                $reference,
                $method,
                $items,
                $total,
                $lang,
                $shippingDiscount
            );
            $mailer->sendOrderConfirmationToOwner(
                $email,
                $name,
                $reference,
                $method,
                $items,
                $total,
                $shippingDiscount
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[ORDER]');
        }
    }
}
