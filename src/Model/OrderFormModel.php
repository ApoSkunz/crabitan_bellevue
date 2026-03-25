<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class OrderFormModel extends Model
{
    protected string $table = 'order_forms';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY year DESC, uploaded_at DESC"
        );
    }

    public function countAll(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaginated(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY year DESC, uploaded_at DESC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdOrNull(int $id): ?array
    {
        $row = $this->findById($id);
        return $row ?: null;
    }

    /**
     * Retourne le bon de commande le plus récent (dernier uploaded_at).
     *
     * @return array<string, mixed>|null
     */
    public function getLatest(): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->table} ORDER BY uploaded_at DESC LIMIT 1"
        );
        return $row ?: null;
    }

    public function create(int $year, ?string $label, string $filename): int
    {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table} (year, label, filename) VALUES (?, ?, ?)",
            [$year, $label, $filename]
        );
    }

    public function delete(int $id): int
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $id;
    }
}
