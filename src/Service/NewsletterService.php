<?php

declare(strict_types=1);

namespace Service;

use Core\Exception\NewsletterException;
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

    /** Fragment de chemin URL pour la confirmation (évite la duplication — SonarCloud php:S1192). */
    private const CONFIRMATION_PATH = '/newsletter/confirmation?token=';

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
     * @throws NewsletterException 'already_confirmed' — abonnement déjà actif
     * @throws NewsletterException 'rate_limit'        — trop de tentatives en 24h (visiteurs)
     * @return void
     */
    public function subscribe(string $email, string $lang, string $ip): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback dev local, APP_URL est https en production

        // Flux accounts — email rattaché à un compte existant
        $account = $this->accounts->findByEmail($email);
        if (is_array($account)) {
            if ((int) ($account['newsletter'] ?? 0) === 1) {
                throw NewsletterException::alreadyConfirmed();
            }
            $this->sendAccountConfirmationEmail($account, $email, $lang, $appUrl);
            return;
        }

        // Flux visiteur — rate limiting
        $attempts = $this->model->countRecentAttempts($email);
        if ($attempts >= self::MAX_RESEND_PER_DAY) {
            throw NewsletterException::rateLimitExceeded();
        }

        // Vérifier si déjà confirmé dans newsletter_subscriptions
        $existing = $this->model->findByEmail($email);
        if ($existing !== null && (int) $existing['newsletter_confirmed'] === 1) {
            throw NewsletterException::alreadyConfirmed();
        }

        [$rawToken, $hashedToken, $expiresAt] = $this->generateToken();
        $this->model->upsertPending($email, $hashedToken, $expiresAt, $ip, $lang);
        $confirmUrl = $appUrl . '/' . $lang . self::CONFIRMATION_PATH . urlencode($rawToken);
        $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
    }

    /**
     * Confirme un abonnement newsletter via le token de confirmation.
     *
     * Essaie d'abord le flux visiteur (token SHA-256 dans newsletter_subscriptions),
     * puis le flux accounts (token brut dans accounts.newsletter_confirm_token).
     *
     * @param string $rawToken Token brut reçu dans l'URL (non haché)
     * @throws NewsletterException 'invalid' — token inconnu dans les deux tables
     * @throws NewsletterException 'expired' — token expiré (flux visiteur)
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
                throw NewsletterException::tokenExpired();
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

        throw NewsletterException::invalidToken();
    }

    /**
     * Renvoie l'email de confirmation à un abonné en état pending.
     *
     * Pour les comptes : régénère le token dans accounts.
     * Pour les visiteurs : respecte le rate limiting (3 max / 24h).
     *
     * @param string $email Adresse email
     * @param string $lang  Langue du destinataire
     * @throws NewsletterException 'rate_limit' — trop de tentatives (visiteurs)
     * @throws NewsletterException 'not_found'  — email inconnu ou déjà confirmé
     * @return void
     */
    public function resendConfirmation(string $email, string $lang): void
    {
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://crabitan.local', '/'); // NOSONAR — fallback dev local, APP_URL est https en production

        // Flux accounts
        $account = $this->accounts->findByEmail($email);
        if (is_array($account)) {
            if ((int) ($account['newsletter'] ?? 0) === 1) {
                throw NewsletterException::notFound();
            }
            $this->sendAccountConfirmationEmail($account, $email, $lang, $appUrl);
            return;
        }

        // Flux visiteur — rate limiting
        $attempts = $this->model->countRecentAttempts($email);
        if ($attempts >= self::MAX_RESEND_PER_DAY) {
            throw NewsletterException::rateLimitExceeded();
        }

        $subscription = $this->model->findByEmail($email);
        if ($subscription === null || (int) $subscription['newsletter_confirmed'] === 1) {
            throw NewsletterException::notFound();
        }

        [$rawToken, $hashedToken, $expiresAt] = $this->generateToken();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->model->upsertPending($email, $hashedToken, $expiresAt, $ip, $lang);
        $confirmUrl = $appUrl . '/' . $lang . self::CONFIRMATION_PATH . urlencode($rawToken);
        $this->mailer->sendNewsletterConfirmation($email, $confirmUrl, $lang);
    }

    /**
     * Envoie l'email de confirmation newsletter pour un compte existant.
     *
     * Génère un token brut, le stocke dans accounts, puis envoie le mail.
     * Factorisé pour éviter la duplication entre subscribe() et resendConfirmation().
     *
     * @param array<string, mixed> $account Données du compte
     * @param string               $email   Adresse email du destinataire
     * @param string               $lang    Langue du destinataire
     * @param string               $appUrl  URL de base de l'application
     * @return void
     */
    private function sendAccountConfirmationEmail(
        array $account,
        string $email,
        string $lang,
        string $appUrl
    ): void {
        $rawToken   = bin2hex(random_bytes(32));
        $this->accounts->storeNewsletterConfirmToken((int) $account['id'], $rawToken);
        $confirmUrl = $appUrl . '/' . $lang . self::CONFIRMATION_PATH . urlencode($rawToken);
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
