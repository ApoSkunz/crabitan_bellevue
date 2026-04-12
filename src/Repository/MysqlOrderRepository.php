<?php

declare(strict_types=1);

namespace Repository;

use Model\OrderModel;

/**
 * Implémentation MySQL de OrderRepository.
 *
 * Délègue toutes les opérations à OrderModel en constructor injection,
 * permettant le remplacement par un mock dans les tests unitaires.
 */
class MysqlOrderRepository implements OrderRepository
{
    /**
     * @param OrderModel $model Modèle de commande sous-jacent
     */
    public function __construct(private OrderModel $model)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @param int         $userId  Identifiant de l'utilisateur
     * @param int         $page    Numéro de page (commence à 1)
     * @param int         $perPage Nombre de résultats par page
     * @param string|null $period  Filtre période ou null
     * @param int|null    $year    Filtre année ou null
     * @param string|null $status  Filtre statut ou null
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(
        int $userId,
        int $page,
        int $perPage,
        ?string $period = null,
        ?int $year = null,
        ?string $status = null
    ): array {
        return $this->model->getForUser($userId, $page, $perPage, $period, $year, $status);
    }

    /**
     * {@inheritdoc}
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
    ): int {
        return $this->model->countForUser($userId, $period, $year, $status);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return array<string, mixed>|null
     */
    public function findDetailForUser(int $orderId, int $userId): ?array
    {
        return $this->model->findDetailForUser($orderId, $userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $reference Référence de commande
     * @param int    $userId    Identifiant de l'utilisateur
     * @return array<string, mixed>|null
     */
    public function findByReference(string $reference, int $userId): ?array
    {
        return $this->model->findByReference($reference, $userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $reference Référence de commande
     * @return array<string, mixed>|null
     */
    public function findByReferenceOnly(string $reference): ?array
    {
        return $this->model->findByReferenceOnly($reference);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $reference Référence de commande
     * @return array<string, mixed>|null
     */
    public function findByReferenceForInvoice(string $reference): ?array
    {
        return $this->model->findByReferenceForInvoice($reference);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $orderId, int $userId): ?array
    {
        return $this->model->findByIdForUser($orderId, $userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $id Identifiant de la commande
     * @return array<string, mixed>|null
     */
    public function findByIdForAdmin(int $id): ?array
    {
        return $this->model->findByIdForAdmin($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param int    $id     Identifiant de la commande
     * @param string $status Nouveau statut
     */
    public function updateStatus(int $id, string $status): void
    {
        $this->model->updateStatus($id, $status);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return bool
     */
    public function cancelForUser(int $orderId, int $userId): bool
    {
        return $this->model->cancelForUser($orderId, $userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $orderId Identifiant de la commande
     * @param int $userId  Identifiant de l'utilisateur
     * @return bool
     */
    public function requestReturnForUser(int $orderId, int $userId): bool
    {
        return $this->model->requestReturnForUser($orderId, $userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $userId Identifiant du compte
     * @return int
     */
    public function countActiveOrdersForUser(int $userId): int
    {
        return $this->model->countActiveOrdersForUser($userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $userId Identifiant du compte
     * @return bool
     */
    public function hasActiveOrdersForUser(int $userId): bool
    {
        return $this->model->hasActiveOrdersForUser($userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param array<int, int> $addressIds IDs d'adresses à vérifier
     * @return array<int, int>
     */
    public function getAddressIdsWithActiveOrders(array $addressIds): array
    {
        return $this->model->getAddressIdsWithActiveOrders($addressIds);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $addressId Identifiant de l'adresse
     * @return bool
     */
    public function hasActiveOrderForAddress(int $addressId): bool
    {
        return $this->model->hasActiveOrderForAddress($addressId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<int, int>
     */
    public function getAvailableYearsForUser(int $userId): array
    {
        return $this->model->getAvailableYearsForUser($userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $orderId Identifiant de la commande
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFiles(int $orderId): array
    {
        return $this->model->getInvoiceFiles($orderId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<int, array<string, mixed>>
     */
    public function getInvoiceFilesForUser(int $userId): array
    {
        return $this->model->getInvoiceFilesForUser($userId);
    }

    /**
     * {@inheritdoc}
     *
     * @param int    $id            Identifiant de la commande
     * @param string $invoiceNumber Numéro de facture
     * @param string $invoicePath   Chemin relatif du PDF
     */
    public function updateInvoiceData(int $id, string $invoiceNumber, string $invoicePath): void
    {
        $this->model->updateInvoiceData($id, $invoiceNumber, $invoicePath);
    }

    /**
     * {@inheritdoc}
     *
     * @param int         $orderId       Identifiant de la commande
     * @param string      $path          Chemin relatif du fichier PDF
     * @param string|null $invoiceNumber Numéro de facture ou null
     * @param string      $source        'auto' ou 'upload'
     */
    public function addInvoiceFile(int $orderId, string $path, ?string $invoiceNumber, string $source): void
    {
        $this->model->addInvoiceFile($orderId, $path, $invoiceNumber, $source);
    }
}
