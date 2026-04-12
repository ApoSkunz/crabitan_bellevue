<?php

declare(strict_types=1);

namespace Service;

use Core\Database;

/**
 * Service de limitation du taux de requêtes (rate limiting).
 *
 * Utilise APCu comme backend principal (mémoire partagée, persistante entre
 * les requêtes dans le même processus PHP-FPM). Si APCu n'est pas disponible,
 * bascule sur la BDD MySQL (table rate_limits). Si la BDD n'est pas disponible,
 * bascule sur les sessions PHP comme dernier recours (acceptable en dev,
 * déconseillé en production multi-processus).
 *
 * Chaque "bucket" est identifié par une clé arbitraire (ex. : "login:127.0.0.1"
 * ou "account_lockout:42"). Le service stocke deux entrées :
 *   - `rl:{key}:count` : nombre de tentatives dans la fenêtre courante
 *   - `rl:{key}:until` : timestamp Unix de fin de lockout (0 si pas de lockout)
 */
class RateLimiterService
{
    private const PREFIX       = 'rl:';
    private const SUFFIX_COUNT = ':count';
    private const SUFFIX_UNTIL = ':until';
    private const LOG_PREFIX   = '[RateLimiterService]';

    private ?Database $db;

    /**
     * Constructeur avec injection optionnelle de la base de données.
     *
     * @param Database|null $db Instance de la BDD (null = pas de backend BDD)
     */
    public function __construct(?Database $db = null)
    {
        $this->db = $db;
    }

    /**
     * Vérifie si la clé est actuellement bloquée ou si elle a dépassé le seuil.
     *
     * Retourne true si la requête est autorisée, false si elle doit être rejetée.
     *
     * @param string $key           Identifiant unique du bucket (ex. "login:127.0.0.1")
     * @param int    $maxAttempts   Nombre maximum de tentatives autorisées dans la fenêtre
     * @param int    $windowSeconds Durée de la fenêtre glissante en secondes
     * @return bool True si autorisé, false si bloqué
     */
    public function checkLimit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $until = $this->get($key . self::SUFFIX_UNTIL, 0);
        if ($until > time()) {
            return false;
        }

        $count = $this->get($key . self::SUFFIX_COUNT, 0);
        if ($count >= $maxAttempts) {
            // Le lockout n'est pas encore posé : on le pose maintenant
            $this->set($key . self::SUFFIX_UNTIL, time() + $windowSeconds, $windowSeconds + 60);
            return false;
        }

