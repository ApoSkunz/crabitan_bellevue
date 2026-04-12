<?php

declare(strict_types=1);

namespace Service;

use Model\AccountModel;
use Model\AddressModel;
use Model\ConnectionModel;
use Model\FavoriteModel;
use Model\OrderAddressSnapshotModel;
use Model\OrderModel;
use Model\TrustedDeviceModel;

/**
 * Service de construction de l'export RGPD (Art. 20 — droit à la portabilité).
 *
 * Centralise la logique partagée entre AccountController (export self-service)
 * et RgpdAdminController (export déclenché par l'admin sur demande).
 */
class RgpdExportService
{
    private AccountModel $accounts;
    private AddressModel $addresses;
    private FavoriteModel $favorites;
    private OrderModel $orders;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private OrderAddressSnapshotModel $orderAddressSnapshots;

    public function __construct()
    {
        $this->accounts              = new AccountModel();
        $this->addresses             = new AddressModel();
        $this->favorites             = new FavoriteModel();
        $this->orders                = new OrderModel();
        $this->connections           = new ConnectionModel();
        $this->trustedDevices        = new TrustedDeviceModel();
        $this->orderAddressSnapshots = new OrderAddressSnapshotModel();
    }

    /**
     * Construit le tableau de données export RGPD pour un compte existant.
     *
     * @param int $userId Identifiant du compte
     * @return array<string, mixed>
     */
    public function buildForAccount(int $userId): array
    {
        $account          = $this->accounts->findById($userId);
        $addresses        = $this->addresses->getByUser($userId);
        $favorites        = $this->favorites->getByUser($userId);
        $orders           = $this->orders->getForUser($userId, 1, 9999);
        $trustedDevices   = $this->trustedDevices->getForExport($userId);
        $sessions         = $this->connections->getActiveForUser($userId);
        $recentLogs       = $this->connections->getRecentForUser($userId);
        $consentData      = $this->accounts->getConsentData($userId);
        $addressSnapshots = $this->orderAddressSnapshots->getForUserExport($userId);

        return [
            'exported_at' => date('c'),
            'account'     => [
                'Email'            => $account['email']        ?? null,
                'Type de compte'   => $account['account_type'] ?? null,
                'Langue'           => $account['lang']         ?? null,
                'Date de création' => $account['created_at']   ?? null,
                ...(($account['account_type'] ?? '') === 'company'
                    ? ['Raison sociale' => $account['company_name'] ?? null]
                    : [
                        'Civilité' => $account['civility']  ?? null,
                        'Prénom'   => $account['firstname'] ?? null,
                        'Nom'      => $account['lastname']  ?? null,
                    ]
                ),
                'Newsletter' => ($account['newsletter'] ?? 0) ? 'Oui' : 'Non',
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
                'user_agent'   => $d['user_agent'],
            ], $trustedDevices),
            'active_sessions' => array_map(fn($s) => [
                'device_name' => $s['device_name'],
                'ip_address'  => $s['ip_address'],
                'created_at'  => $s['created_at'],
                'expired_at'  => $s['expired_at'],
            ], $sessions),
            'connection_logs' => array_map(fn($l) => [
                'device_name' => $l['device_name'],
                'ip_address'  => $l['ip_address'],
                'user_agent'  => $l['user_agent'],
                'auth_method' => $l['auth_method'],
                'status'      => $l['status'],
                'created_at'  => $l['created_at'],
            ], $recentLogs),
            'consentements' => [
                'newsletter' => [
                    'abonne'           => $consentData['newsletter']['abonne'] ? 'Oui' : 'Non',
                    'email_verifie_le' => $consentData['newsletter']['email_verifie_le'],
                ],
                'cgv_versions_acceptees' => $consentData['cgv_versions'],
            ],
            'adresses_commandes' => array_map(fn($s) => [
                'order_reference' => $s['order_reference'],
                'address_type'    => $s['address_type'],
                'civility'        => $s['civility'],
                'firstname'       => $s['firstname'],
                'lastname'        => $s['lastname'],
                'street'          => $s['street'],
                'city'            => $s['city'],
                'zip'             => $s['zip'],
                'country'         => $s['country'],
                'phone'           => $s['phone'],
                'ordered_at'      => $s['ordered_at'],
            ], $addressSnapshots),
        ];
    }

    /**
     * Construit le tableau de données export RGPD pour un abonné sans compte.
     *
     * @param array<string, mixed> $subscription Ligne newsletter_subscriptions
     * @return array<string, mixed>
     */
    public function buildForSubscriber(array $subscription): array
    {
        return [
            'exported_at'            => date('c'),
            'source'                 => 'newsletter_subscription',
            'newsletter_subscription' => [
                'email'                   => $subscription['email'],
                'newsletter_confirmed'    => (bool) $subscription['newsletter_confirmed'],
                'newsletter_consent_date' => $subscription['newsletter_consent_date'],
                'newsletter_consent_ip'   => $subscription['newsletter_consent_ip'],
                'consent_ip'              => $subscription['consent_ip'],
                'lang'                    => $subscription['lang'],
                'created_at'              => $subscription['created_at'],
            ],
        ];
    }

    /**
     * Génère le contenu binaire de l'archive (ZIP si ZipArchive disponible, JSON sinon).
     *
     * @param array<string, mixed> $exportData Données à sérialiser
     * @param string               $basename   Nom de base sans extension
     * @return array{0: string, 1: string} [contenu binaire, nom de fichier avec extension]
     */
    public function buildZip(array $exportData, string $basename): array
    {
        $json = (string) json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (class_exists('ZipArchive')) {
            $tmpZip = (string) tempnam(sys_get_temp_dir(), 'cbv_rgpd_');
            $zip    = new \ZipArchive();
            $zip->open($tmpZip, \ZipArchive::OVERWRITE);
            $zip->addFromString($basename . '.json', $json);
            $zip->close();
            $content  = (string) file_get_contents($tmpZip);
            unlink($tmpZip);
            return [$content, $basename . '.zip'];
        }

        return [$json, $basename . '.json'];
    }

    /**
     * Construit le contenu binaire de l'export RGPD (ZIP JSON+PDF ou JSON seul en fallback).
     *
     * Inclut un PDF lisible (RGPD Art. 20) si ZipArchive est disponible.
     *
     * @param array<string, mixed> $export   Données à exporter
     * @param string               $date     Date au format Y-m-d
     * @param string               $basename Nom de base du fichier (ex : mes-donnees-2026-04-01)
     * @return array{0: string, 1: bool} [content, isZip]
     */
    public function buildExportContent(array $export, string $date, string $basename): array
    {
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        if (class_exists('ZipArchive')) {
            $pdf    = $this->buildExportPdf($export, $date);
            $tmpZip = (string) tempnam(sys_get_temp_dir(), 'cbv_export_');
            $zip    = new \ZipArchive();
            $zip->open($tmpZip, \ZipArchive::OVERWRITE);
            $zip->addFromString($basename . '.json', $json);
            $zip->addFromString($basename . '.pdf', $pdf);
            $zip->close();

            $content = (string) file_get_contents($tmpZip);
            unlink($tmpZip);
            return [$content, true];
        }

        return [$json, false];
    }

    /**
     * Génère un PDF lisible (RGPD Art. 20 — portabilité) via TCPDF.
     *
     * @param array<string, mixed> $export Données d'export RGPD
     * @param string               $date   Date au format Y-m-d
     * @return string Contenu binaire du PDF
     */
    public function buildExportPdf(array $export, string $date): string
    {
        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Crabitan Bellevue');
        $pdf->SetAuthor('Crabitan Bellevue');
        $pdf->SetTitle('Mes données personnelles — ' . $date);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $h = '<style>
            body { font-family: dejavusans; font-size: 10pt; color: #1e1e1e; }
            h1 { font-size: 16pt; color: #c1a14b; margin-bottom: 4px; }
            h2 { font-size: 11pt; color: #c1a14b; margin-top: 14px;'
            . ' margin-bottom: 4px; border-bottom: 1px solid #c1a14b; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
            th { background: #f5f0e8; font-weight: bold; padding: 4px 6px; text-align: left; }
            td { padding: 4px 6px; border-bottom: 1px solid #e8e0d0; }
            .muted { color: #888; font-size: 9pt; }
        </style>';

        $acc = $export['account'];

        $h .= '<h1>Mes données personnelles</h1>';
        $h .= '<p class="muted">Exporté le ' . htmlspecialchars($date) . ' — RGPD Art. 20</p>';

        $h .= '<h2>Compte</h2><table><tr><th>Champ</th><th>Valeur</th></tr>';
        foreach ($acc as $k => $v) {
            $h .= '<tr><td>' . htmlspecialchars($k) . '</td>'
                . '<td>' . htmlspecialchars((string) ($v ?? '—')) . '</td></tr>';
        }
        $h .= '</table>';

        $h .= $this->buildPdfTableSection(
            'Commandes',
            $export['orders'],
            '<tr><th>Référence</th><th>Statut</th><th>Total</th><th>Date</th></tr>',
            static fn(array $o) => '<tr><td>' . htmlspecialchars($o['reference']) . '</td>'
                . '<td>' . htmlspecialchars($o['status']) . '</td>'
                . '<td>' . htmlspecialchars((string) $o['price']) . ' €</td>'
                . '<td>' . htmlspecialchars((string) $o['ordered_at']) . '</td></tr>',
            'Aucune commande.'
        );

        $h .= $this->buildPdfTableSection(
            'Adresses',
            $export['addresses'],
            '<tr><th>Type</th><th>Nom</th><th>Adresse</th><th>Ville</th><th>Pays</th></tr>',
            static fn(array $a) => '<tr><td>' . htmlspecialchars($a['type']) . '</td>'
                . '<td>' . htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) . '</td>'
                . '<td>' . htmlspecialchars($a['street']) . '</td>'
                . '<td>' . htmlspecialchars($a['zip_code'] . ' ' . $a['city']) . '</td>'
                . '<td>' . htmlspecialchars($a['country']) . '</td></tr>',
            'Aucune adresse.'
        );

        /** @var array<int, array<string, mixed>> $adressesCommandes */
        $adressesCommandes = $export['adresses_commandes'] ?? [];
        $h .= $this->buildPdfTableSection(
            'Adresses utilisées sur commandes',
            $adressesCommandes,
            '<tr><th>Commande</th><th>Type</th><th>Nom</th><th>Adresse</th><th>Ville</th><th>Pays</th></tr>',
            static fn(array $s) => '<tr><td>' . htmlspecialchars($s['order_reference'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($s['address_type'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars(($s['firstname'] ?? '') . ' ' . ($s['lastname'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars($s['street'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars(($s['zip'] ?? '') . ' ' . ($s['city'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars($s['country'] ?? '—') . '</td></tr>',
            'Aucune adresse de commande.'
        );

        $h .= $this->buildPdfTableSection(
            'Favoris',
            $export['favorites'],
            '<tr><th>Vin</th><th>Millésime</th></tr>',
            static fn(array $f) => '<tr><td>' . htmlspecialchars($f['name'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars((string) ($f['vintage'] ?? '')) . '</td></tr>',
            'Aucun favori.'
        );

        $h .= $this->buildPdfTableSection(
            'Appareils de confiance (MFA)',
            $export['trusted_devices'],
            '<tr><th>Appareil</th><th>Confirmé le</th><th>Dernière activité</th><th>User-Agent</th></tr>',
            static fn(array $d) => '<tr><td>' . htmlspecialchars($d['device_name'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars((string) ($d['confirmed_at'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($d['last_seen'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($d['user_agent'] ?? '—')) . '</td></tr>',
            'Aucun appareil de confiance.'
        );

        $h .= $this->buildPdfTableSection(
            'Sessions actives',
            $export['active_sessions'],
            '<tr><th>Appareil</th><th>Adresse IP</th><th>Connecté le</th><th>Expire le</th></tr>',
            static fn(array $s) => '<tr><td>' . htmlspecialchars($s['device_name'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($s['ip_address'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars((string) ($s['created_at'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($s['expired_at'] ?? '—')) . '</td></tr>',
            'Aucune session active.'
        );

        /** @var array<int, array<string, mixed>> $connectionLogs */
        $connectionLogs = $export['connection_logs'] ?? [];
        $h .= $this->buildPdfTableSection(
            'Logs de connexion (90 derniers jours)',
            $connectionLogs,
            '<tr><th>Appareil</th><th>IP</th><th>Méthode</th><th>Statut</th><th>Date</th></tr>',
            static fn(array $l) => '<tr><td>' . htmlspecialchars($l['device_name'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($l['ip_address'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($l['auth_method'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($l['status'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars((string) ($l['created_at'] ?? '—')) . '</td></tr>',
            'Aucun log de connexion.'
        );

        /** @var array<string, mixed> $consent */
        $consent = $export['consentements'] ?? [];
        /** @var array<string, mixed> $consentNl */
        $consentNl = $consent['newsletter'] ?? [];
        /** @var array<int, string> $cgvVersions */
        $cgvVersions = $consent['cgv_versions_acceptees'] ?? [];
        $h .= '<h2>Consentements</h2>';
        $h .= '<table><tr><th>Newsletter abonné</th><th>Email vérifié le</th></tr>';
        $h .= '<tr><td>' . htmlspecialchars((string) ($consentNl['abonne'] ?? '—')) . '</td>'
            . '<td>' . htmlspecialchars((string) ($consentNl['email_verifie_le'] ?? '—')) . '</td></tr>';
        $h .= '</table>';

        if ($cgvVersions !== []) {
            $h .= '<h2>Versions CGV acceptées</h2><table><tr><th>Version</th></tr>';
            foreach ($cgvVersions as $version) {
                $h .= '<tr><td>' . htmlspecialchars((string) $version) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<h2>Versions CGV acceptées (0)</h2><p class="muted">Aucune commande passée.</p>';
        }

        $pdf->writeHTML($h, true, false, true, false, '');

        return (string) $pdf->Output('', 'S');
    }

    /**
     * Génère une section HTML tableau pour le PDF d'export RGPD.
     *
     * @param string         $label    Titre de la section
     * @param array<mixed>   $rows     Lignes de données
     * @param string         $header   HTML des en-têtes du tableau
     * @param callable       $rowFn    function(array $row): string
     * @param string         $emptyMsg Message affiché si aucune donnée
     * @return string HTML de la section
     */
    private function buildPdfTableSection(
        string $label,
        array $rows,
        string $header,
        callable $rowFn,
        string $emptyMsg
    ): string {
        $h = '<h2>' . $label . ' (' . count($rows) . ')</h2>';
        if ($rows === []) {
            return $h . '<p class="muted">' . $emptyMsg . '</p>';
        }
        $h .= '<table>' . $header;
        foreach ($rows as $row) {
            $h .= $rowFn($row);
        }
        return $h . '</table>';
    }
}
