<?php

declare(strict_types=1);

namespace Service;

/**
 * Service d'alertes de sécurité pour les comptes administrateurs.
 *
 * Détecte les seuils d'échecs de connexion et envoie des emails d'alerte
 * à l'admin concerné et au mainteneur (MAINTAINER_MAIL).
 */
class SecurityAlertService
{
    /** Seuil par défaut : 3 échecs en 5 minutes déclenchent une alerte. */
    private const DEFAULT_THRESHOLD  = 3;
    private const DEFAULT_WINDOW_MIN = 5;

    /** Seuil auto-blocage définitif : 5 échecs en 1h → compte bloqué jusqu'à déblocage manuel. */
    public const AUTO_BLOCK_THRESHOLD  = 5;
    public const AUTO_BLOCK_WINDOW_MIN = 60;

    /** Clé APCu/session pour compter les échecs de connexion admin (fenêtre 5 min, alerte). */
    private const ADMIN_FAIL_PREFIX = 'admin_login_fail:';

    /** Clé APCu/session pour le compteur cumulatif auto-blocage (fenêtre 1h). */
    private const ADMIN_FAIL_CUMUL_PREFIX = 'admin_fail_cumul:';
    private const LOG_CONTEXT             = '[SecurityAlertService]';

    private RateLimiterService $rateLimiter;
    private MailService $mail;

    /**
     * Initialise le service avec ses dépendances.
     *
     * @param RateLimiterService|null $rateLimiter Service de rate limiting (APCu/session)
     * @param MailService|null        $mail        Service d'envoi d'emails
     */
    public function __construct(
        ?RateLimiterService $rateLimiter = null,
        ?MailService $mail = null
    ) {
        $this->rateLimiter = $rateLimiter ?? new RateLimiterService();
        $this->mail        = $mail ?? new MailService();
    }

    // ----------------------------------------------------------------
    // Détection des seuils
    // ----------------------------------------------------------------

    /**
     * Indique si le seuil d'échecs de connexion est atteint pour un admin.
     *
     * Incrémente le compteur à chaque appel (enregistre l'échec),
     * puis retourne true si le compteur vient d'atteindre le seuil.
     * Les appels suivants au-delà du seuil retournent false (alerte déjà envoyée).
     *
     * @param int $adminId       Identifiant du compte admin
     * @param int $windowMinutes Fenêtre de temps en minutes (défaut : 5)
     * @param int $threshold     Nombre d'échecs déclenchant l'alerte (défaut : 3)
     * @return bool True si le seuil vient d'être atteint exactement (1 seule alerte)
     */
    public function shouldAlertOnFailedLogins(
        int $adminId,
        int $windowMinutes = self::DEFAULT_WINDOW_MIN,
        int $threshold = self::DEFAULT_THRESHOLD
    ): bool {
        $key     = self::ADMIN_FAIL_PREFIX . $adminId;
        $window  = $windowMinutes * 60;

        $this->rateLimiter->recordAttempt($key, $window);
        $count = $this->rateLimiter->recall($key . ':count', 0);

        return (int) $count === $threshold;
    }

    /**
     * Enregistre un échec de connexion dans le compteur cumulatif (fenêtre 1h).
     *
     * À appeler sur chaque échec de connexion admin, indépendamment du compteur d'alerte.
     *
     * @param int $adminId Identifiant du compte admin
     * @return void
     */
    public function recordAutoBlockFailure(int $adminId): void
    {
        $key    = self::ADMIN_FAIL_CUMUL_PREFIX . $adminId;
        $window = self::AUTO_BLOCK_WINDOW_MIN * 60;
        $this->rateLimiter->recordAttempt($key, $window);
    }

    /**
     * Indique si le compte admin doit être bloqué définitivement.
     *
     * Retourne true si le compteur cumulatif (1h) atteint ou dépasse AUTO_BLOCK_THRESHOLD (5).
     * Le blocage est définitif : seul un super_admin peut le lever manuellement.
     *
     * @param int $adminId Identifiant du compte admin
     * @return bool True si le compte doit être bloqué
     */
    public function shouldAutoBlock(int $adminId): bool
    {
        $key   = self::ADMIN_FAIL_CUMUL_PREFIX . $adminId;
        $count = $this->rateLimiter->recall($key . ':count', 0);

        return (int) $count >= self::AUTO_BLOCK_THRESHOLD;
    }

