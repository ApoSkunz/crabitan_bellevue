<?php

declare(strict_types=1);

namespace Core;

abstract class Model
{
    protected Database $db;
    protected string $table;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    public function findAll(string $where = '', array $params = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE $where";
        }
        return $this->db->fetchAll($sql, $params);
    }

    public function delete(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }
}
