<?php

declare(strict_types=1);

namespace Core;

/**
 * Classe de base abstraite pour tous les modèles.
 * Fournit les opérations CRUD génériques sur une table MySQL.
 */
abstract class Model
{
    protected Database $db;
    protected string $table;

    /**
     * Initialise le modèle avec l'instance singleton de la base de données.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Trouve un enregistrement par son identifiant primaire.
     *
     * @param int $id Identifiant de la ligne
     * @return array<string, mixed>|false Ligne trouvée ou false si absente
     */
    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /**
     * Retourne tous les enregistrements, avec clause WHERE optionnelle.
     *
     * @param string       $where  Clause WHERE sans le mot-clé (ex. 'active = 1')
     * @param array<mixed> $params Paramètres de la clause WHERE
     * @return array<array-key, array<string, mixed>>
     */
    public function findAll(string $where = '', array $params = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE $where";
        }
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Supprime un enregistrement par son identifiant.
     *
     * @param int $id Identifiant de la ligne à supprimer
     * @return int Nombre de lignes supprimées
     */
    public function delete(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }
}
