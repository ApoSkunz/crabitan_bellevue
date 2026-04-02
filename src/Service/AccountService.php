<?php

declare(strict_types=1);

namespace Service;

use Core\Exception\HttpException;
use Model\AccountModel;

/**
 * Service métier pour les opérations de compte utilisateur.
 *
 * Implémente le double opt-in pour le changement d'email :
 *  1. requestEmailChange() — vérifie le mot de passe, génère un token, envoie les emails
 *  2. confirmEmailChange() — valide le token et applique le changement effectif
 *
 * Sécurité :
 *  - Token haché en SHA-256 avant stockage, comparé via hash_equals()
 *  - TTL 24h
 *  - Rate limiting : max 3 demandes / 24h
 *  - Sessions invalidées après changement
 *  - Log d'audit RGPD à chaque opération
 */
class AccountService
{
    private const EMAIL_CHANGE_MAX_REQUESTS = 3;
    private const EMAIL_CHANGE_TTL_SECONDS  = 86400; // 24h

    private AccountModel $accountModel;
    private MailService $mailService;

    /**
     * @param AccountModel $accountModel Modèle compte (injectable pour les tests)
     * @param MailService  $mailService  Service email (injectable pour les tests)
     */
    public function __construct(AccountModel $accountModel, MailService $mailService)
    {
        $this->accountModel = $accountModel;
        $this->mailService  = $mailService;
    }

    /**
     * Initie une demande de changement d'email avec double opt-in.
     *
     * Vérifie le mot de passe actuel, le rate limit, l'unicité de la nouvelle adresse,
     * génère un token cryptographique, l'enregistre en BDD (haché SHA-256),
     * envoie un email de confirmation à la nouvelle adresse ET un email d'alerte
     * à l'ancienne adresse.
     *
     * @param int    $userId          Identifiant du compte
     * @param string $newEmail        Nouvelle adresse email souhaitée
     * @param string $currentPassword Mot de passe actuel en clair
     * @param string $ip              Adresse IP du demandeur (log RGPD)
     * @return void
     * @throws HttpException 422 si mot de passe incorrect, email déjà pris ou rate limit atteint
     * @throws HttpException 404 si le compte n'existe pas
     */
    public function requestEmailChange(
        int $userId,
        string $newEmail,
        string $currentPassword,
        string $ip
    ): void {
        $account = $this->accountModel->findById($userId);
        if (!$account) {
            throw new HttpException(404, 'Compte introuvable.');
        }

        // Vérification du mot de passe
        if (
            $account['password'] === null
            || !password_verify($currentPassword, (string) $account['password'])
        ) {
            throw new HttpException(422, null, 'Mot de passe incorrect.');
        }

        // Rate limiting : max 3 demandes par 24h
        $requestCount = $this->accountModel->countEmailChangeRequestsLast24h($userId);
        if ($requestCount >= self::EMAIL_CHANGE_MAX_REQUESTS) {
            throw new HttpException(429, null, 'Trop de demandes de changement d\'email. Réessayez dans 24h.');
        }

        // Vérification unicité de la nouvelle adresse
        $existing = $this->accountModel->findByEmail($newEmail);
        if ($existing !== false) {
            throw new HttpException(422, null, 'Cette adresse email est déjà utilisée.');
        }

        // Génération du token (32 octets aléatoires → 64 hex chars)
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expiresAt   = date('Y-m-d H:i:s', time() + self::EMAIL_CHANGE_TTL_SECONDS);

        // Persistance
        $this->accountModel->saveEmailChangeToken(
            $userId,
            $hashedToken,
            $newEmail,
            $expiresAt
        );

        // Log audit (demande initiale)
        $this->accountModel->logAuditEvent(
            $userId,
            'email_change_request',
            $ip,
            ['new_email' => $newEmail]
        );

        // Construction des URLs
        $appUrl     = defined('APP_URL') ? APP_URL : ($_ENV['APP_URL'] ?? 'http://localhost');
        $lang       = (string) ($account['lang'] ?? 'fr');
        $confirmUrl = rtrim($appUrl, '/') . "/{$lang}/mon-compte/email/confirmer?token={$rawToken}";
        $revokeUrl  = rtrim($appUrl, '/') . "/{$lang}/mon-compte/email/revoquer?token={$rawToken}";

        $displayName = $this->resolveDisplayName($account);

        // Email de confirmation → ancienne adresse (identité prouvée — anti account-takeover)
        $this->mailService->sendEmailChangeConfirmation(
            (string) $account['email'],
            $displayName,
            $confirmUrl,
            $lang,
            $newEmail,
            $revokeUrl
        );

        // Email de notification → nouvelle adresse (information simple, aucune action requise)
        $this->mailService->sendEmailChangeNotification($newEmail, $lang);
    }

    /**
     * Confirme le changement d'email après clic sur le lien de confirmation.
     *
     * Hache le token brut reçu, le compare en base avec hash_equals(),
     * vérifie l'expiration et l'usage unique, puis :
     *  - Met à jour l'email du compte
     *  - Marque le token comme utilisé
     *  - Révoque toutes les sessions actives
     *  - Enregistre un événement d'audit RGPD
     *
     * @param string $rawToken Token brut reçu depuis l'URL de confirmation
     * @return void
     * @throws HttpException 410 si le token est invalide, expiré ou déjà utilisé
     */
    public function confirmEmailChange(string $rawToken): void
    {
        $hashedToken = hash('sha256', $rawToken);

        $row = $this->accountModel->findByEmailChangeToken($hashedToken);
        if (!$row) {
            throw new HttpException(410, 'Lien de confirmation invalide.');
        }

        // Vérification token à usage unique
        if ($row['email_change_used_at'] !== null) {
            throw new HttpException(410, 'Ce lien de confirmation a déjà été utilisé.');
        }

        // Vérification TTL
        if (strtotime((string) $row['email_change_expires_at']) < time()) {
            throw new HttpException(410, 'Ce lien de confirmation a expiré.');
        }

        $userId   = (int) $row['id'];
        $newEmail = (string) $row['email_change_new_email'];

        // Application du changement + marquage token utilisé
        $this->accountModel->applyEmailChange($userId, $newEmail);

        // Révocation de toutes les sessions (critère 8)
        $this->accountModel->revokeAllSessions($userId);

        // Log audit RGPD (critère 9)
        $this->accountModel->logAuditEvent(
            $userId,
            'email_changed',
            '', // IP non disponible lors de la confirmation (lien email)
            ['old_email' => $row['email'], 'new_email' => $newEmail]
        );
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Résout le nom d'affichage à partir des données du compte.
     *
     * @param array<string, mixed> $account Données du compte
     * @return string Nom d'affichage (prénom + nom ou raison sociale ou 'Client')
     */
    private function resolveDisplayName(array $account): string
    {
        $name = trim(
            ($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? '')
        );
        if ($name !== '') {
            return $name;
        }
        return (string) ($account['company_name'] ?? 'Client');
    }
}
