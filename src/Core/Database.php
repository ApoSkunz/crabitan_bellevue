<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

/**
 * Singleton d'accès à la base de données MySQL via PDO.
 * Gère les transactions imbriquées par compteur de profondeur.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $transactionDepth = 0;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_NAME'] ?? 'crabitan_bellevue'
        );

        $this->pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Forcer la timezone MySQL à UTC pour cohérence avec PHP (date/time functions)
        $this->pdo->exec("SET time_zone = '+00:00'");
    }

    /**
     * Retourne l'instance singleton de la base de données.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Exécute une requête SQL préparée et retourne le statement.
     *
     * @param string       $sql    Requête SQL avec placeholders
     * @param array<mixed> $params Valeurs des paramètres
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retourne la première ligne d'une requête SELECT, ou false si aucun résultat.
     *
     * @param string       $sql    Requête SQL
     * @param array<mixed> $params Valeurs des paramètres
     * @return array<string, mixed>|false
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Retourne toutes les lignes d'une requête SELECT.
     *
     * @param string       $sql    Requête SQL
     * @param array<mixed> $params Valeurs des paramètres
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Exécute un INSERT et retourne l'identifiant de la ligne insérée.
     *
     * @param string       $sql    Requête INSERT
     * @param array<mixed> $params Valeurs des paramètres
     * @return string Dernier ID inséré (lastInsertId)
     */
    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Exécute une requête UPDATE/DELETE et retourne le nombre de lignes affectées.
     *
     * @param string       $sql    Requête SQL
     * @param array<mixed> $params Valeurs des paramètres
     * @return int Nombre de lignes affectées
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Démarre une transaction (ou incrémente le compteur d'imbrication).
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        }
        $this->transactionDepth++;
    }

    /**
     * Valide la transaction courante (ou décrémente le compteur d'imbrication).
     *
     * @return void
     */
    public function commit(): void
    {
        $this->transactionDepth--;
        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        }
    }

    /**
     * Annule la transaction courante et réinitialise le compteur d'imbrication.
     *
     * @return void
     */
    public function rollback(): void
    {
        $this->transactionDepth = 0;
        $this->pdo->rollBack();
    }
}
