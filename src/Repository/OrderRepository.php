<?php

declare(strict_types=1);

namespace Repository;

/**
 * Contrat d'accès aux données de commande.
 *
 * Abstraction du stockage permettant l'injection de dépendance
 * et la testabilité unitaire des controllers et services.
 */
interface OrderRepository
{
    /**
     * Retourne toutes les commandes d'un utilisateur (liste paginée).
     *
     * @param int         $userId   Identifiant de l'utilisateur
     * @param int         $page     Numéro de page (commence à 1)
     * @param int         $perPage  Nombre de résultats par page
     * @param string|null $period   Filtre période ('month', 'year', etc.) ou null
     * @param int|null    $year     Filtre année ou null
     * @param string|null $status   Filtre statut ou null
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(
        int $userId,
        int $page,
        int $perPage,
        ?string $period = null,
        ?int $year = null,
        ?string $status = null
    ): array;

    /**
     * Compte les commandes d'un utilisateur (avec filtres optionnels).
     *
     * @param int         $userId Identifiant de l'utilisateur
     * @param string|null $period Filtre période ou null
     * @param int|null    $year   Filtre année ou null
     * @param string|null $status Filtre statut ou null
     * @return int
     */
    public function countForUser(
        int $userId,
        ?string $period = null,
        ?int $year = null,
        ?string $status = null
    ): int;

    /**
     * Retourne le détail complet d'une commande pour un utilisateur donné.
     *
     * Inclut les adresses de facturation et de livraison (snapshots).
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return array<string, mixed>|null null si non trouvée ou accès interdit
     */
    public function findDetailForUser(int $orderId, int $userId): ?array;

    /**
     * Recherche une commande par sa référence pour un utilisateur donné.
     *
     * @param string $reference Référence de commande (ex. WEB-CB-XXXXX-YYYY)
     * @param int    $userId    Identifiant de l'utilisateur
     * @return array<string, mixed>|null null si non trouvée
     */
    public function findByReference(string $reference, int $userId): ?array;

    /**
     * Recherche une commande par sa référence uniquement (sans vérification userId).
     *
     * Usage : idempotence IPN, vérification d'existence avant création.
     *
     * @param string $reference Référence de commande
     * @return array<string, mixed>|null null si non trouvée
     */
    public function findByReferenceOnly(string $reference): ?array;

    /**
     * Recherche une commande par référence avec les données de facturation (pour PDF).
     *
     * @param string $reference Référence de commande
     * @return array<string, mixed>|null null si non trouvée
     */
    public function findByReferenceForInvoice(string $reference): ?array;

    /**
     * Recherche une commande par son ID pour un accès utilisateur limité.
     *
     * Retourne uniquement les champs nécessaires à la consultation de facture.
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return array<string, mixed>|null null si non trouvée ou accès interdit
     */
    public function findByIdForUser(int $orderId, int $userId): ?array;

    /**
     * Recherche une commande par son ID pour l'administration (données complètes).
     *
     * @param int $id Identifiant de la commande
     * @return array<string, mixed>|null null si non trouvée
     */
    public function findByIdForAdmin(int $id): ?array;

    /**
     * Met à jour le statut d'une commande.
     *
     * @param int    $id     Identifiant de la commande
     * @param string $status Nouveau statut (doit être dans VALID_STATUSES)
     */
    public function updateStatus(int $id, string $status): void;

    /**
     * Annule une commande au nom de l'utilisateur si elle est annulable.
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return bool true si annulée, false si impossible (statut non annulable ou commande inconnue)
     */
    public function cancelForUser(int $orderId, int $userId): bool;

    /**
     * Demande de rétractation après livraison (art. L221-18 Code conso).
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return bool true si la demande a été enregistrée
     */
    public function requestReturnForUser(int $orderId, int $userId): bool;

    /**
     * Retourne le nombre de commandes actives de l'utilisateur.
     *
     * @param int $userId Identifiant du compte
     * @return int
     */
    public function countActiveOrdersForUser(int $userId): int;

    /**
     * Retourne true si l'utilisateur possède au moins une commande active.
     *
     * @param int $userId Identifiant du compte
     * @return bool
     */
    public function hasActiveOrdersForUser(int $userId): bool;

    /**
     * Retourne les IDs d'adresses bloquées par une commande active.
     *
     * @param array<int, int> $addressIds IDs d'adresses à vérifier
     * @return array<int, int>
     */
    public function getAddressIdsWithActiveOrders(array $addressIds): array;

    /**
     * Vérifie si une adresse est liée à une commande active.
     *
     * @param int $addressId Identifiant de l'adresse
     * @return bool
     */
    public function hasActiveOrderForAddress(int $addressId): bool;

    /**
     * Retourne les années disponibles dans les commandes de l'utilisateur.
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<int, int>
     */
    public function getAvailableYearsForUser(int $userId): array;

    /**
     * Retourne les fichiers de factures associés à une commande.
     *
     * @param int $orderId Identifiant de la commande
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFiles(int $orderId): array;

    /**
     * Retourne toutes les factures d'un utilisateur.
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFilesForUser(int $userId): array;

    /**
     * Met à jour le numéro et le chemin de la facture d'une commande.
     *
     * @param int    $id            Identifiant de la commande
     * @param string $invoiceNumber Numéro de facture (WEB-INVOICE-YYYY-XXXX)
     * @param string $invoicePath   Chemin relatif du PDF
     */
    public function updateInvoiceData(int $id, string $invoiceNumber, string $invoicePath): void;

    /**
     * Ajoute une entrée dans l'historique des factures (L441-9 C.com.).
     *
     * @param int         $orderId       Identifiant de la commande
     * @param string      $path          Chemin relatif du fichier PDF
     * @param string|null $invoiceNumber Numéro de facture ou null pour upload manuel
     * @param string      $source        'auto' ou 'upload'
     */
    public function addInvoiceFile(int $orderId, string $path, ?string $invoiceNumber, string $source): void;
}
