<?php

declare(strict_types=1);

namespace Service;

use Core\ErrorLogger;
use Model\AccountModel;
use Service\MailService;
use Service\RateLimiterService;
use Service\SecurityAlertService;

/**
 * Service de logique métier pour l'authentification par identifiants.
 *
 * Encapsule les vérifications du flux de connexion :
 *   - lockout compte existant (BT2 pré-vérification)
 *   - compte en cours de suppression programmée
 *   - vérification du mot de passe
 *   - alertes admin et blocage automatique
 *   - lockout BT2 après échec
 *   - vérification de l'email
 *
 * Ce service est pur (pas de dépendance HTTP, pas de $_REQUEST, pas de Response).
 * Les décisions HTTP restent dans le contrôleur appelant.
 */
class LoginService
{
    /** Nombre max d'échecs consécutifs avant lockout du compte (BT2). */
    private const MAX_ACCOUNT_FAILURES = 3;

    /** Durée du lockout compte en secondes (30 min). */
    private const ACCOUNT_LOCKOUT_WINDOW = 1800;

    /** Fenêtre de rate limiting pour la connexion (15 min). */
    private const WINDOW_LOGIN = 900;

    /** Nombre maximum de renvois de token de vérification par compte par fenêtre. */
    private const MAX_RESEND_VERIF = 3;

    /** Fenêtre de renvoi de token de vérification (1 heure). */
    private const WINDOW_RESEND_VERIF = 3600;

    private AccountModel $accounts;
    private RateLimiterService $rateLimiter;
    private SecurityAlertService $securityAlert;

    /** Service d'envoi d'emails. */
    private MailService $mailer;

    /**
     * Constructeur avec injection des dépendances.
     *
     * @param AccountModel         $accounts      Modèle de comptes utilisateurs
     * @param RateLimiterService   $rateLimiter   Service de rate limiting
     * @param SecurityAlertService $securityAlert Service d'alertes sécurité admin
     * @param MailService|null     $mailer        Service d'envoi d'emails (null → instancié en interne)
     */
    public function __construct(
        AccountModel $accounts,
        RateLimiterService $rateLimiter,
        SecurityAlertService $securityAlert,
        ?MailService $mailer = null
    ) {
        $this->accounts      = $accounts;
        $this->rateLimiter   = $rateLimiter;
        $this->securityAlert = $securityAlert;
        $this->mailer        = $mailer ?? new MailService();
    }

    /**
     * Exécute toutes les vérifications métier du flux de connexion.
     *
     * Retourne un tableau avec :
     *   - 'result'  : 'ok' | 'locked' | 'deleted' | 'bad_credentials' | 'unverified'
     *   - 'account' : array<string, mixed>|null — données du compte si trouvé
     *   - 'wait'    : int — secondes de lockout restantes (si 'locked')
     *
     * Effets de bord possibles :
     *   - Enregistre les tentatives d'échec dans le rate limiter
     *   - Envoie des emails (compte locké, alerte admin, vérification email, réactivation)
     *   - Bloque automatiquement un compte admin si seuil atteint
     *   - Régénère et renvoie le token de vérification si email non confirmé
     *
     * @param string $email    Adresse email saisie (déjà normalisée en minuscules)
     * @param string $password Mot de passe en clair saisi par l'utilisateur
     * @param string $ipKey    Clé de rate limiting de l'IP (ex. "login:127.0.0.1")
     * @param string $lang     Code langue courant (ex. 'fr', 'en')
     * @param string $ip       Adresse IP brute de la requête
     * @return array{result: string, account: array<string, mixed>|null, wait: int}
     */
    public function attempt( // NOSONAR — php:S1142 — 5 retours nécessaires pour les cas auth distincts (locked/deleted/bad_credentials/unverified/ok)
        string $email,
        string $password,
        string $ipKey,
        string $lang,
        string $ip
    ): array {
        $account = $this->accounts->findByEmail($email);

        // BT2 — Compte déjà verrouillé (tentatives 6+) : bloquer sans email
        if ($account) {
            $accountKey = 'account_lockout:' . (int) $account['id'];
            if (!$this->rateLimiter->checkLimit($accountKey, self::MAX_ACCOUNT_FAILURES, self::ACCOUNT_LOCKOUT_WINDOW)) {
                $wait = (int) ceil($this->rateLimiter->getRetryAfter($accountKey) / 60);
                $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
                return ['result' => 'locked', 'account' => $account, 'wait' => $wait];
            }
        }

        // Compte soft-deleted : identifiants valides → message de réactivation
        if (!$account) {
            $deleted = $this->accounts->findDeletedByEmail($email);
            if ($this->isValidDeletedAccount($deleted, $password)) {
                $this->maybeSendDeletionAlert($deleted, $lang);
                return ['result' => 'deleted', 'account' => null, 'wait' => 0];
            }
        }

        // Vérification du mot de passe
        if (!$account || $account['password'] === null || !password_verify($password, (string) $account['password'])) {
            $this->recordLoginFailure($ipKey, $account);
            $this->handleAdminFailure($account, $ip);
            $this->handleBt2Lockout($account, $lang);
            return ['result' => 'bad_credentials', 'account' => null, 'wait' => 0];
        }

        // Email non vérifié : resend silencieux du token
        if (!$account['email_verified_at']) {
            $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
            $this->maybeSendVerificationEmail($account, $lang);
            return ['result' => 'unverified', 'account' => null, 'wait' => 0];
        }

        return ['result' => 'ok', 'account' => $account, 'wait' => 0];
    }