    /**
     * Réinitialise le compteur d'échecs de connexion pour un admin.
     *
     * À appeler après une connexion réussie.
     *
     * @param int $adminId Identifiant du compte admin
     * @return void
     */
    public function resetFailedLoginCount(int $adminId): void
    {
        $this->rateLimiter->reset(self::ADMIN_FAIL_PREFIX . $adminId);
        $this->rateLimiter->reset(self::ADMIN_FAIL_CUMUL_PREFIX . $adminId);
    }

    // ----------------------------------------------------------------
    // Envoi des alertes email
    // ----------------------------------------------------------------

    /**
     * Envoie une alerte email lors de trop nombreux échecs de connexion admin.
     *
     * Envoie deux emails :
     *  - à l'admin concerné (email du compte)
     *  - au mainteneur (MAINTAINER_MAIL dans .env)
     *
     * @param array<string, mixed> $account Données du compte admin (email, firstname, company_name, lang)
     * @param string               $ip      Adresse IP de l'attaquant
     * @return void
     */
    public function sendAdminLoginAlert(array $account, string $ip): void
    {
        $to   = (string) ($account['email'] ?? '');
        $name = $this->resolveDisplayName($account);
        $lang = $this->resolveLang($account);

        try {
            $this->mail->sendAdminLoginFailureAlert($to, $name, $lang, $ip);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, self::LOG_CONTEXT);
        }

        $maintainerMail = $_ENV['MAINTAINER_MAIL'] ?? 'alexandresolane.web@gmail.com';
        if ($maintainerMail !== '' && $maintainerMail !== $to) {
            try {
                $this->mail->sendAdminLoginFailureAlert($maintainerMail, 'Mainteneur', 'fr', $ip, $name);
            } catch (\Throwable $e) {
                \Core\ErrorLogger::warning($e, self::LOG_CONTEXT);
            }
        }
    }

    /**
     * Envoie une alerte email lors d'un changement de mot de passe admin.
     *
     * @param array<string, mixed> $account   Données du compte admin
     * @param string               $ip        Adresse IP de la requête
     * @param string               $userAgent User-Agent du navigateur
     * @return void
     */
    public function sendPasswordChangeAlert(array $account, string $ip, string $userAgent): void
    {
        $to   = (string) ($account['email'] ?? '');
        $name = $this->resolveDisplayName($account);
        $lang = $this->resolveLang($account);

        try {
            $this->mail->sendAdminPasswordChangeAlert($to, $name, $lang, $ip, $userAgent);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, self::LOG_CONTEXT);
        }

        $maintainerMail = $_ENV['MAINTAINER_MAIL'] ?? 'alexandresolane.web@gmail.com';
        if ($maintainerMail !== '' && $maintainerMail !== $to) {
            try {
                $this->mail->sendAdminPasswordChangeAlert(
                    $maintainerMail,
                    'Mainteneur',
                    'fr',
                    $ip,
                    $userAgent,
                    $name
                );
            } catch (\Throwable $e) {
                \Core\ErrorLogger::warning($e, self::LOG_CONTEXT);
            }
        }
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Résout le nom d'affichage d'un compte (prénom ou nom commercial).
     *
     * @param array<string, mixed> $account
     * @return string
     */
    private function resolveDisplayName(array $account): string
    {
        $name = trim((string) ($account['firstname'] ?? '') . ' ' . (string) ($account['lastname'] ?? ''));
        if ($name === '' || $name === ' ') {
            $name = (string) ($account['company_name'] ?? 'Admin');
        }
        return trim($name) ?: 'Admin';
    }

    /**
     * Résout la langue d'un compte (fr ou en uniquement).
     *
     * @param array<string, mixed> $account
     * @return string
     */
    private function resolveLang(array $account): string
    {
        $lang = (string) ($account['lang'] ?? 'fr');
        return in_array($lang, ['fr', 'en'], true) ? $lang : 'fr';
    }
}
