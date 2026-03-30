<?php

declare(strict_types=1);

namespace Service;

/**
 * Service de limitation du taux de requêtes (rate limiting).
 *
 * Utilise APCu comme backend principal (mémoire partagée, persistante entre
 * les requêtes dans le même processus PHP-FPM). Si APCu n'est pas disponible,
 * bascule sur les sessions PHP comme fallback (acceptable en dev, déconseillé
 * en production multi-processus).
 *
 * Chaque "bucket" est identifié par une clé arbitraire (ex. : "login:127.0.0.1"
 * ou "account_lockout:42"). Le service stocke deux entrées :
 *   - `rl:{key}:count` : nombre de tentatives dans la fenêtre courante
 *   - `rl:{key}:until` : timestamp Unix de fin de lockout (0 si pas de lockout)
 */
class RateLimiterService
{
    private const PREFIX = 'rl:';

    /**
     * Vérifie si la clé est actuellement bloquée ou si elle a dépassé le seuil.
     *
     * Retourne true si la requête est autorisée, false si elle doit être rejetée.
     *
     * @param string $key          Identifiant unique du bucket (ex. "login:127.0.0.1")
     * @param int    $maxAttempts  Nombre maximum de tentatives autorisées dans la fenêtre
     * @param int    $windowSeconds Durée de la fenêtre glissante en secondes
     * @return bool True si autorisé, false si bloqué
     */
    public function checkLimit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $until = $this->get($key . ':until', 0);
        if ($until > time()) {
            return false;
        }

        $count = $this->get($key . ':count', 0);
        if ($count >= $maxAttempts) {
            // Le lockout n'est pas encore posé : on le pose maintenant
            $this->set($key . ':until', time() + $windowSeconds, $windowSeconds + 60);
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
        $count = $this->get($key . ':count', 0);
        $this->set($key . ':count', $count + 1, $windowSeconds);
    }

    /**
     * Réinitialise toutes les entrées associées à la clé (compteur + lockout).
     *
     * @param string $key Identifiant du bucket
     * @return void
     */
    public function reset(string $key): void
    {
        $this->delete($key . ':count');
        $this->delete($key . ':until');
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
        $until = $this->get($key . ':until', 0);
        return max(0, $until - time());
    }

    /**
     * Indique si APCu est disponible et activé.
     *
     * @return bool
     */
    public function isApcuAvailable(): bool
    {
        return function_exists('apcu_fetch') && ini_get('apc.enabled');
    }

    // ----------------------------------------------------------------
    // Helpers de stockage
    // ----------------------------------------------------------------

    /**
     * Lit une valeur depuis APCu ou la session.
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

        $this->ensureSession();
        return $_SESSION['_rl'][$fullKey] ?? $default;
    }

    /**
     * Écrit une valeur dans APCu ou la session, avec un TTL.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   Durée de vie en secondes (ignorée pour les sessions)
     * @return void
     */
    private function set(string $key, mixed $value, int $ttl): void
    {
        $fullKey = self::PREFIX . $key;

        if ($this->isApcuAvailable()) {
            apcu_store($fullKey, $value, $ttl);
            return;
        }

        $this->ensureSession();
        $_SESSION['_rl'][$fullKey] = $value;

        // En mode session, stocker le timestamp d'expiration manuellement
        $_SESSION['_rl_ttl'][$fullKey] = time() + $ttl;
    }

    /**
     * Supprime une valeur dans APCu ou la session.
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

        $this->ensureSession();
        unset($_SESSION['_rl'][$fullKey], $_SESSION['_rl_ttl'][$fullKey]);
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