    /**
     * Réinitialise les compteurs de rate limiting après une connexion réussie.
     *
     * @param string               $ipKey   Clé de rate limiting de l'IP
     * @param array<string, mixed> $account Données du compte connecté
     * @return void
     */
    public function resetCounters(string $ipKey, array $account): void
    {
        $this->rateLimiter->reset($ipKey);
        $this->rateLimiter->reset('account_lockout:' . (int) $account['id']);
        if (in_array($account['role'] ?? '', ['admin', 'super_admin'], true)) {
            $this->securityAlert->resetFailedLoginCount((int) $account['id']);
        }
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Vérifie si le compte supprimé est valide pour l'alerte de réactivation.
     *
     * @param array<string, mixed>|false $deleted  Données du compte supprimé (false si absent)
     * @param string                     $password Mot de passe saisi
     * @return bool True si le compte supprimé est valide et le mot de passe correct
     */
    private function isValidDeletedAccount(array|false $deleted, string $password): bool
    {
        return $deleted !== false
            && $deleted['password'] !== null
            && password_verify($password, (string) $deleted['password'])
            && !empty($deleted['scheduled_deletion_at'])
            && strtotime((string) $deleted['scheduled_deletion_at']) > time();
    }

    /**
     * Envoie l'email d'alerte de suppression programmée (une seule fois par session).
     *
     * @param array<string, mixed> $deleted Données du compte supprimé
     * @param string               $lang    Code langue
     * @return void
     */
    private function maybeSendDeletionAlert(array $deleted, string $lang): void
    {
        $alertKey = 'deletion_alert_sent_' . (int) $deleted['id'];
        if (!empty($_SESSION[$alertKey]) || empty($deleted['reactivation_token'])) {
            return;
        }

        $name     = (string) ($deleted['firstname'] ?? ($deleted['company_name'] ?? ''));
        $langUsed = in_array((string) ($deleted['lang'] ?? ''), ['fr', 'en'], true)
            ? (string) $deleted['lang']
            : $lang;

        try {
            $this->mailer->sendPendingDeletionLoginAlert(
                (string) $deleted['email'],
                $name,
                $langUsed,
                (string) $deleted['reactivation_token'],
                (string) $deleted['scheduled_deletion_at']
            );
        } catch (\Throwable $e) {
            ErrorLogger::warning($e);
        }

        $_SESSION[$alertKey] = true;
    }

    /**
     * Enregistre un échec de connexion sur le bucket IP et sur le bucket compte.
     *
     * @param string               $ipKey   Clé de rate limiting de l'IP
     * @param array<string, mixed>|false $account Données du compte (false si inconnu)
     * @return void
     */
    private function recordLoginFailure(string $ipKey, array|false $account): void
    {
        $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
        if ($account) {
            $accountKey = 'account_lockout:' . (int) $account['id'];
            $this->rateLimiter->recordAttempt($accountKey, self::ACCOUNT_LOCKOUT_WINDOW);
        }
    }

    /**
     * Gère les alertes et le blocage automatique pour les comptes admin en cas d'échec.
     *
     * @param array<string, mixed>|false $account Données du compte (false si inconnu)
     * @param string                     $ip      Adresse IP de la requête
     * @return void
     */
    private function handleAdminFailure(array|false $account, string $ip): void
    {
        if (!$account || !in_array($account['role'] ?? '', ['admin', 'super_admin'], true)) {
            return;
        }

        if ($this->securityAlert->shouldAlertOnFailedLogins((int) $account['id'])) {
            $this->securityAlert->sendAdminLoginAlert($account, $ip);
        }

        $this->securityAlert->recordAutoBlockFailure((int) $account['id']);

        if ($this->securityAlert->shouldAutoBlock((int) $account['id'])) {
            $this->accounts->blockAccount((int) $account['id']);
            $displayName = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''))
                ?: ($account['company_name'] ?? (string) $account['email']);
            try {
                $this->mailer->sendAdminAccountBlocked(
                    (string) $account['email'],
                    $displayName,
                    (string) ($account['lang'] ?? 'fr'),
                    'Système (blocage automatique)'
                );
            } catch (\Throwable $e) {
                ErrorLogger::warning($e);
            }
        }
    }

    /**
     * Applique le lockout BT2 (30 min après N échecs) et envoie l'email de compte verrouillé.
     *
     * N'a aucun effet si le compte est inconnu ou si le seuil n'est pas encore atteint.
     *
     * @param array<string, mixed>|false $account Données du compte (false si inconnu)
     * @param string                     $lang    Code langue
     * @return void
     */
    private function handleBt2Lockout(array|false $account, string $lang): void
    {
        if (!$account) {
            return;
        }

        $accountKey = 'account_lockout:' . (int) $account['id'];
        if ($this->rateLimiter->checkLimit($accountKey, self::MAX_ACCOUNT_FAILURES, self::ACCOUNT_LOCKOUT_WINDOW)) {
            // Seuil pas encore atteint
            return;
        }

        $displayName = $account['account_type'] === 'company'
            ? ($account['company_name'] ?? '')
            : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
        $resetUrl = APP_URL . "/{$lang}/mot-de-passe-oublie";

        try {
            $this->mailer->sendAccountLocked(
                (string) $account['email'],
                $displayName,
                $lang,
                $resetUrl
            );
        } catch (\Throwable $e) {
            ErrorLogger::warning($e);
        }
    }

    /**
     * Renvoie silencieusement le token de vérification d'email si le quota le permet.
     *
     * Anti-énumération : la réponse du contrôleur reste identique qu'un renvoi ait eu lieu ou non.
     *
     * @param array<string, mixed> $account Données du compte non vérifié
     * @param string               $lang    Code langue
     * @return void
     */
    private function maybeSendVerificationEmail(array $account, string $lang): void
    {
        $resendKey = 'resend_verif:' . (int) $account['id'];
        if (!$this->rateLimiter->checkLimit($resendKey, self::MAX_RESEND_VERIF, self::WINDOW_RESEND_VERIF)) {
            return;
        }

        $newToken    = bin2hex(random_bytes(32));
        $this->accounts->refreshVerificationToken((int) $account['id'], $newToken);
        $verifyUrl   = APP_URL . "/{$lang}/verification/{$newToken}";
        $displayName = $account['account_type'] === 'company'
            ? ($account['company_name'] ?? '')
            : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));

        try {
            $this->mailer->sendEmailVerification(
                (string) $account['email'],
                $displayName,
                $verifyUrl,
                $lang
            );
        } catch (\Throwable $e) {
            ErrorLogger::warning($e);
        }
    }
}
