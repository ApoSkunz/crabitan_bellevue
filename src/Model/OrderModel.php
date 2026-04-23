<?php

declare(strict_types=1);

namespace Model;

use Core\Model;
use Service\EncryptionService;

class OrderModel extends Model // NOSONAR php:S1448 — regroupement intentionnel ; découpage prévu à l'audit génie logiciel
{
    protected string $table = 'orders';

    private EncryptionService $encryption;

    /**
     * @param EncryptionService|null $encryption Service de chiffrement (null = construit depuis l'environnement)
     */
    public function __construct(?EncryptionService $encryption = null)
    {
        parent::__construct();
        $this->encryption = $encryption ?? EncryptionService::fromEnv();
    }

    private const VALID_STATUSES = [
        'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'return_requested', 'refund_refused', 'refund_pending',
        'ready_for_pickup', 'collected', // Click & Collect
    ];

    /** Fenêtre légale de rétractation après livraison (art. L221-18 Code conso). */
    public const CANCEL_WINDOW_DAYS = 14;

    public const VALID_PAYMENT_METHODS = [
        'card', 'virement', 'cheque',
    ];

    /**
     * Crée une commande et retourne la référence générée.
     *
     * @param int                              $userId            Identifiant de l'utilisateur
     * @param array<int, array<string, mixed>> $items             Snapshot des articles du panier
     * @param float                            $price             Total TTC
     * @param string                           $paymentMethod     Méthode de paiement ('card', 'virement', 'cheque')
     * @param float                            $shippingDiscount  Remise livraison appliquée
     * @param int                              $idBillingAddress  ID adresse de facturation
     * @param int|null                         $idDeliveryAddress ID adresse de livraison (null = même que facturation)
     * @param string                           $cgvVersion        Version des CGV acceptées
     * @param string                           $deliveryMethod    Mode de livraison : 'shipping' ou 'pickup' (Click & Collect)
     * @return string Référence de commande générée
     */
    public function create( // NOSONAR php:S107 — paramètres atomiques requis par le schéma BDD ; DTO prévu à l'audit architecture
        int $userId,
        array $items,
        float $price,
        string $paymentMethod,
        float $shippingDiscount,
        int $idBillingAddress,
        ?int $idDeliveryAddress,
        string $cgvVersion,
        string $deliveryMethod = 'shipping'
    ): string {
        if (!in_array($paymentMethod, self::VALID_PAYMENT_METHODS, true)) {
            $paymentMethod = 'card';
        }

        $typeCode  = match ($paymentMethod) {
            'virement' => 'VB',
            'cheque'   => 'CHQ',
            default    => 'CB',
        };
        $reference = 'WEB-' . $typeCode . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
        $contentJson = json_encode($items, JSON_UNESCAPED_UNICODE);

        $snapshots         = new OrderAddressSnapshotModel();
        $billingAddr       = $this->fetchAddressById($idBillingAddress);
        $billingSnapshotId = $snapshots->createFromAddress($billingAddr ?: [], $idBillingAddress);

        $deliverySnapshotId = null;
        if ($idDeliveryAddress !== null) {
            $deliveryAddr       = $this->fetchAddressById($idDeliveryAddress);
            $deliverySnapshotId = $snapshots->createFromAddress($deliveryAddr ?: [], $idDeliveryAddress);
        }

        $deliveryMethod = in_array($deliveryMethod, ['shipping', 'pickup'], true) ? $deliveryMethod : 'shipping';

        $this->db->insert(
            "INSERT INTO {$this->table}
             (user_id, order_reference, content, price, payment_method, shipping_discount,
              id_billing_snapshot, id_delivery_snapshot, cgv_version, delivery_method, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')", // phpcs:ignore Generic.Files.LineLength
            [
                $userId,
                $reference,
                $contentJson,
                $price,
                $paymentMethod,
                $shippingDiscount,
                $billingSnapshotId,
                $deliverySnapshotId,
                $cgvVersion,
                $deliveryMethod,
            ]
        );

        return $reference;
    }

    /**
     * Retrouve une commande par sa référence pour un utilisateur donné.
     *
     * @param string $reference Référence de commande
     * @param int    $userId    Identifiant de l'utilisateur
     * @return array<string, mixed>|null
     */
    public function findByReference(string $reference, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.id, o.user_id, o.order_reference, o.content, o.price, o.payment_method,
                    o.shipping_discount, o.id_billing_snapshot, o.id_delivery_snapshot, o.status,
                    o.cgv_version, o.invoice_number, o.path_invoice,
                    o.ordered_at, o.delivered_at, o.rating_email_sent_at, o.updated_at,
                    b.civility  AS bill_civility,  b.firstname AS bill_firstname, b.lastname AS bill_lastname,
                    b.street    AS bill_street,    b.city      AS bill_city,
                    b.zip       AS bill_zip,       b.country   AS bill_country,  b.phone AS bill_phone,
                    d.civility  AS del_civility,   d.firstname AS del_firstname,  d.lastname AS del_lastname,
                    d.street    AS del_street,     d.city      AS del_city,
                    d.zip       AS del_zip,        d.country   AS del_country
             FROM {$this->table} o
             LEFT JOIN order_address_snapshots b ON b.id = o.id_billing_snapshot
             LEFT JOIN order_address_snapshots d ON d.id = o.id_delivery_snapshot
             WHERE o.order_reference = ? AND o.user_id = ?",
            [$reference, $userId]
        );
        if (is_array($row)) {
            $row = $this->decryptSnapshotFields($row, ['bill_street', 'bill_phone', 'del_street']);
        }
        return $row ?: null;
    }

