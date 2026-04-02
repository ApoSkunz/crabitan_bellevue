<?php

declare(strict_types=1);

namespace Service;

use Model\AccountModel;
use Model\NewsletterSubscriptionModel;

/**
 * Service de gestion des abonnements newsletter avec double opt-in.
 *
 * Flux RGPD Art. 7 :
 *   1. subscribe()             → génère token, statut pending, envoie email de confirmation
 *   2. confirmSubscription()   → vérifie hash + TTL, marque confirmed, enregistre preuve
 *   3. resendConfirmation()    → renvoie l'email (max 3 fois / 24h par visiteur)
 *
 * Routage selon le type d'abonné :
 *   - Email connu dans accounts → flux accounts (newsletter_confirm_token sur le compte)
 *   - Email inconnu             → flux visiteur (table newsletter_subscriptions)
 *
 * Sécurité :
 *   - Token brut : bin2hex(random_bytes(32)) — jamais stocké en BDD
 *   - Token haché : hash('sha256', $token) — stocké en BDD (flux visiteur)
 *   - Comparaison : hash_equals() — résistant aux timing attacks
 *   - Rate limiting visiteurs : 3 renvois max / 24h par email
 */
class NewsletterService
{
    /** Durée de vie du token de confirmation en secondes (48h). */
    private const TOKEN_TTL_SECONDS = 172800;

    /** Nombre maximal de renvois de confirmation par 24h (visiteurs uniquement). */
    private const MAX_RESEND_PER_DAY = 3;

    private NewsletterSubscriptionModel $model;
    private MailService $mailer;
    private AccountModel $accounts;

    /**
     * @param NewsletterSubscriptionModel $model    Modèle abonnements visiteurs
     * @param MailService                 $mailer   Service d'envoi email
     * @param AccountModel                $accounts Modèle comptes utilisateurs
     */
    public function __construct(
        NewsletterSubscriptionModel $model,
        MailService $mailer,
        AccountModel $accounts
    ) {
        $this->model    = $model;
        $this->mailer   = $mailer;
        $this->accounts = $accounts;
    }

    /**
     * Inscrit un email à la newsletter et envoie l'email de confirmation.
     *
     * Si l'email appartient à un compte : met à jour accounts.newsletter_confirm_token.
     * Sinon : flux visiteur via newsletter_subscriptions avec rate limiting.
     *
     * @param string $email Adresse email à inscrire
     * @param string $lang  Langue du destinataire ('fr' ou 'en')
     * @param string $ip    Adresse IP de la requête (pour la preuve RGPD)
     * @throws \RuntimeException 'already_confirmed' — abonnement déjà actif
     * @throws \RuntimeException 'rate_limit'        — trop de tentatives en 24h (visiteurs)
     * @return void
     */
    public function subscribe(string $email, string $lang, string $ip): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/');

        // Flux accounts — email rattaché à un compte existant
        $account = $this->accounts->findByEmail($email);
        if (is_array($account)) {
            if ((int) ($account['newsletter'] ?? 0) === 1) {
                throw new \RuntimeException('already_confirmed');
            }
            $rawToken   = bin2hex(random_bytes(32));
            $this->accounts->storeNewsletterConfirmToken((int) $account['id'], $rawToken);
            $confirmUrl = $appUrl . '/' . $lang . '/newsletter/confirmation?token=' . urlencode($rawToken);
            $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
            return;
        }

        // Flux visiteur — rate limiting
        $attempts = $this->model->countRecentAttempts($email);
        if ($attempts >= self::MAX_RESEND_PER_DAY) {
            throw new \RuntimeException('rate_limit');
        }

        // Vérifier si déjà confirmé dans newsletter_subscriptions
        $existing = $this->model->findByEmail($email);
        if ($existing !== null && (int) $existing['newsletter_confirmed'] === 1) {
            throw new \RuntimeException('already_confirmed');
        }

        [$rawToken, $hashedToken, $expiresAt] = $this->generateToken();
        $this->model->upsertPending($email, $hashedToken, $expiresAt, $ip, $lang);
        $confirmUrl = $appUrl . '/' . $lang . '/newsletter/confirmation?token=' . urlencode($rawToken);
        $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
    }

    /**
     * Confirme un abonnement newsletter via le token de confirmation.
     *
     * Essaie d'abord le flux visiteur (token SHA-256 dans newsletter_subscriptions),
     * puis le flux accounts (token brut dans accounts.newsletter_confirm_token).
     *
     * @param string $rawToken Token brut reçu dans l'URL (non haché)
     * @throws \RuntimeException 'invalid' — token inconnu dans les deux tables
     * @throws \RuntimeException 'expired' — token expiré (flux visiteur)
     * @return void
     */
    public function confirmSubscription(string $rawToken): void
    {
        $hashedToken  = hash('sha256', $rawToken);
        $subscription = $this->model->findPendingByTokenHash($hashedToken);

        if ($subscription !== null) {
            // Flux visiteur — vérification TTL
            $expiresAt = new \DateTimeImmutable($subscription['newsletter_token_expires_at']);
            if ($expiresAt < new \DateTimeImmutable()) {
                throw new \RuntimeException('expired');
            }
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $this->model->confirmByTokenHash($hashedToken, $ip);
            return;
        }

        // Flux accounts — token brut stocké dans accounts.newsletter_confirm_token
        $account = $this->accounts->confirmNewsletterByToken($rawToken);
        if (is_array($account)) {
            return;
        }

        throw new \RuntimeException('invalid');
    }

    /**
     * Renvoie l'email de confirmation à un abonné en état pending.
     *
     * Pour les comptes : régénère le token dans accounts.
     * Pour les visiteurs : respecte le rate limiting (3 max / 24h).
     *
     * @param string $email Adresse email
     * @param string $lang  Langue du destinataire
     * @throws \RuntimeException 'rate_limit' — trop de tentatives (visiteurs)
     * @throws \RuntimeException 'not_found'  — email inconnu ou déjà confirmé
     * @return void
     */
    public function resendConfirmation(string $email, string $lang): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/');

        // Flux accounts
        $account = $this->accounts->findByEmail($email);
        if (is_array($account)) {
            if ((int) ($account['newsletter'] ?? 0) === 1) {
                throw new \RuntimeException('not_found');
            }
            $rawToken   = bin2hex(random_bytes(32));
            $this->accounts->storeNewsletterConfirmToken((int) $account['id'], $rawToken);
            $confirmUrl = $appUrl . '/' . $lang . '/newsletter/confirmation?token=' . urlencode($rawToken);
            $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
            return;
        }

        // Flux visiteur — rate limiting
        $attempts = $this->model->countRecentAttempts($email);
        if ($attempts >= self::MAX_RESEND_PER_DAY) {
            throw new \RuntimeException('rate_limit');
        }

        $subscription = $this->model->findByEmail($email);
        if ($subscription === null || (int) $subscription['newsletter_confirmed'] === 1) {
            throw new \RuntimeException('not_found');
        }

        [$rawToken, $hashedToken, $expiresAt] = $this->generateToken();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->model->upsertPending($email, $hashedToken, $expiresAt, $ip, $lang);
        $confirmUrl = $appUrl . '/' . $lang . '/newsletter/confirmation?token=' . urlencode($rawToken);
        $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
    }

    /**
     * Génère un token brut, son hash SHA-256 et sa date d'expiration.
     *
     * @return array{string, string, string} [rawToken, hashedToken, expiresAt]
     */
    private function generateToken(): array
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt   = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        return [$rawToken, $hashedToken, $expiresAt];
    }
}
