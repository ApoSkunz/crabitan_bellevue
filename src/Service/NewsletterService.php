<?php

declare(strict_types=1);

namespace Service;

use Model\NewsletterSubscriptionModel;

/**
 * Service de gestion des abonnements newsletter avec double opt-in.
 *
 * Flux RGPD Art. 7 :
 *   1. subscribe()             → génère token, statut pending, envoie email de confirmation
 *   2. confirmSubscription()   → vérifie hash + TTL, marque confirmed, enregistre preuve
 *   3. resendConfirmation()    → renvoie l'email (max 3 fois / 24h, rate limiting)
 *
 * Sécurité :
 *   - Token brut : bin2hex(random_bytes(32)) — jamais stocké en BDD
 *   - Token haché : hash('sha256', $token) — stocké en BDD
 *   - Comparaison : hash_equals() — résistant aux timing attacks
 *   - Rate limiting : 3 renvois max / 24h par email
 */
class NewsletterService
{
    /** Durée de vie du token de confirmation en secondes (48h). */
    private const TOKEN_TTL_SECONDS = 172800;

    /** Nombre maximal de renvois de confirmation par 24h. */
    private const MAX_RESEND_PER_DAY = 3;

    private NewsletterSubscriptionModel $model;
    private MailService $mailer;

    /**
     * @param NewsletterSubscriptionModel $model  Modèle d'accès BDD
     * @param MailService                 $mailer Service d'envoi email
     */
    public function __construct(
        NewsletterSubscriptionModel $model,
        MailService $mailer
    ) {
        $this->model  = $model;
        $this->mailer = $mailer;
    }

    /**
     * Inscrit un email à la newsletter et envoie l'email de confirmation.
     *
     * Vérifie le rate limiting (3 envois max / 24h) avant tout traitement.
     * Si l'email est déjà confirmé, lève une exception pour informer l'utilisateur.
     * Si l'email est en état pending, renouvelle le token et renvoie l'email.
     *
     * @param string $email Adresse email à inscrire
     * @param string $lang  Langue du destinataire ('fr' ou 'en')
     * @param string $ip    Adresse IP de la requête (pour la preuve RGPD)
     * @throws \RuntimeException 'rate_limit'       — trop de tentatives en 24h
     * @throws \RuntimeException 'already_confirmed' — abonnement déjà actif
     * @return void
     */
    public function subscribe(string $email, string $lang, string $ip): void
    {
        // Rate limiting — 3 envois max / 24h
        $attempts = $this->model->countRecentAttempts($email);
        if ($attempts >= self::MAX_RESEND_PER_DAY) {
            throw new \RuntimeException('rate_limit');
        }

        // Vérifier si déjà confirmé
        $existing = $this->model->findByEmail($email);
        if ($existing !== null && (int) $existing['newsletter_confirmed'] === 1) {
            throw new \RuntimeException('already_confirmed');
        }

        // Génération du token et stockage haché
        [$rawToken, $hashedToken, $expiresAt] = $this->generateToken();

        $this->model->upsertPending($email, $hashedToken, $expiresAt, $ip, $lang);

        // Envoi de l'email de confirmation
        $appUrl       = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/');
        $confirmUrl   = $appUrl . '/' . $lang . '/newsletter/confirmation?token=' . urlencode($rawToken);

        $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
    }

    /**
     * Confirme un abonnement newsletter via le token de confirmation.
     *
     * Vérifie le hash (timing-safe) et le TTL. Enregistre la preuve RGPD
     * (date + IP) lors de la confirmation.
     *
     * @param string $rawToken Token brut reçu dans l'URL (non haché)
     * @throws \RuntimeException 'invalid' — token inconnu ou déjà confirmé
     * @throws \RuntimeException 'expired' — token expiré (TTL 48h dépassé)
     * @return void
     */
    public function confirmSubscription(string $rawToken): void
    {
        $hashedToken = hash('sha256', $rawToken);

        $subscription = $this->model->findPendingByTokenHash($hashedToken);

        if ($subscription === null) {
            throw new \RuntimeException('invalid');
        }

        // Vérification timing-safe du hash
        if (!hash_equals($subscription['newsletter_token_hash'], $hashedToken)) {
            throw new \RuntimeException('invalid'); // @codeCoverageIgnore
        }

        // Vérification du TTL
        $expiresAt = new \DateTimeImmutable($subscription['newsletter_token_expires_at']);
        if ($expiresAt < new \DateTimeImmutable()) {
            throw new \RuntimeException('expired');
        }

        // IP lors de la confirmation (fournie par le controller via $_SERVER)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->model->confirmByTokenHash($hashedToken, $ip);
    }

    /**
     * Renvoie l'email de confirmation à un abonné en état pending.
     *
     * Respecte le rate limiting : 3 envois max / 24h.
     * Si l'abonnement n'existe pas ou est déjà confirmé, lève une exception.
     *
     * @param string $email Adresse email
     * @param string $lang  Langue du destinataire
     * @throws \RuntimeException 'rate_limit'       — trop de tentatives en 24h
     * @throws \RuntimeException 'not_found'        — email inconnu ou déjà confirmé
     * @return void
     */
    public function resendConfirmation(string $email, string $lang): void
    {
        $attempts = $this->model->countRecentAttempts($email);
        if ($attempts >= self::MAX_RESEND_PER_DAY) {
            throw new \RuntimeException('rate_limit');
        }

        $subscription = $this->model->findByEmail($email);
        if ($subscription === null || (int) $subscription['newsletter_confirmed'] === 1) {
            throw new \RuntimeException('not_found');
        }

        // Regénérer un nouveau token
        [$rawToken, $hashedToken, $expiresAt] = $this->generateToken();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->model->upsertPending($email, $hashedToken, $expiresAt, $ip, $lang);

        $appUrl     = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/');
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
