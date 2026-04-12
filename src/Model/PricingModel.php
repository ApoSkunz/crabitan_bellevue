<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle d'accès et de mise à jour des règles tarifaires (admin).
 */
class PricingModel extends Model
{
    protected string $table = 'pricing_rules';

    /**
     * Retourne toutes les règles tarifaires, triées par format puis quantité minimum.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT id, format, min_quantity, max_quantity, delivery_price, price_type, withdrawal_price, label, active, created_at, updated_at
             FROM {$this->table} ORDER BY format ASC, min_quantity ASC"
        );
    }

    /**
     * Met à jour une règle tarifaire existante.
     *
     * @param int         $id              Identifiant de la règle
     * @param float       $deliveryPrice   Prix de livraison
     * @param float       $withdrawalPrice Prix de retrait
     * @param string      $labelFr         Libellé en français
     * @param string      $labelEn         Libellé en anglais
     * @param bool        $active          Règle active ou non
     * @param int         $minQuantity     Quantité minimale
     * @param int|null    $maxQuantity     Quantité maximale (null = illimité)
     * @return void
     */
    public function update( // NOSONAR — php:S107 : 8 params couvrent exactement les colonnes de la table pricing_rules
        int $id,
        float $deliveryPrice,
        float $withdrawalPrice,
        string $labelFr,
        string $labelEn,
        bool $active,
        int $minQuantity,
        ?int $maxQuantity
    ): void {
        $label = json_encode(['fr' => $labelFr, 'en' => $labelEn]);
        $this->db->execute(
            "UPDATE {$this->table}
             SET delivery_price = ?, withdrawal_price = ?, label = ?, active = ?,
                 min_quantity = ?, max_quantity = ?
             WHERE id = ?",
            [$deliveryPrice, $withdrawalPrice, $label, $active ? 1 : 0, $minQuantity, $maxQuantity, $id]
        );
    }
}
