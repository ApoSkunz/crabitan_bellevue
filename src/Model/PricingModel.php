<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class PricingModel extends Model
{
    protected string $table = 'pricing_rules';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY format ASC, min_quantity ASC"
        );
    }

    public function update(
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