        return true;
    }

    /**
     * Enregistre une tentative pour la clé donnée.
     *
     * Si c'est la première tentative dans la fenêtre, l'entrée expire
     * automatiquement après $windowSeconds. Les tentatives suivantes
     * prolongent uniquement le TTL de lockout, pas la fenêtre de comptage.
     *
     * @param string $key           Identifiant du bucket
     * @param int    $windowSeconds Durée de la fenêtre en secondes
     * @return void
     */
    public function recordAttempt(string $key, int $windowSeconds): void
    {
        $count = $this->get($key . self::SUFFIX_COUNT, 0);
        $this->set($key . self::SUFFIX_COUNT, $count + 1, $windowSeconds);
    }

    /**
     * Réinitialise toutes les entrées associées à la clé (compteur + lockout).
     *
     * @param string $key Identifiant du bucket
     * @return void
     */
    public function reset(string $key): void
    {
        $this->delete($key . self::SUFFIX_COUNT);
        $this->delete($key . self::SUFFIX_UNTIL);
    }

    /**
     * Retourne le nombre de secondes restantes avant la fin du lockout.
     *
     * Retourne 0 si la clé n'est pas en lockout.
     *
     * @param string $key Identifiant du bucket
     * @return int Secondes restantes (0 si pas de lockout actif)
     */
    public function getRetryAfter(string $key): int
    {
        $until = $this->get($key . self::SUFFIX_UNTIL, 0);
        return max(0, $until - time());
    }

    /**
     * Stocke une valeur arbitraire avec TTL (métadonnée associée à une clé de rate limiting).
     *
     * Utilise le même backend que le rate limiter (APCu, BDD ou session).
     *
     * @param string $key   Identifiant de la donnée (ex. "rgpd_export_ts:42")
     * @param mixed  $value Valeur à stocker
     * @param int    $ttl   Durée de vie en secondes
     * @return void
     */
    public function remember(string $key, mixed $value, int $ttl): void
    {
        $this->set($key, $value, $ttl);
    }

    /**
     * Lit une valeur arbitraire précédemment stockée avec remember().
     *
     * @param string $key     Identifiant de la donnée
     * @param mixed  $default Valeur par défaut si absente
     * @return mixed
     */
    public function recall(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * Indique si APCu est disponible et activé.
     *
     * @return bool
     */
    public function isApcuAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    /**
     * Indique si le backend BDD est disponible.
     *
     * @return bool
     */
    public function isDbAvailable(): bool
    {
        return $this->db !== null;
    }

    // ----------------------------------------------------------------
    // Helpers de stockage
    // ----------------------------------------------------------------

    /**
     * Lit une valeur depuis APCu, la BDD ou la session (par ordre de priorité).
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    private function get(string $key, mixed $default = null): mixed
    {
        $fullKey = self::PREFIX . $key;

        if ($this->isApcuAvailable()) {
            $success = false;
            $value   = apcu_fetch($fullKey, $success);
            return $success ? $value : $default;
        }

        if ($this->isDbAvailable()) {
            return $this->dbGet($fullKey, $default);
        }

        $this->ensureSession();
        if (!isset($_SESSION['_rl'][$fullKey])) {
            return $default;
        }
        $ttlAt = $_SESSION['_rl_ttl'][$fullKey] ?? null;
        if ($ttlAt !== null && time() >= $ttlAt) {
            unset($_SESSION['_rl'][$fullKey], $_SESSION['_rl_ttl'][$fullKey]);
        }
        return $_SESSION['_rl'][$fullKey] ?? $default;
    }

    /**
     * Écrit une valeur dans APCu, la BDD ou la session (par ordre de priorité).
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   Durée de vie en secondes
     * @return void
     */
    private function set(string $key, mixed $value, int $ttl): void
    {
        $fullKey = self::PREFIX . $key;

        if ($this->isApcuAvailable()) {
            apcu_store($fullKey, $value, $ttl);
            return;
        }

        if ($this->isDbAvailable()) {
            $this->dbSet($fullKey, $value, $ttl);
            return;
        }

        $this->ensureSession();
        $_SESSION['_rl'][$fullKey] = $value;

        // En mode session, stocker le timestamp d'expiration manuellement
        $_SESSION['_rl_ttl'][$fullKey] = time() + $ttl;
    }

    /**
     * Supprime une valeur dans APCu, la BDD ou la session (par ordre de priorité).
     *
     * @param string $key
     * @return void
     */
    private function delete(string $key): void
    {
        $fullKey = self::PREFIX . $key;

        if ($this->isApcuAvailable()) {
            apcu_delete($fullKey);
            return;
        }

        if ($this->isDbAvailable()) {
            $this->dbDelete($fullKey);
            return;
        }

        $this->ensureSession();
        unset($_SESSION['_rl'][$fullKey], $_SESSION['_rl_ttl'][$fullKey]);
    }

    /**
     * Lit une valeur depuis la table rate_limits en BDD.
     *
     * @param string $fullKey Clé complète (avec préfixe rl:)
     * @param mixed  $default
     * @return mixed
     */
    private function dbGet(string $fullKey, mixed $default = null): mixed
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT value FROM rate_limits WHERE cache_key = ? AND expires_at > UNIX_TIMESTAMP()',
                [$fullKey]
            );
            if ($row === false) {
                return $default;
            }
            return unserialize((string) $row['value']);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, self::LOG_PREFIX);
            return $default;
        }
    }

    /**
     * Écrit une valeur dans la table rate_limits en BDD (upsert).
     *
     * @param string $fullKey    Clé complète (avec préfixe rl:)
     * @param mixed  $value
     * @param int    $ttl        Durée de vie en secondes
     * @return void
     */
    private function dbSet(string $fullKey, mixed $value, int $ttl): void
    {
        try {
            $expiresAt  = time() + $ttl;
            $serialized = serialize($value);
            $this->db->query(
                'INSERT INTO rate_limits (cache_key, value, expires_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = ?, expires_at = ?',
                [$fullKey, $serialized, $expiresAt, $serialized, $expiresAt]
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, self::LOG_PREFIX);
        }
    }

    /**
     * Supprime une entrée de la table rate_limits en BDD.
     *
     * @param string $fullKey Clé complète (avec préfixe rl:)
     * @return void
     */
    private function dbDelete(string $fullKey): void
    {
        try {
            $this->db->execute(
                'DELETE FROM rate_limits WHERE cache_key = ?',
                [$fullKey]
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, self::LOG_PREFIX);
        }
    }

    /**
     * S'assure que la session est démarrée.
     *
     * @return void
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(); // @codeCoverageIgnore
        }
    }
}