    /**
     * Crée une commande depuis la confirmation IPN CA avec statut 'paid'.
     *
     * Appelée uniquement par le handler IPN après vérification de la signature.
     * Si une collision sur order_reference survient (UNIQUE KEY), génère une nouvelle
     * référence et retente (jusqu'à 3 essais).
     *
     * @param int                              $userId            Identifiant de l'utilisateur
     * @param array<int, array<string, mixed>> $items             Articles du panier
     * @param float                            $total             Total TTC en euros
     * @param float                            $deliveryDiscount  Remise livraison
     * @param int                              $billingAddressId  Identifiant adresse facturation
     * @param int                              $deliveryAddressId Identifiant adresse livraison
     * @param string                           $cgvVersion        Version CGV acceptée
     * @param string                           $reference         Référence pré-générée (WEB-CB-XXXXX-YYYY)
     * @param string                           $numappel          NUMAPPEL CA (lettre T PBX_RETOUR)
     * @param string                           $numtrans          NUMTRANS CA (lettre S PBX_RETOUR)
     * @return string Référence de la commande créée
     * @throws \PDOException Si la collision de référence persiste après 3 tentatives
     */
    public function createFromIpn( // NOSONAR php:S107 — paramètres atomiques requis par le schéma BDD ; DTO prévu à l'audit architecture
        int $userId,
        array $items,
        float $total,
        float $deliveryDiscount,
        int $billingAddressId,
        int $deliveryAddressId,
        string $cgvVersion,
        string $reference,
        string $numappel,
        string $numtrans,
        string $deliveryMethod = 'shipping'
    ): string {
        $content        = json_encode($items, JSON_UNESCAPED_UNICODE);
        $deliveryMethod = in_array($deliveryMethod, ['shipping', 'pickup'], true) ? $deliveryMethod : 'shipping';
        $typeCode       = 'CB';
        $maxTries       = 3;

        $snapshots          = new OrderAddressSnapshotModel();
        $billingAddr        = $this->fetchAddressById($billingAddressId);
        $billingSnapshotId  = $snapshots->createFromAddress($billingAddr ?: [], $billingAddressId);
        $deliveryAddr       = $this->fetchAddressById($deliveryAddressId);
        $deliverySnapshotId = $snapshots->createFromAddress($deliveryAddr ?: [], $deliveryAddressId);

        for ($attempt = 1; $attempt <= $maxTries; $attempt++) {
            try {
                $this->db->insert(
                    "INSERT INTO {$this->table}
                     (user_id, order_reference, content, price, payment_method, shipping_discount,
                      id_billing_snapshot, id_delivery_snapshot, status, cgv_version, delivery_method, ca_numappel, ca_numtrans)
                     VALUES (?, ?, ?, ?, 'card', ?, ?, ?, 'paid', ?, ?, ?, ?)", // phpcs:ignore Generic.Files.LineLength
                    [
                        $userId,
                        $reference,
                        $content,
                        $total,
                        $deliveryDiscount,
                        $billingSnapshotId,
                        $deliverySnapshotId,
                        $cgvVersion,
                        $deliveryMethod,
                        $numappel,
                        $numtrans,
                    ]
                );
                return $reference;
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000' || $attempt === $maxTries) {
                    throw $e;
                }
                $reference = 'WEB-' . $typeCode . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
            }
        }

