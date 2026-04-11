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
}