        return $reference;
    }

    /**
     * Recherche une commande par sa référence uniquement (sans vérification userId).
     *
     * Utilisé par le handler IPN pour vérifier si une commande existe déjà
     * (idempotence sur double notification).
     *
     * @param string $reference Référence de la commande
     * @return array<string, mixed>|null null si non trouvée
     */
    public function findByReferenceOnly(string $reference): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, user_id, order_reference, content, price, payment_method, shipping_discount,
                    id_billing_snapshot, id_delivery_snapshot, status, cgv_version,
                    ca_numappel, ca_numtrans, invoice_number, path_invoice,
                    ordered_at, delivered_at, rating_email_sent_at, updated_at
             FROM {$this->table} WHERE order_reference = ?",
            [$reference]
        );
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(
        int $page,
        int $perPage,
        ?string $status,
        ?string $search,
        ?string $payment = null,
        bool $archived = false
    ): array {
        [$where, $params] = $this->buildAdminFilters($status, $search, $payment, $archived);
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT o.id, o.order_reference, o.status, o.price,
                    o.payment_method, o.ordered_at, o.delivery_method,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '') AS lastname
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}
             ORDER BY o.ordered_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * @param bool $archived Filtrer uniquement les commandes de plus de 10 ans (archivage L123-22 C.com.)
     */
    public function countForAdmin(
        ?string $status,
        ?string $search,
        ?string $payment = null,
        bool $archived = false
    ): int {
        [$where, $params] = $this->buildAdminFilters($status, $search, $payment, $archived);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForAdmin(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.id, o.user_id, o.order_reference, o.content, o.price, o.payment_method,
                    o.shipping_discount, o.id_billing_snapshot, o.id_delivery_snapshot, o.status,
                    o.cgv_version, o.ca_numappel, o.ca_numtrans, o.delivery_method,
                    o.invoice_number, o.path_invoice,
                    o.ordered_at, o.delivered_at, o.rating_email_sent_at, o.updated_at,
                    a.email, a.lang,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '') AS lastname,
                    b.firstname  AS bill_firstname, b.lastname  AS bill_lastname,
                    b.street     AS bill_street,    b.city      AS bill_city,
                    b.zip        AS bill_zip,       b.country   AS bill_country,
                    b.phone      AS bill_phone,
                    d.firstname  AS del_firstname,  d.lastname  AS del_lastname,
                    d.street     AS del_street,     d.city      AS del_city,
                    d.zip        AS del_zip,        d.country   AS del_country
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             LEFT JOIN order_address_snapshots b ON b.id = o.id_billing_snapshot
             LEFT JOIN order_address_snapshots d ON d.id = o.id_delivery_snapshot
             WHERE o.id = ?",
            [$id]
        );
        if (is_array($row)) {
            $row = $this->decryptSnapshotFields($row, ['bill_street', 'bill_phone', 'del_street']);
        }
        return $row ?: null;
    }

    public function updateInvoice(int $id, string $path): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET path_invoice = ? WHERE id = ?",
            [$path, $id]
        );
    }

    /**
     * Ajoute une entrée dans l'historique des factures de la commande (L441-9 C.com.).
     *
     * @param int         $orderId       Identifiant de la commande
     * @param string      $path          Chemin relatif du fichier PDF
     * @param string|null $invoiceNumber Numéro de facture (WEB-INVOICE-… ou null pour uploads manuels)
     * @param string      $source        'auto' (généré) ou 'upload' (téléversé par admin)
     */
    public function addInvoiceFile(int $orderId, string $path, ?string $invoiceNumber, string $source): void
    {
        $this->db->execute(
            "INSERT INTO order_invoice_files (order_id, path, invoice_number, source) VALUES (?, ?, ?, ?)",
            [$orderId, $path, $invoiceNumber, $source]
        );
    }

    /**
     * Retourne l'historique des factures d'une commande, du plus récent au plus ancien.
     *
     * @param  int $orderId Identifiant de la commande
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFiles(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT id, path, invoice_number, source, uploaded_at
               FROM order_invoice_files
              WHERE order_id = ?
              ORDER BY uploaded_at DESC",
            [$orderId]
        );
    }

    /**
     * Met à jour le numéro et le chemin de la facture d'une commande en une seule requête.
     *
     * @param int    $id            Identifiant de la commande
     * @param string $invoiceNumber Numéro de facture (WEB-INVOICE-YYYY-XXXX)
     * @param string $invoicePath   Chemin relatif du fichier PDF (storage/pdf/invoices/…)
     */
    public function updateInvoiceData(int $id, string $invoiceNumber, string $invoicePath): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET invoice_number = ?, path_invoice = ? WHERE id = ?",
            [$invoiceNumber, $invoicePath, $id]
        );
    }

    /**
     * Recherche une commande par référence avec les adresses, sans données de paiement CA.
     * Usage : génération de facture PDF (InvoiceService, IpnController, OrderController).
     * Les tokens CA (ca_numappel, ca_numtrans) ne sont pas nécessaires pour la facture.
     *
     * @param  string $reference Référence de la commande (WEB-CB-XXXXX-YYYY)
     * @return array<string, mixed>|null
     */
    public function findByReferenceForInvoice(string $reference): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.id, o.user_id, o.order_reference, o.content, o.price, o.payment_method,
                    o.shipping_discount, o.id_billing_snapshot, o.id_delivery_snapshot, o.status,
                    o.cgv_version, o.invoice_number, o.path_invoice,
                    o.ordered_at, o.delivered_at, o.updated_at,
                    a.email, a.lang,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '')                AS lastname,
                    b.civility  AS bill_civility,
                    b.firstname AS bill_firstname, b.lastname AS bill_lastname,
                    b.street    AS bill_street,    b.city     AS bill_city,
                    b.zip       AS bill_zip,       b.country  AS bill_country,
                    b.phone     AS bill_phone
             FROM {$this->table} o
             JOIN accounts a                    ON a.id          = o.user_id
             LEFT JOIN account_individuals ai   ON ai.account_id = a.id
             LEFT JOIN account_companies   ac   ON ac.account_id = a.id
             LEFT JOIN order_address_snapshots b ON b.id         = o.id_billing_snapshot
             WHERE o.order_reference = ?",
            [$reference]
        );
        if (is_array($row)) {
            $row = $this->decryptSnapshotFields($row, ['bill_street', 'bill_phone']);
        }
        return $row ?: null;
    }

    /**
     * Recherche une commande par référence avec les adresses et les références CA (admin/IPN uniquement).
     *
     * @param  string $reference Référence de la commande (WEB-CB-XXXXX-YYYY)
     * @return array<string, mixed>|null
     */
    public function findByReferenceWithAddresses(string $reference): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.id, o.user_id, o.order_reference, o.content, o.price, o.payment_method,
                    o.shipping_discount, o.id_billing_snapshot, o.id_delivery_snapshot, o.status,
                    o.cgv_version, o.ca_numappel, o.ca_numtrans, o.delivery_method,
                    o.invoice_number, o.path_invoice,
                    o.ordered_at, o.delivered_at, o.rating_email_sent_at, o.updated_at,
                    a.email, a.lang,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '')                AS lastname,
                    b.civility  AS bill_civility,
                    b.firstname AS bill_firstname, b.lastname AS bill_lastname,
                    b.street    AS bill_street,    b.city     AS bill_city,
                    b.zip       AS bill_zip,       b.country  AS bill_country,
                    b.phone     AS bill_phone
             FROM {$this->table} o
             JOIN accounts a                    ON a.id          = o.user_id
             LEFT JOIN account_individuals ai   ON ai.account_id = a.id
             LEFT JOIN account_companies   ac   ON ac.account_id = a.id
             LEFT JOIN order_address_snapshots b ON b.id         = o.id_billing_snapshot
             WHERE o.order_reference = ?",
            [$reference]
        );
        if (is_array($row)) {
            $row = $this->decryptSnapshotFields($row, ['bill_street', 'bill_phone']);
        }
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $orderId, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, order_reference, path_invoice, user_id
             FROM {$this->table}
             WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return;
        }
        if ($status === 'delivered') {
            $this->db->execute(
                "UPDATE {$this->table} SET status = ?, delivered_at = NOW() WHERE id = ?",
                [$status, $id]
            );
        } else {
            $this->db->execute(
                "UPDATE {$this->table} SET status = ? WHERE id = ?",
                [$status, $id]
            );
        }
    }

    /**
     * Demande de rétractation après livraison (art. L221-18 Code conso).
     * Passe le statut à `return_requested` si :
     *   - la commande appartient à l'utilisateur
     *   - le statut est `delivered`
     *   - NOW() ≤ delivered_at + CANCEL_WINDOW_DAYS jours
     *
     * @param int $orderId
     * @param int $userId
     * @return bool true si la demande a été enregistrée
     */
    public function requestReturnForUser(int $orderId, int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT status, delivered_at FROM {$this->table}
             WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );

        if (!$row || $row['status'] !== 'delivered' || $row['delivered_at'] === null) {
            return false;
        }

        $deadline = strtotime($row['delivered_at'] . ' +' . self::CANCEL_WINDOW_DAYS . ' days');
        if ($deadline === false || time() > $deadline) {
            return false;
        }

        $this->db->execute(
            "UPDATE {$this->table} SET status = 'return_requested', updated_at = NOW()
             WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        return true;
    }

    /**
     * @return array<string, int>  ex. ['pending' => 3, 'paid' => 12, ...]
     */
    public function countByStatus(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Chiffre d'affaires des N derniers jours (commandes non annulées/remboursées).
     */
    public function getRevenue(int $days = 30): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price), 0) AS total
             FROM {$this->table}
             WHERE status NOT IN ('cancelled', 'refunded')
               AND ordered_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return (float) ($row['total'] ?? 0);
    }

    /**
     * Chiffre d'affaires pour une année civile donnée (commandes non annulées/remboursées).
     */
    public function getRevenueByYear(int $year): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price), 0) AS total
             FROM {$this->table}
             WHERE status NOT IN ('cancelled', 'refunded')
               AND YEAR(ordered_at) = ?",
            [$year]
        );
        return (float) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 8): array
    {
        return $this->db->fetchAll(
            "SELECT o.id, o.order_reference, o.status, o.price, o.ordered_at,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             ORDER BY o.ordered_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(int $userId, int $page, int $perPage, ?string $period = null, ?int $year = null, ?string $status = null): array
    {
        [$where, $params] = $this->buildUserFilters($userId, $period, $year, $status);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        return $this->db->fetchAll(
            "SELECT id, order_reference, status, price, payment_method, ordered_at, path_invoice, delivery_method
             FROM {$this->table}
             {$where}
             ORDER BY ordered_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countForUser(int $userId, ?string $period = null, ?int $year = null, ?string $status = null): int
    {
        [$where, $params] = $this->buildUserFilters($userId, $period, $year, $status);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetailForUser(int $orderId, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.id, o.user_id, o.order_reference, o.content, o.price, o.payment_method,
                    o.shipping_discount, o.id_billing_snapshot, o.id_delivery_snapshot, o.status,
                    o.cgv_version, o.ca_numappel, o.ca_numtrans, o.delivery_method,
                    o.invoice_number, o.path_invoice,
                    o.ordered_at, o.delivered_at, o.rating_email_sent_at, o.updated_at,
                    b.civility  AS bill_civility,  b.firstname AS bill_firstname, b.lastname AS bill_lastname,
                    b.street    AS bill_street,    b.city      AS bill_city,
                    b.zip       AS bill_zip,       b.country   AS bill_country,  b.phone AS bill_phone,
                    d.civility  AS del_civility,   d.firstname AS del_firstname,  d.lastname AS del_lastname,
                    d.street    AS del_street,     d.city      AS del_city,
                    d.zip       AS del_zip,        d.country   AS del_country
             FROM {$this->table} o
             LEFT JOIN order_address_snapshots b ON b.id = o.id_billing_snapshot
             LEFT JOIN order_address_snapshots d ON d.id = o.id_delivery_snapshot
             WHERE o.id = ? AND o.user_id = ?",
            [$orderId, $userId]
        );
        if (is_array($row)) {
            $row = $this->decryptSnapshotFields($row, ['bill_street', 'bill_phone', 'del_street']);
        }
        return $row ?: null;
    }

    public function cancelForUser(int $orderId, int $userId): bool
    {
        $cancellable = ['pending'];
        $row = $this->db->fetchOne(
            "SELECT status FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        if (!$row || !in_array($row['status'], $cancellable, true)) {
            return false;
        }
        $this->db->execute(
            "UPDATE {$this->table} SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        return true;
    }

    public function hasActiveOrdersForUser(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table}
             WHERE user_id = ? AND status IN ('pending','paid','processing','shipped')",
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Retourne le nombre de commandes actives de l'utilisateur.
     *
     * @param  int $userId Identifiant du compte
     * @return int
     */
    public function countActiveOrdersForUser(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table}
             WHERE user_id = ? AND status IN ('pending','paid','processing','shipped')",
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Retourne les IDs d'adresses parmi celles fournies qui sont liées à une commande active.
     *
     * @param  array<int, int>  $addressIds
     * @return array<int, int>
     */
    /**
     * Les adresses ne sont plus référencées dans orders depuis l'enabler order-address-snapshot.
     * Les snapshots étant indépendants, aucune adresse ne bloque la suppression d'une commande.
     *
     * @param  array<int, int>  $addressIds
     * @return array<int, int>
     */
    public function getAddressIdsWithActiveOrders(array $addressIds): array // NOSONAR — php:S1172 : stub, adresses snapshotées
    {
        return [];
    }

    /**
     * Les adresses ne sont plus référencées dans orders depuis l'enabler order-address-snapshot.
     *
     * @param int $addressId
     * @return bool
     */
    public function hasActiveOrderForAddress(int $addressId): bool // NOSONAR — php:S1172 : stub, adresses snapshotées
    {
        return false;
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Retourne une adresse depuis la table `addresses` par son ID.
     *
     * @param int $id Identifiant de l'adresse
     * @return array<string, mixed>|false
     */
    /**
     * Déchiffre les champs street/phone d'un snapshot dans un tableau de résultat.
     *
     * @param  array<string, mixed> $row
     * @param  string[]             $fields Liste des clés à déchiffrer (ex. ['bill_street', 'bill_phone'])
     * @return array<string, mixed>
     */
    private function decryptSnapshotFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = $this->encryption->decrypt($row[$field]) ?? $row[$field];
            }
        }
        return $row;
    }

    private function fetchAddressById(int $id): array|false
    {
        $row = $this->db->fetchOne(
            "SELECT id, user_id, type, firstname, lastname, civility,
                    street, city, zip_code, country, phone, saved,
                    deleted_at, preserved_for_order, created_at, updated_at
             FROM addresses WHERE id = ?",
            [$id]
        );
        if (!is_array($row)) {
            return false;
        }
        if (isset($row['street']) && is_string($row['street'])) {
            $row['street'] = $this->encryption->decrypt($row['street']) ?? $row['street'];
        }
        if (isset($row['phone']) && is_string($row['phone'])) {
            $row['phone'] = $this->encryption->decrypt($row['phone']) ?? $row['phone'];
        }
        return $row;
    }

    // ----------------------------------------------------------------
    // Statistiques CA (admin)
    // ----------------------------------------------------------------

    /**
     * Années distinctes présentes dans les commandes (hors annulées/remboursées).
     *
     * @return array<int, int>
     */
    public function getAvailableYears(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT YEAR(ordered_at) AS yr
             FROM {$this->table}
             WHERE status NOT IN ('cancelled', 'refunded')
             ORDER BY yr DESC"
        );
        return array_map('intval', array_column($rows, 'yr'));
    }

    /**
     * CA total + nombre de commandes pour une période.
     * $from/$to au format 'Y-m-d' ; null = pas de borne.
     *
     * @return array{ca: float, count: int, avg: float}
     */
    public function getStatsForPeriod(?string $from, ?string $to): array
    {
        [$where, $params] = $this->buildStatsPeriodFilter($from, $to);
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price), 0) AS ca, COUNT(*) AS cnt
             FROM {$this->table}
             {$where}",
            $params
        );
        $ca    = (float) ($row['ca']  ?? 0);
        $count = (int)   ($row['cnt'] ?? 0);
        return [
            'ca'    => $ca,
            'count' => $count,
            'avg'   => $count > 0 ? round($ca / $count, 2) : 0.0,
        ];
    }

    /**
     * Données de graphe agrégées par jour / mois / année.
     * Retourne toujours des points consécutifs (gaps remplis à 0).
     *
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    public function getChartData(?string $from, ?string $to, string $granularity): array
    {
        [$where, $params] = $this->buildStatsPeriodFilter($from, $to);

        $groupExpr = match ($granularity) {
            'daily'  => "DATE(ordered_at)",
            'yearly' => "YEAR(ordered_at)",
            default  => "DATE_FORMAT(ordered_at, '%Y-%m')",
        };

        $rows = $this->db->fetchAll(
            "SELECT {$groupExpr} AS label,
                    COALESCE(SUM(price), 0) AS ca,
                    COUNT(*) AS count
             FROM {$this->table}
             {$where}
             GROUP BY {$groupExpr}
             ORDER BY {$groupExpr}",
            $params
        );

        // Normalisation des types
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[(string) $r['label']] = [
                'label' => (string) $r['label'],
                'ca'    => (float)  $r['ca'],
                'count' => (int)    $r['count'],
            ];
        }

        // Remplissage des gaps
        return $this->fillChartGaps($indexed, $from, $to, $granularity);
    }

    /**
     * @param  array<string, array{label: string, ca: float, count: int}>  $indexed
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    private function fillChartGaps(array $indexed, ?string $from, ?string $to, string $granularity): array
    {
        $empty = ['ca' => 0.0, 'count' => 0];

        if ($granularity === 'yearly') {
            return $this->fillChartGapsYearly($indexed, $empty);
        }

        if ($from === null || $to === null) {
            return array_values($indexed);
        }

        return $this->fillChartGapsByDate($indexed, $empty, $from, $to, $granularity);
    }

    /**
     * @param  array<string, array{label: string, ca: float, count: int}>  $indexed
     * @param  array{ca: float, count: int}                                 $empty
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    private function fillChartGapsYearly(array $indexed, array $empty): array
    {
        $years = $this->getAvailableYears();
        if ($years === []) {
            return [];
        }

        $result = [];
        $min    = min($years);
        $max    = max($years);
        for ($y = $min; $y <= $max; $y++) {
            $label    = (string) $y;
            $result[] = array_merge(['label' => $label], $indexed[$label] ?? $empty);
        }
        return $result;
    }

    /**
     * @param  array<string, array{label: string, ca: float, count: int}>  $indexed
     * @param  array{ca: float, count: int}                                 $empty
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    private function fillChartGapsByDate(array $indexed, array $empty, string $from, string $to, string $granularity): array
    {
        $result   = [];
        $start    = new \DateTime($from);
        $end      = new \DateTime($to);

        if ($granularity === 'daily') {
            $cur = clone $start;
            while ($cur <= $end) {
                $label    = $cur->format('Y-m-d');
                $result[] = array_merge(['label' => $label], $indexed[$label] ?? $empty);
                $cur->modify('+1 day');
            }
        } else {
            // monthly
            $cur      = new \DateTime($start->format('Y-m-01'));
            $endMonth = new \DateTime($end->format('Y-m-01'));
            while ($cur <= $endMonth) {
                $label    = $cur->format('Y-m');
                $result[] = array_merge(['label' => $label], $indexed[$label] ?? $empty);
                $cur->modify('+1 month');
            }
        }

        return $result;
    }

    /** @return array{string, array<int, mixed>} */
    private function buildStatsPeriodFilter(?string $from, ?string $to): array
    {
        $conds  = ["status NOT IN ('cancelled', 'refunded')"];
        $params = [];
        if ($from !== null) {
            $conds[]  = 'ordered_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $conds[]  = 'ordered_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        return ['WHERE ' . implode(' AND ', $conds), $params]; // NOSONAR php:S1192 — littéraux SQL naturels, constantes sans valeur ajoutée
    }

    /**
     * @return array<int, int>  Liste des années distinctes pour un utilisateur
     */
    public function getAvailableYearsForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT YEAR(ordered_at) AS yr FROM {$this->table}
             WHERE user_id = ? ORDER BY yr DESC",
            [$userId]
        );
        return array_column($rows, 'yr');
    }

    /** @return array{string, array<int, mixed>} */
    private function buildUserFilters(int $userId, ?string $period, ?int $year, ?string $status = null): array
    {
        $conds  = ['user_id = ?'];
        $params = [$userId];

        if ($period === '3months') {
            $conds[]  = 'ordered_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)';
        } elseif ($period === 'year' && $year !== null) {
            $conds[]  = 'YEAR(ordered_at) = ?';
            $params[] = $year;
        }

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $conds[]  = 'status = ?';
            $params[] = $status;
        }

        return ['WHERE ' . implode(' AND ', $conds), $params];
    }

    /** @return array{string, array<int, mixed>} */
    private function buildAdminFilters(
        ?string $status,
        ?string $search,
        ?string $payment = null,
        bool $archived = false
    ): array {
        $conds  = [];
        $params = [];

        if ($archived) {
            $conds[] = 'o.ordered_at < DATE_SUB(NOW(), INTERVAL 10 YEAR)';
        }

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $conds[]  = 'o.status = ?';
            $params[] = $status;
        }

        if ($payment !== null && in_array($payment, self::VALID_PAYMENT_METHODS, true)) {
            $conds[]  = 'o.payment_method = ?';
            $params[] = $payment;
        }

        if ($search !== null && $search !== '') {
            $conds[]  = '(a.email LIKE ? OR o.order_reference LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conds !== [] ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$where, $params];
    }

    /**
     * Pseudonymise les snapshots d'adresse des commandes de plus de 10 ans (Art. L123-22 C.com. + RGPD Art. 5).
     *
     * Les données personnelles (nom, prénom, rue, téléphone) sont remplacées par 'XXXXX'.
     * Les données comptables (ville, code postal, pays, montant, référence) sont conservées.
     *
     * @return int Nombre de snapshots pseudonymisés
     */
    /**
     * Retourne les fichiers de facture paginés pour la période donnée (registre L441-9 C.com.).
     * Chaque ligne = un fichier PDF (plusieurs possibles par commande).
     *
     * @param  int      $year    Année de filtrage (obligatoire)
     * @param  int|null $month   Mois (1-12) ou null
     * @param  int|null $quarter Trimestre (1-4) ou null — ignoré si $month est défini
     * @param  int      $page    Page courante
     * @param  int      $perPage Éléments par page
     * @return array<int, array<string, mixed>>
     */
    public function getInvoicesForAdmin(
        int $year,
        ?int $month,
        ?int $quarter,
        int $page,
        int $perPage,
        ?string $search = null
    ): array {
        [$where, $params] = $this->buildInvoicePeriodFilter($year, $month, $quarter);
        if ($search !== null && $search !== '') {
            $where .= ' AND (f.invoice_number LIKE ? OR o.order_reference LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll( // phpcs:ignore Generic.Files.LineLength
            "SELECT f.id, f.path, f.invoice_number, f.source, f.uploaded_at,
                    o.id AS order_id, o.order_reference, o.price, o.ordered_at, o.payment_method,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '') AS lastname,
                    ROW_NUMBER() OVER (PARTITION BY f.order_id ORDER BY f.uploaded_at DESC) AS invoice_rank
             FROM order_invoice_files f
             JOIN {$this->table} o ON o.id = f.order_id
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}
             ORDER BY f.uploaded_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Compte les fichiers de facture pour la période donnée.
     *
     * @param  int      $year
     * @param  int|null $month
     * @param  int|null $quarter
     * @return int
     */
    public function countInvoicesForAdmin(int $year, ?int $month, ?int $quarter, ?string $search = null): int
    {
        [$where, $params] = $this->buildInvoicePeriodFilter($year, $month, $quarter);
        if ($search !== null && $search !== '') {
            $where .= ' AND (f.invoice_number LIKE ? OR o.order_reference LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM order_invoice_files f
             JOIN {$this->table} o ON o.id = f.order_id
             {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne toutes les entrées order_invoice_files de la période pour l'export ZIP + CSV.
     * Identique à getInvoicesForAdmin mais sans pagination et en ordre chronologique.
     *
     * @param  int      $year
     * @param  int|null $month
     * @param  int|null $quarter
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFilesForExport(int $year, ?int $month, ?int $quarter): array
    {
        [$where, $params] = $this->buildInvoicePeriodFilter($year, $month, $quarter);

        return $this->db->fetchAll( // phpcs:ignore Generic.Files.LineLength
            "SELECT f.id, f.path, f.invoice_number, f.source, f.uploaded_at,
                    o.id AS order_id, o.order_reference, o.price, o.ordered_at, o.payment_method,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '') AS lastname
             FROM order_invoice_files f
             JOIN {$this->table} o ON o.id = f.order_id
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}
             ORDER BY f.uploaded_at ASC",
            $params
        );
    }

    /**
     * Retourne la facture courante (la plus récente) de chaque commande de l'utilisateur.
     * Une seule ligne par commande — la version précédente n'est pas exposée côté client.
     *
     * @param  int $userId Identifiant du compte
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFilesForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT ranked.id, ranked.path, ranked.invoice_number, ranked.uploaded_at,
                    ranked.order_id, ranked.order_reference, ranked.price, ranked.ordered_at
             FROM (
                 SELECT f.id, f.path, f.invoice_number, f.uploaded_at,
                        o.id AS order_id, o.order_reference, o.price, o.ordered_at,
                        ROW_NUMBER() OVER (PARTITION BY f.order_id ORDER BY f.uploaded_at DESC) AS rn
                 FROM order_invoice_files f
                 JOIN {$this->table} o ON o.id = f.order_id
                 WHERE o.user_id = ?
             ) AS ranked
             WHERE ranked.rn = 1
             ORDER BY ranked.ordered_at DESC",
            [$userId]
        );
    }

    /**
     * Retourne les années distinctes pour lesquelles au moins un fichier de facture existe.
     *
     * @return int[]
     */
    public function getAvailableYearsWithInvoices(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT YEAR(o.ordered_at) AS yr
             FROM order_invoice_files f
             JOIN {$this->table} o ON o.id = f.order_id
             ORDER BY yr DESC"
        );
        return array_map('intval', array_column($rows, 'yr'));
    }

    /**
     * Construit la clause WHERE pour le filtrage par période (utilisé par les requêtes sur order_invoice_files).
     *
     * @param  int      $year
     * @param  int|null $month
     * @param  int|null $quarter
     * @return array{string, array<int, mixed>}
     */
    private function buildInvoicePeriodFilter(int $year, ?int $month, ?int $quarter): array
    {
        $conds  = ['YEAR(o.ordered_at) = ?'];
        $params = [$year];

        if ($month !== null) {
            $conds[]  = 'MONTH(o.ordered_at) = ?';
            $params[] = $month;
        } elseif ($quarter !== null) {
            $startMonth = ($quarter - 1) * 3 + 1;
            $endMonth   = $startMonth + 2;
            $conds[]    = 'MONTH(o.ordered_at) BETWEEN ? AND ?';
            $params[]   = $startMonth;
            $params[]   = $endMonth;
        }

        return ['WHERE ' . implode(' AND ', $conds), $params];
    }

    public function pseudonymizeExpired(): int
    {
        return $this->db->execute(
            "UPDATE order_address_snapshots oas
             SET oas.firstname        = 'XXXXX',
                 oas.lastname         = 'XXXXX',
                 oas.street           = 'XXXXX',
                 oas.phone            = '',
                 oas.pseudonymized_at = NOW()
             WHERE oas.pseudonymized_at IS NULL
               AND oas.id IN (
                   SELECT snap_id FROM (
                       SELECT id_billing_snapshot  AS snap_id FROM orders
                        WHERE ordered_at < DATE_SUB(NOW(), INTERVAL 10 YEAR)
                          AND id_billing_snapshot IS NOT NULL
                       UNION
                       SELECT id_delivery_snapshot AS snap_id FROM orders
                        WHERE ordered_at < DATE_SUB(NOW(), INTERVAL 10 YEAR)
                          AND id_delivery_snapshot IS NOT NULL
                   ) AS expired_snaps
               )"
        );
    }

    /**
     * Retourne les commandes sans mouvement depuis plus de $days jours,
     * hors états finaux (delivered, cancelled, refunded).
     *
     * @param int $days Nombre de jours d'inactivité (défaut : 7)
     * @return array<int, array<string, mixed>>
     */
    public function getStaleOrders(int $days = 7): array
    {
        return $this->db->fetchAll(
            "SELECT id, order_reference, status, price, payment_method, ordered_at, updated_at
               FROM {$this->table}
              WHERE status NOT IN ('delivered', 'cancelled', 'refunded')
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              ORDER BY updated_at ASC",
            [$days]
        );
    }

    // ----------------------------------------------------------------
    // Marketing
    // ----------------------------------------------------------------

    /**
     * Retourne les commandes livrées il y a 3 semaines (fenêtre 504h–528h)
     * dont l'email de notation n'a pas encore été envoyé.
     *
     * Nécessite la colonne rating_email_sent_at sur la table orders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDeliveredForRatingEmail(): array
    {
        return $this->db->fetchAll(
            "SELECT id, user_id, content, price
               FROM {$this->table}
              WHERE status = 'delivered'
                AND rating_email_sent_at IS NULL
                AND delivered_at <= NOW() - INTERVAL 504 HOUR
                AND delivered_at >  NOW() - INTERVAL 528 HOUR"
        );
    }

    /**
     * Marque une commande comme ayant reçu l'email de notation.
     *
     * @param int $orderId Identifiant de la commande
     * @return void
     */
    public function markRatingEmailSent(int $orderId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET rating_email_sent_at = NOW() WHERE id = ?",
            [$orderId]
        );
    }

    /**
     * Retourne les commandes livrées éligibles à l'email demande d'avis global J+28.
     *
     * Fenêtre : 672h–696h après livraison confirmée, review_request_sent_at IS NULL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDeliveredForReviewEmail(): array
    {
        return $this->db->fetchAll(
            "SELECT id, user_id
               FROM {$this->table}
              WHERE status = 'delivered'
                AND review_request_sent_at IS NULL
                AND delivered_at <= NOW() - INTERVAL 672 HOUR
                AND delivered_at >  NOW() - INTERVAL 696 HOUR"
        );
    }

    /**
     * Marque une commande comme ayant reçu l'email de demande d'avis global.
     *
     * @param int $orderId Identifiant de la commande
     * @return void
     */
    public function markReviewEmailSent(int $orderId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET review_request_sent_at = NOW() WHERE id = ?",
            [$orderId]
        );
    }

    /**
     * Retourne la valeur moyenne des commandes sur N derniers jours (statuts actifs).
     *
     * @param int $days Nombre de jours à considérer
     * @return float Valeur moyenne arrondie à 2 décimales
     */
    public function getAvgOrderValue(int $days = 30): float
    {
        $row = $this->db->fetchOne(
            "SELECT AVG(price) AS avg_val
               FROM {$this->table}
              WHERE status IN ('paid', 'processing', 'shipped', 'delivered')
                AND ordered_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return round((float) ($row['avg_val'] ?? 0.0), 2);
    }

    /**
     * Retourne le taux de conversion et les compteurs bruts sur N jours.
     *
     * Formule : commandes / (commandes + paniers abandonnés).
     * Les paniers convertis étant vidés, le dénominateur = commandes + paniers encore actifs.
     *
     * @param int $days Période en jours (défaut 30)
     * @return array{rate: float, orders: int, carts: int}
     */
    public function getConversionStats(int $days = 30): array
    {
        $ordersRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
               FROM {$this->table}
              WHERE status IN ('paid', 'processing', 'shipped', 'delivered')
                AND ordered_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );

        $cartsRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
               FROM carts
              WHERE total_quantity > 0
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );

        $orders = (int) ($ordersRow['cnt'] ?? 0);
        $carts  = (int) ($cartsRow['cnt'] ?? 0);
        $total  = $orders + $carts;

        return [
            'rate'   => $total > 0 ? round($orders / $total, 4) : 0.0,
            'orders' => $orders,
            'carts'  => $carts,
        ];
    }

    /**
     * Retourne le taux de conversion : commandes payées / (commandes + paniers abandonnés) sur N jours.
     *
     * @param int $days Période en jours (défaut 30)
     * @return float Ratio entre 0 et 1 (ex : 0.45 = 45%), arrondi à 4 décimales
     */
    public function getConversionRate(int $days = 30): float
    {
        return $this->getConversionStats($days)['rate'];
    }

    /**
     * Vérifie si un utilisateur a au moins une commande (statut hors annulé/remboursé).
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return bool
     */
    public function hasOrdersByUserId(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM {$this->table}
             WHERE user_id = ? AND status NOT IN ('cancelled', 'refunded')
             LIMIT 1",
            [$userId]
        );
        return $row !== false;
    }
}
