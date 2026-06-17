<?php

declare(strict_types=1);

namespace Model;

use Core\Model;
use Service\EncryptionService;

/**
 * Modèle de gestion des comptes utilisateurs.
 * Couvre la création, l'authentification, la mise à jour du profil,
 * la suppression programmée (RGPD) et la gestion des tokens de sécurité.
 */
class AccountModel extends Model // NOSONAR php:S1448 — regroupement intentionnel ; découpage prévu à l'audit génie logiciel
{
    protected string $table = 'accounts';
    private EncryptionService $encryption;

    /**
     * @param EncryptionService|null $encryption Service de chiffrement (null = construit depuis l'environnement)
     */
    public function __construct(?EncryptionService $encryption = null)
    {
        parent::__construct();
        $this->encryption = $encryption ?? EncryptionService::fromEnv();
    }

    private function withProfile(): string
    {
        return "SELECT a.id, a.email, a.password, a.account_type, a.role, a.lang,
                       a.newsletter, a.newsletter_unsubscribe_token, a.newsletter_optin_pending,
                       a.newsletter_confirm_token, a.newsletter_confirm_expires_at,
                       a.email_verification_token, a.email_verification_token_expires_at,
                       a.email_verified_at, a.email_change_token, a.email_change_new_email,
                       a.email_change_expires_at, a.email_change_used_at,
                       a.google_id, a.apple_id,
                       a.majority_declared_at, a.majority_declared_ip,
                       a.deleted_at, a.scheduled_deletion_at,
                       a.reactivation_token, a.reactivation_token_expires_at,
                       a.encryption_version, a.inactivity_warning_sent_at,
                       a.deletion_reminder_7d_sent_at, a.deletion_reminder_1d_sent_at,
                       a.pending_password_hash, a.pending_password_expires_at,
                       a.pending_password_session_token,
                       a.security_confirm_token_hash, a.security_confirm_token_expires_at,
                       a.is_blocked, a.created_at, a.updated_at, a.has_connected,
                       ai.lastname, ai.firstname, ai.civility,
                       ac.company_name, ac.siret
                FROM {$this->table} a
                LEFT JOIN account_individuals ai ON ai.account_id = a.id
                LEFT JOIN account_companies   ac ON ac.account_id = a.id";
    }

    /**
     * Trouve un compte actif par son adresse email.
     *
     * @param string $email Adresse email
     * @return array<string, mixed>|false Compte trouvé (avec profil) ou false
     */
    public function findByEmail(string $email): array|false
    {
        $row = $this->db->fetchOne(
            $this->withProfile() . " WHERE a.email = ? AND a.deleted_at IS NULL",
            [$email]
        );
        return is_array($row) ? $this->decryptRow($row) : $row;
    }

    /**
     * Trouve un compte actif par son identifiant.
     *
     * @param int $id Identifiant du compte
     * @return array<string, mixed>|false Compte trouvé (avec profil) ou false
     */
    public function findById(int $id): array|false
    {
        $row = $this->db->fetchOne(
            $this->withProfile() . " WHERE a.id = ? AND a.deleted_at IS NULL",
            [$id]
        );
        return is_array($row) ? $this->decryptRow($row) : $row;
    }

    /**
     * Trouve un compte soft-deleted par son identifiant (pour restauration admin).
     *
     * @param int $id Identifiant du compte
     * @return array<string, mixed>|false
     */
    public function findDeletedById(int $id): array|false
    {
        $row = $this->db->fetchOne(
            $this->withProfile() . " WHERE a.id = ? AND a.deleted_at IS NOT NULL",
            [$id]
        );
        return is_array($row) ? $this->decryptRow($row) : $row;
    }

    /**
     * Déchiffre les colonnes sensibles d'un compte retourné par la BDD.
     *
     * @param array<string, mixed> $row Ligne brute issue d'un SELECT
     * @return array<string, mixed> Ligne avec colonnes déchiffrées
     */
    private function decryptRow(array $row): array
    {
        if (isset($row['email_change_new_email']) && is_string($row['email_change_new_email'])) {
            $row['email_change_new_email'] = $this->encryption->decrypt($row['email_change_new_email'])
                ?? $row['email_change_new_email'];
        }

        if (isset($row['majority_declared_ip']) && is_string($row['majority_declared_ip'])) {
            $row['majority_declared_ip'] = $this->encryption->decrypt($row['majority_declared_ip'])
                ?? $row['majority_declared_ip'];
        }

        return $row;
    }

    /**
     * Trouve un compte par son token de vérification email, uniquement si non expiré.
     *
     * @param string $token Token de vérification (64 hex chars)
     * @return array<string, mixed>|false Compte trouvé ou false si invalide/expiré
     */
    public function findByVerificationToken(string $token): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.email_verification_token = ?
              AND a.deleted_at IS NULL
              AND (a.email_verification_token_expires_at IS NULL
                   OR a.email_verification_token_expires_at > NOW())",
            [$token]
        );
    }

    /**
     * Trouve un compte actif par son identifiant Google OAuth.
     *
     * @param string $googleId Identifiant Google (sub)
     * @return array<string, mixed>|false
     */
    public function findByGoogleId(string $googleId): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.google_id = ? AND a.deleted_at IS NULL",
            [$googleId]
        );
    }

    /**
     * Cherche un compte en cours de suppression (soft-delete) par google_id.
     *
     * @param string $googleId Identifiant Google (sub)
     * @return array<string, mixed>|false
     */
    public function findDeletedByGoogleId(string $googleId): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.google_id = ? AND a.deleted_at IS NOT NULL",
            [$googleId]
        );
    }

    /**
     * Cherche un compte en cours de suppression (soft-delete) par email.
     *
     * @param string $email Adresse email
     * @return array<string, mixed>|false
     */
    public function findDeletedByEmail(string $email): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.email = ? AND a.deleted_at IS NOT NULL",
            [$email]
        );
    }

    /**
     * Rattache un google_id à un compte existant.
     *
     * @param int    $accountId Identifiant du compte
     * @param string $googleId  Identifiant Google (sub)
     */
    public function linkGoogleId(int $accountId, string $googleId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET google_id = ? WHERE id = ?",
            [$googleId, $accountId]
        );
    }

    /**
     * Crée un compte depuis Google OAuth (email pré-vérifié, pas de mot de passe).
     *
     * @param string $email       Adresse email Google
     * @param string $googleId    Identifiant Google (sub)
     * @param string $lang        Langue de l'interface (fr|en)
     * @param string $firstname   Prénom fourni par Google
     * @param string $lastname    Nom de famille fourni par Google
     * @param string $accountType Type de compte : 'individual' ou 'company'
     * @param string $companyName Nom de la société (requis si $accountType = 'company')
     * @return int Identifiant du compte créé
     */
    public function createFromGoogle(
        string $email,
        string $googleId,
        string $lang,
        string $firstname,
        string $lastname,
        string $accountType = 'individual',
        string $companyName = ''
    ): int {
        $this->db->beginTransaction();
        try {
            $accountId = $this->db->insert(
                "INSERT INTO {$this->table}
                 (email, password, account_type, role, lang, newsletter, google_id,
                  email_verified_at, newsletter_unsubscribe_token)
                 VALUES (?, NULL, ?, 'customer', ?, 0, ?, NOW(), ?)",
                [$email, $accountType, $lang, $googleId, bin2hex(random_bytes(32))]
            );

            if ($accountType === 'company') {
                $this->db->insert(
                    "INSERT INTO account_companies (account_id, company_name)
                     VALUES (?, ?)",
                    [(int) $accountId, $companyName ?: 'Société']
                );
            } else {
                $this->db->insert(
                    "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                     VALUES (?, ?, ?, NULL)",
                    [(int) $accountId, $lastname ?: 'Google', $firstname ?: 'Utilisateur']
                );
            }

            $this->db->commit();
            return (int) $accountId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Trouve un compte actif par son identifiant Apple Sign-In.
     *
     * @param string $appleId Identifiant Apple (sub)
     * @return array<string, mixed>|false
     */
    public function findByAppleId(string $appleId): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.apple_id = ? AND a.deleted_at IS NULL",
            [$appleId]
        );
    }

    /**
     * Crée un nouveau compte client avec token de vérification email (TTL 24 h).
     *
     * Newsletter : toujours à 0 à la création. Si $newsletter = 1, le consentement est
     * stocké dans newsletter_optin_pending et activé lors de la vérification email
     * (double opt-in via vérification adresse).
     *
     * @param string $accountType     Type de compte ('individual' ou 'company')
     * @param string $email           Adresse email
     * @param string $hashedPassword  Mot de passe hashé (bcrypt)
     * @param string $lang            Langue préférée ('fr' ou 'en')
     * @param int    $newsletter      Consentement newsletter coché à l'inscription (0 ou 1)
     * @param string $verificationToken Token de vérification email (64 hex chars)
     * @param string $civility        Civilité ('M', 'F', 'other') — particulier uniquement
     * @param string $lastname        Nom de famille — particulier uniquement
     * @param string $firstname       Prénom — particulier uniquement
     * @param string $companyName     Raison sociale — entreprise uniquement
     * @return string Identifiant du compte créé (cast en string depuis lastInsertId)
     * @throws \Throwable En cas d'erreur BDD (rollback automatique)
     */
    public function create( // NOSONAR — params nécessaires pour les deux types de compte, DTO prévu avec feat/account
        string $accountType,
        string $email,
        string $hashedPassword,
        string $lang,
        int $newsletter,
        string $verificationToken,
        string $civility,
        string $lastname,
        string $firstname,
        string $companyName
    ): string {
        $this->db->beginTransaction();
        try {
            $accountId = $this->db->insert(
                "INSERT INTO {$this->table}
                 (email, password, account_type, role, lang, newsletter, newsletter_optin_pending,
                  email_verification_token, email_verification_token_expires_at,
                  newsletter_unsubscribe_token)
                 VALUES (?, ?, ?, 'customer', ?, 0, ?, ?, NOW() + INTERVAL 24 HOUR, ?)",
                [$email, $hashedPassword, $accountType, $lang, $newsletter,
                 $verificationToken, bin2hex(random_bytes(32))]
            );

            if ($accountType === 'company') {
                $this->db->insert(
                    "INSERT INTO account_companies (account_id, company_name) VALUES (?, ?)",
                    [(int) $accountId, $companyName]
                );
            } else {
                $this->db->insert(
                    "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                     VALUES (?, ?, ?, ?)",
                    [(int) $accountId, $lastname, $firstname, $civility]
                );
            }

            $this->db->commit();
            return $accountId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Marque l'email d'un compte comme vérifié et invalide le token de vérification.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function verifyEmail(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET email_verified_at = NOW(),
                 email_verification_token = NULL,
                 email_verification_token_expires_at = NULL
             WHERE id = ?",
            [$id]
        );
    }

    /**
     * Régénère le token de vérification email d'un compte non vérifié.
     * Utilisé pour le renvoi silencieux à la connexion (anti-énumération).
     *
     * @param int    $id    Identifiant du compte
     * @param string $token Nouveau token brut (64 hex chars)
     * @return void
     */
    public function refreshVerificationToken(int $id, string $token): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET email_verification_token = ?,
                 email_verification_token_expires_at = NOW() + INTERVAL 24 HOUR
             WHERE id = ? AND email_verified_at IS NULL",
            [$token, $id]
        );
    }

    /**
     * Met à jour le mot de passe haché d'un compte.
     *
     * @param int    $id             Identifiant du compte
     * @param string $hashedPassword Nouveau mot de passe haché (bcrypt)
     * @return void
     */
    public function updatePassword(int $id, string $hashedPassword): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET password = ? WHERE id = ?",
            [$hashedPassword, $id]
        );
    }

    /**
     * Met à jour le profil d'un compte particulier.
     *
     * @param int    $id        Identifiant du compte
     * @param string $civility  Civilité ('M', 'F', 'other')
     * @param string $firstname Prénom
     * @param string $lastname  Nom de famille
     * @return void
     */
    public function updateIndividualProfile(int $id, string $civility, string $firstname, string $lastname): void
    {
        $this->db->execute(
            "UPDATE account_individuals SET civility = ?, firstname = ?, lastname = ?
             WHERE account_id = ?",
            [$civility, $firstname, $lastname, $id]
        );
    }

    /**
     * Met à jour le profil d'un compte entreprise.
     *
     * @param int         $id          Identifiant du compte
     * @param string      $companyName Raison sociale
     * @param string|null $siret       Numéro SIRET (optionnel)
     * @return void
     */
    public function updateCompanyProfile(int $id, string $companyName, ?string $siret): void
    {
        $this->db->execute(
            "UPDATE account_companies SET company_name = ?, siret = ? WHERE account_id = ?",
            [$companyName, $siret, $id]
        );
    }

    /**
     * Met à jour le consentement newsletter d'un compte.
     *
     * @param int  $id        Identifiant du compte
     * @param bool $subscribe true pour s'abonner, false pour se désabonner
     * @return void
     */
    public function updateNewsletter(int $id, bool $subscribe): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET newsletter = ? WHERE id = ?",
            [$subscribe ? 1 : 0, $id]
        );
    }

    /**
     * Révoque toutes les sessions actives d'un compte.
     * Utilisé lors de la suppression de compte.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function revokeAllSessions(int $id): void
    {
        // Utilisé lors de la suppression de compte pour révoquer toutes les sessions actives
        $this->db->execute(
            "UPDATE connections SET status = 'revoked' WHERE user_id = ? AND status = 'active'",
            [$id]
        );
    }

    /**
     * Marque un compte comme ayant effectué sa première connexion.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function markAsConnected(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET has_connected = 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * Met à jour la date de dernière connexion réussie.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function updateLastLoginAt(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET last_login_at = NOW(), inactivity_warning_sent_at = NULL WHERE id = ?",
            [$id]
        );
    }

    /**
     * Met à jour la langue préférée d'un compte.
     *
     * @param int    $id   Identifiant du compte
     * @param string $lang Code langue ('fr' ou 'en')
     * @return void
     */
    public function updateLang(int $id, string $lang): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET lang = ? WHERE id = ?",
            [$lang, $id]
        );
    }

    /**
     * Planifie la suppression d'un compte (soft-delete sur 30 jours).
     * Génère un token de réactivation pour annulation éventuelle.
     *
     * @param int $id Identifiant du compte
     * @return int Nombre de lignes modifiées
     */
    public function delete(int $id): int
    {
        return $this->db->execute(
            "UPDATE {$this->table}
             SET deleted_at = NOW(),
                 scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
                 reactivation_token = ?
             WHERE id = ?",
            [bin2hex(random_bytes(32)), $id]
        );
    }

    /**
     * Retourne le token de réactivation d'un compte supprimé.
     *
     * @param int $id Identifiant du compte
     * @return string|null Token de réactivation ou null
     */
    public function getReactivationToken(int $id): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT reactivation_token FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row !== false ? ($row['reactivation_token'] ?? null) : null;
    }

    /**
     * Stocke un nouveau mot de passe en attente de confirmation par email (TTL 1h).
     * Le token de session à l'origine de la demande est conservé pour révocation ciblée.
     *
     * @param  int    $userId       Identifiant du compte
     * @param  string $hash         Nouveau mot de passe haché (bcrypt)
     * @param  string $expiresAt    Date d'expiration au format 'Y-m-d H:i:s'
     * @param  string $sessionToken Token JWT de la session ayant initié la demande
     * @return void
     */
    public function savePendingPassword(
        int $userId,
        string $hash,
        string $expiresAt,
        string $sessionToken
    ): void {
        $this->db->execute(
            "UPDATE {$this->table}
             SET pending_password_hash            = ?,
                 pending_password_expires_at      = ?,
                 pending_password_session_token   = ?
             WHERE id = ?",
            [$hash, $expiresAt, $sessionToken, $userId]
        );
    }

    /**
     * Retourne le token de session à l'origine de la demande de changement MDP en attente.
     *
     * @param  int $userId Identifiant du compte
     * @return string|null Token JWT ou null si aucun pending
     */
    public function getPendingSessionToken(int $userId): string|null
    {
        $row = $this->db->fetchOne(
            "SELECT pending_password_session_token FROM {$this->table} WHERE id = ?",
            [$userId]
        );
        return $row ? ($row['pending_password_session_token'] ?? null) : null;
    }

    /**
     * Applique le mot de passe en attente et efface le pending (opération atomique).
     * Ne fait rien si aucun mot de passe en attente n'est présent.
     *
     * @param  int $userId Identifiant du compte
     * @return int Nombre de lignes modifiées (1 si appliqué, 0 si aucun pending)
     */
    public function applyPendingPassword(int $userId): int
    {
        return $this->db->execute(
            "UPDATE {$this->table}
             SET password                        = pending_password_hash,
                 pending_password_hash           = NULL,
                 pending_password_expires_at     = NULL,
                 pending_password_session_token  = NULL
             WHERE id = ? AND pending_password_hash IS NOT NULL",
            [$userId]
        );
    }

    /**
     * Efface le mot de passe en attente sans l'appliquer (action "Ce n'était pas moi").
     *
     * @param  int $userId Identifiant du compte
     * @return void
     */
    public function clearPendingPassword(int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET pending_password_hash            = NULL,
                 pending_password_expires_at      = NULL,
                 pending_password_session_token   = NULL
             WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Enregistre un token de confirmation d'action de sécurité (haché SHA-256, TTL 1h).
     *
     * @param  int    $userId    Identifiant du compte
     * @param  string $hash      Hash SHA-256 du token brut
     * @param  string $expiresAt Date d'expiration au format 'Y-m-d H:i:s'
     * @return void
     */
    public function saveSecurityConfirmToken(int $userId, string $hash, string $expiresAt): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET security_confirm_token_hash       = ?,
                 security_confirm_token_expires_at = ?
             WHERE id = ?",
            [$hash, $expiresAt, $userId]
        );
    }

    /**
     * Recherche un compte par son token de confirmation de sécurité (haché SHA-256), non expiré.
     *
     * @param  string $hash Hash SHA-256 du token brut
     * @return array<string, mixed>|false
     */
    public function findBySecurityConfirmToken(string $hash): array|false
    {
        return $this->db->fetchOne(
            "SELECT id, email, lang FROM {$this->table}
             WHERE security_confirm_token_hash = ?
               AND security_confirm_token_expires_at > UTC_TIMESTAMP()
               AND deleted_at IS NULL",
            [$hash]
        );
    }

    /**
     * Invalide le token de confirmation de sécurité après usage.
     *
     * @param  int $userId Identifiant du compte
     * @return void
     */
    public function consumeSecurityConfirmToken(int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET security_confirm_token_hash       = NULL,
                 security_confirm_token_expires_at = NULL
             WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Trouve un compte en attente de suppression par son token de réactivation.
     *
     * @param string $token Token de réactivation
     * @return array<string, mixed>|false Compte trouvé ou false
     */
    public function findByReactivationToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT id, email, lang FROM {$this->table}
             WHERE reactivation_token = ?
               AND deleted_at IS NOT NULL
               AND scheduled_deletion_at > NOW()",
            [$token]
        );
    }

    /**
     * Retourne les comptes dont la suppression programmée tombe dans une fenêtre
     * de ±windowHours autour de "maintenant + daysAhead jours" et pour lesquels
     * le rappel correspondant n'a pas encore été envoyé.
     *
     * Seuls les comptes encore en attente de suppression (deleted_at IS NOT NULL,
     * scheduled_deletion_at IS NOT NULL) et non réactivés sont inclus.
     *
     * @param int $daysAhead  7 (rappel J-7) ou 1 (rappel J-1)
     * @param int $windowHours Demi-largeur de la fenêtre en heures (défaut 12)
     * @return array<int, array<string, mixed>>
     */
    public function getAccountsPendingDeletionReminder(int $daysAhead, int $windowHours = 12): array
    {
        $column = match ($daysAhead) {
            7       => 'deletion_reminder_7d_sent_at',
            1       => 'deletion_reminder_1d_sent_at',
            default => throw new \InvalidArgumentException("daysAhead must be 1 or 7, got {$daysAhead}"),
        };

        $hoursMin = $daysAhead * 24 - $windowHours;
        $hoursMax = $daysAhead * 24 + $windowHours;

        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.lang, a.reactivation_token, a.scheduled_deletion_at,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '') AS lastname
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.deleted_at IS NOT NULL
               AND a.scheduled_deletion_at IS NOT NULL
               AND a.{$column} IS NULL
               AND a.scheduled_deletion_at BETWEEN DATE_ADD(NOW(), INTERVAL ? HOUR)
                                                AND DATE_ADD(NOW(), INTERVAL ? HOUR)
             ORDER BY a.scheduled_deletion_at ASC",
            [$hoursMin, $hoursMax]
        );
    }

    /**
     * Marque le rappel J-7 avant suppression comme envoyé.
     *
     * @param int $id Identifiant du compte
     */
    public function markDeletionReminder7dSent(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET deletion_reminder_7d_sent_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Marque le rappel J-1 avant suppression comme envoyé.
     *
     * @param int $id Identifiant du compte
     */
    public function markDeletionReminder1dSent(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET deletion_reminder_1d_sent_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Réactive un compte en annulant la suppression programmée.
     *
     * @param int $id Identifiant du compte
     * @return int Nombre de lignes modifiées
     */
    public function reactivate(int $id): int
    {
        return $this->db->execute(
            "UPDATE {$this->table}
             SET deleted_at = NULL,
                 scheduled_deletion_at = NULL,
                 reactivation_token = NULL
             WHERE id = ?",
            [$id]
        );
    }

    /**
     * Trouve un compte actif par son token de désinscription newsletter.
     *
     * @param string $token Token de désinscription (32 hex chars)
     * @return array<string, mixed>|false
     */
    public function findByUnsubscribeToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT id, email, lang FROM {$this->table}
             WHERE newsletter_unsubscribe_token = ? AND deleted_at IS NULL",
            [$token]
        );
    }

    /**
     * Désabonne un compte de la newsletter via son token et effectue une rotation du token.
     *
     * @param string $token Token de désinscription courant
     * @return bool true si un compte a été désabonné
     */
    public function unsubscribeByToken(string $token): bool
    {
        // Désinscrit ET invalide le token (rotation) pour éviter toute réutilisation du lien
        $rows = $this->db->execute(
            "UPDATE {$this->table}
             SET newsletter = 0,
                 newsletter_unsubscribe_token = ?
             WHERE newsletter_unsubscribe_token = ? AND deleted_at IS NULL",
            [bin2hex(random_bytes(32)), $token]
        );
        return $rows > 0;
    }

    /**
     * Retourne les comptes dont la suppression définitive est échue,
     * avec les données nécessaires à l'envoi de l'email final (avant anonymisation).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccountsDueForDeletion(): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.lang,
                    COALESCE(ai.firstname, ac.company_name, a.email) AS name
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.scheduled_deletion_at IS NOT NULL
               AND a.scheduled_deletion_at < NOW()"
        );
    }

    /**
     * Anonymise les comptes dont la suppression programmée est échue.
     * Les données de commandes sont conservées (obligations légales comptables — 10 ans).
     *
     * @return int Nombre de comptes anonymisés
     */
    public function purgeScheduledDeletions(): int
    {
        $accounts = $this->db->fetchAll(
            "SELECT id FROM {$this->table}
             WHERE scheduled_deletion_at IS NOT NULL
               AND scheduled_deletion_at < NOW()"
        );

        if ($accounts === []) {
            return 0;
        }

        $ids          = array_column($accounts, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $this->db->execute(
            "UPDATE {$this->table}
             SET email = CONCAT('deleted_', id, '@purged.invalid'),
                 password = NULL,
                 newsletter = 0,
                 newsletter_unsubscribe_token = NULL,
                 email_verification_token = NULL,
                 email_verification_token_expires_at = NULL,
                 google_id = NULL,
                 apple_id = NULL,
                 scheduled_deletion_at = NULL
             WHERE id IN ({$placeholders})",
            $ids
        );

        $this->db->execute(
            "UPDATE account_individuals
             SET firstname = 'Supprimé', lastname = 'Supprimé', civility = NULL
             WHERE account_id IN ({$placeholders})",
            $ids
        );

        $this->db->execute(
            "UPDATE account_companies
             SET company_name = 'Supprimé', siret = NULL
             WHERE account_id IN ({$placeholders})",
            $ids
        );

        // Suppression des adresses sauvegardées (données personnelles, non nécessaires à la comptabilité)
        // Les snapshots adresses/facturation dans orders.content sont conservés pour obligation comptable.
        // Les adresses preserved_for_order = 1 sont purgées séparément par bin/cron/purge_addresses.php.
        $this->db->execute(
            "DELETE FROM addresses WHERE user_id IN ({$placeholders}) AND preserved_for_order = 0",
            $ids
        );

        // Anonymisation des avis (display_name = prénom + initiale = donnée personnelle, RGPD Art. 17)
        $this->db->execute(
            "UPDATE customer_reviews
             SET display_name = 'Avis anonymisé',
                 comment      = NULL,
                 location     = NULL
             WHERE account_id IN ({$placeholders})",
            $ids
        );

        return count($ids);
    }

    // ----------------------------------------------------------------
    // RGPD — Inactivité (Art. 5 §1e — minimisation des données)
    // ----------------------------------------------------------------

    /**
     * Retourne les comptes clients inactifs depuis $months mois, non encore avertis.
     *
     * Critères d'exclusion : admin/super_admin, déjà soft-deleted,
     * avertissement déjà envoyé, commande pending/processing active.
     *
     * @param int $months Durée d'inactivité en mois (lu depuis $_ENV['RGPD_INACTIVITY_MONTHS'])
     * @return array<int, array<string, mixed>>
     */
    public function getInactiveAccounts(int $months): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.lang,
                    COALESCE(ai.firstname, ac.company_name, a.email) AS name
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.role = 'customer'
               AND a.deleted_at IS NULL
               AND a.inactivity_warning_sent_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.user_id = a.id AND o.status IN ('pending', 'processing')
               )
               AND COALESCE(a.last_login_at, a.created_at) < DATE_SUB(NOW(), INTERVAL ? MONTH)",
            [$months]
        );
    }

    /**
     * Retourne les comptes à soft-delete : avertis il y a plus de 30 jours
     * et n'ayant pas reconnecté depuis l'avertissement.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccountsToSoftDelete(): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.lang,
                    COALESCE(ai.firstname, ac.company_name, a.email) AS name
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.role = 'customer'
               AND a.deleted_at IS NULL
               AND a.inactivity_warning_sent_at IS NOT NULL
               AND a.inactivity_warning_sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND NOT EXISTS (
                   SELECT 1 FROM connections c
                   WHERE c.user_id = a.id AND c.created_at > a.inactivity_warning_sent_at
               )
               AND NOT EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.user_id = a.id AND o.status IN ('pending', 'processing')
               )"
        );
    }

    /**
     * Marque l'avertissement d'inactivité comme envoyé.
     *
     * @param int $id Identifiant du compte
     */
    public function markInactivityWarningSent(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET inactivity_warning_sent_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Réinitialise l'avertissement d'inactivité lors d'une reconnexion réussie.
     * Permet au cycle RGPD (Phase 1 → Phase 2) de redémarrer après 36 mois d'inactivité.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function resetInactivityWarning(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET inactivity_warning_sent_at = NULL WHERE id = ? AND inactivity_warning_sent_at IS NOT NULL",
            [$id]
        );
    }

    /**
     * Soft-delete un compte inactif : pose deleted_at, scheduled_deletion_at J+30,
     * génère un token de réactivation et révoque toutes les sessions actives.
     *
     * Préserve les adresses liées à des commandes actives (pending/processing).
     *
     * @param int $id Identifiant du compte
     */
    public function softDeleteForInactivity(int $id): string
    {
        $token = bin2hex(random_bytes(32));

        $this->db->execute(
            "UPDATE {$this->table}
             SET deleted_at = NOW(),
                 scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
                 reactivation_token = ?
             WHERE id = ?",
            [$token, $id]
        );

        $this->revokeAllSessions($id);
        $this->preserveAddressesForActiveOrders($id);

        return $token;
    }

    /**
     * Marque les adresses liées à des commandes actives comme préservées.
     * Elles seront purgées par bin/cron/purge_addresses.php une fois les commandes terminées.
     *
     * @param int $id Identifiant du compte
     */
    private function preserveAddressesForActiveOrders(int $id): void
    {
        $this->db->execute(
            "UPDATE addresses a
             SET a.preserved_for_order = 1
             WHERE a.user_id = ?
               AND EXISTS (
                   SELECT 1 FROM order_address_snapshots oas
                   JOIN orders o ON (o.id_billing_snapshot = oas.id OR o.id_delivery_snapshot = oas.id)
                   WHERE oas.source_address_id = a.id
                     AND o.status IN ('pending', 'processing')
               )",
            [$id]
        );
    }

    // ----------------------------------------------------------------
    // Changement d'email (double opt-in)
    // ----------------------------------------------------------------

    /**
     * Enregistre la demande de changement d'email avec TTL et nouvelle adresse.
     *
     * Le token est null quand l'adresse cible est déjà utilisée (anti-énumération) :
     * la demande est visible en BDD (pending block affiché), mais aucun email n'est envoyé
     * et la confirmation est impossible sans token.
     *
     * @param int         $userId      Identifiant du compte
     * @param string|null $hashedToken Hash SHA-256 du token brut, ou null si email déjà pris
     * @param string      $newEmail    Nouvelle adresse email en attente de confirmation
     * @param string      $expiresAt   Date d'expiration au format 'Y-m-d H:i:s'
     * @return void
     */
    public function saveEmailChangeToken(
        int $userId,
        ?string $hashedToken,
        string $newEmail,
        string $expiresAt
    ): void {
        $this->db->execute(
            "UPDATE {$this->table}
             SET email_change_token     = ?,
                 email_change_new_email = ?,
                 email_change_expires_at = ?,
                 email_change_used_at   = NULL
             WHERE id = ?",
            [$hashedToken, $this->encryption->encrypt($newEmail), $expiresAt, $userId]
        );
    }

    /**
     * Recherche un compte par son token de changement d'email (haché SHA-256).
     *
     * @param string $hashedToken Hash SHA-256 du token brut
     * @return array<string, mixed>|false Compte + champs email_change_* ou false si introuvable
     */
    public function findByEmailChangeToken(string $hashedToken): array|false
    {
        $row = $this->db->fetchOne(
            "SELECT id, email, email_change_new_email, email_change_expires_at, email_change_used_at, lang
             FROM {$this->table}
             WHERE email_change_token = ? AND deleted_at IS NULL",
            [$hashedToken]
        );

        if (is_array($row) && isset($row['email_change_new_email']) && is_string($row['email_change_new_email'])) {
            $row['email_change_new_email'] = $this->encryption->decrypt($row['email_change_new_email'])
                ?? $row['email_change_new_email'];
        }

        return $row;
    }

    /**
     * Applique le changement d'email effectif et marque le token comme utilisé.
     *
     * @param int    $userId   Identifiant du compte
     * @param string $newEmail Nouvelle adresse email définitive
     * @return void
     */
    /**
     * Annule une demande de changement d'email en cours.
     *
     * Remet à NULL le token, la nouvelle adresse, l'expiration et l'horodatage.
     *
     * @param int $userId Identifiant du compte
     * @return void
     */
    public function clearEmailChangeToken(int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET email_change_token      = NULL,
                 email_change_new_email  = NULL,
                 email_change_expires_at = NULL,
                 email_change_used_at    = NULL
             WHERE id = ?",
            [$userId]
        );
    }

    public function applyEmailChange(int $userId, string $newEmail): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET email                  = ?,
                 email_change_used_at   = NOW(),
                 email_change_token     = NULL,
                 email_change_new_email = NULL,
                 email_change_expires_at = NULL
             WHERE id = ?",
            [$newEmail, $userId]
        );
    }

    /**
     * Compte le nombre de demandes de changement d'email dans les 24 dernières heures.
     * Utilisé pour le rate limiting (max 3 demandes / 24h).
     *
     * @param int $userId Identifiant du compte
     * @return int Nombre de demandes
     */
    public function countEmailChangeRequestsLast24h(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM audit_log
             WHERE user_id = ?
               AND event_type = 'email_change_request'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$userId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Enregistre un événement dans le log d'audit RGPD.
     *
     * @param int                  $userId    Identifiant du compte concerné
     * @param string               $eventType Type d'événement (ex. 'email_changed')
     * @param string               $ip        Adresse IP de l'acteur
     * @param array<string, mixed> $meta      Données complémentaires (sérialisées en JSON)
     * @return void
     */
    public function logAuditEvent(
        int $userId,
        string $eventType,
        string $ip,
        array $meta = []
    ): void {
        $this->db->insert(
            "INSERT INTO audit_log (user_id, event_type, ip, meta, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $eventType, $ip, $meta !== [] ? json_encode($meta) : null]
        );
    }

    // ----------------------------------------------------------------
    // Méthodes admin
    // ----------------------------------------------------------------

    /**
     * @param string|null $sort Clé de tri : created_at_desc|created_at_asc|last_connection_desc|last_connection_asc
     * @return array<int, array<string, mixed>>
     */
    /**
     * Retourne la liste paginée des comptes pour l'admin.
     *
     * @param int                          $limit   Nombre de lignes
     * @param int                          $offset  Décalage
     * @param \ValueObject\AccountAdminFilters $filters Filtres et tri (role, search, type, sort, hasOrdered, activity)
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(
        int $limit,
        int $offset,
        \ValueObject\AccountAdminFilters $filters
    ): array {
        [$where, $params] = $this->buildAdminFilters($filters->role, $filters->search, $filters->type, $filters->activity);
        $where            = $this->applyAdminFlagFilters($where, $filters->hasOrdered, $filters->activity);

        $params[] = $limit;
        $params[] = $offset;

        $orderBy = match ($filters->sort) {
            'created_at_asc'         => 'a.created_at ASC',
            'last_connection_desc'   => 'a.last_login_at DESC',
            'last_connection_asc'    => 'a.last_login_at ASC',
            'order_count_desc'       => 'order_count DESC',
            'order_count_asc'        => 'order_count ASC',
            default                  => 'a.created_at DESC',
        };

        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.account_type, a.role, a.lang, a.newsletter,
                    a.email_verified_at, a.has_connected, a.created_at, a.deleted_at,
                    a.is_blocked, a.last_login_at AS last_connection,
                    a.inactivity_warning_sent_at, a.scheduled_deletion_at,
                    ai.lastname, ai.firstname, ai.civility,
                    ac.company_name, ac.siret,
                    (a.password IS NOT NULL) AS has_password,
                    (SELECT COUNT(*) FROM orders o WHERE o.user_id = a.id AND o.status NOT IN ('cancelled', 'refunded')) AS order_count
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}
             ORDER BY {$orderBy}
             LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Applique les filtres booléens (commandes, activité) sur une clause WHERE existante.
     *
     * @param string      $where      Clause WHERE courante (peut être vide)
     * @param string|null $hasOrdered '1' = a commandé, '0' = jamais, null = tous
     * @param string|null $activity   'active', 'inactive', 'warning', 'deleted' ou null
     * @return string Clause WHERE enrichie
     */
    private function applyAdminFlagFilters(string $where, ?string $hasOrdered, ?string $activity): string
    {
        $join = fn(string $w): string => $w === '' ? 'WHERE' : ' AND';

        if ($hasOrdered === '1') {
            $where .= $join($where) . ' (SELECT COUNT(*) FROM orders o WHERE o.user_id = a.id AND o.status NOT IN (\'cancelled\', \'refunded\')) > 0';
        } elseif ($hasOrdered === '0') {
            $where .= $join($where) . ' (SELECT COUNT(*) FROM orders o WHERE o.user_id = a.id AND o.status NOT IN (\'cancelled\', \'refunded\')) = 0';
        }

        // deleted_at IS NULL déjà posé par buildAdminFilters sauf pour 'deleted'
        if ($activity === 'active') {
            $where .= $join($where) . ' a.last_login_at >= DATE_SUB(NOW(), INTERVAL 3 YEAR)';
        } elseif ($activity === 'inactive') {
            $where .= $join($where) . ' (a.last_login_at IS NULL OR a.last_login_at < DATE_SUB(NOW(), INTERVAL 3 YEAR))';
        } elseif ($activity === 'warning') {
            $where .= $join($where) . ' a.inactivity_warning_sent_at IS NOT NULL';
        } elseif ($activity === 'deleted') {
            $where .= $join($where) . ' a.deleted_at IS NOT NULL';
        }

        return $where;
    }

    /**
     * Retourne le nombre total de comptes correspondant aux filtres admin.
     *
     * @param \ValueObject\AccountAdminFilters $filters Filtres (role, search, type, hasOrdered, activity — sort ignoré)
     * @return int
     */
    public function countForAdmin(\ValueObject\AccountAdminFilters $filters): int
    {
        [$where, $params] = $this->buildAdminFilters($filters->role, $filters->search, $filters->type, $filters->activity);
        $where            = $this->applyAdminFlagFilters($where, $filters->hasOrdered, $filters->activity);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    public function updateRole(int $id, string $role): void
    {
        $valid = ['customer', 'admin', 'super_admin'];
        if (!in_array($role, $valid, true)) {
            return;
        }
        $this->db->execute(
            "UPDATE {$this->table} SET role = ? WHERE id = ?",
            [$role, $id]
        );
    }

    public function countTotal(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} WHERE deleted_at IS NULL"
        );
        return (int) ($row['total'] ?? 0);
    }

    // ----------------------------------------------------------------
    // Newsletter
    // ----------------------------------------------------------------

    /**
     * Retourne la liste unifiée des abonnés newsletter (comptes + abonnements sans compte).
     *
     * La requête UNION garantit l'absence de doublon sur l'email : si un visiteur
     * s'est abonné via newsletter_subscriptions puis a créé un compte avec newsletter=1,
     * seul le compte remonte (le dédoublonnage à l'inscription a supprimé l'entrée ns,
     * mais le NOT EXISTS dans la branche ns sert de filet de sécurité).
     *
     * @param  int $limit  Nombre de lignes
     * @param  int $offset Décalage
     * @return array<int, array<string, mixed>>
     */
    public function getNewsletterSubscribers(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT unified.id, unified.email, unified.firstname, unified.lastname,
                    unified.company_name, unified.account_type, unified.lang,
                    unified.source, unified.created_at
             FROM (
                 SELECT a.id, a.email, ai.firstname, ai.lastname, ac.company_name,
                        a.account_type, a.lang, 'account' AS source, a.created_at
                 FROM {$this->table} a
                 LEFT JOIN account_individuals ai ON ai.account_id = a.id
                 LEFT JOIN account_companies   ac ON ac.account_id = a.id
                 WHERE a.newsletter = 1 AND a.deleted_at IS NULL

                 UNION

                 SELECT NULL AS id, ns.email, NULL AS firstname, NULL AS lastname,
                        NULL AS company_name, 'individual' AS account_type,
                        ns.lang, 'subscription' AS source,
                        ns.newsletter_consent_date AS created_at
                 FROM newsletter_subscriptions ns
                 WHERE ns.newsletter_confirmed = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM {$this->table} a2
                       WHERE a2.email = ns.email AND a2.newsletter = 1
                   )
             ) AS unified
             ORDER BY unified.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Compte le total des abonnés newsletter toutes sources confondues.
     *
     * Additionne les comptes newsletter=1 et les abonnements sans compte confirmés
     * sans doublon sur l'email.
     *
     * @return int
     */
    public function countNewsletterSubscribers(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM (
                 SELECT a.email
                 FROM {$this->table} a
                 WHERE a.newsletter = 1 AND a.deleted_at IS NULL

                 UNION

                 SELECT ns.email
                 FROM newsletter_subscriptions ns
                 WHERE ns.newsletter_confirmed = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM {$this->table} a2
                       WHERE a2.email = ns.email AND a2.newsletter = 1
                   )
             ) AS unified"
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Supprime l'abonnement newsletter_subscriptions pour un email donné.
     *
     * Utilisé lors de la création de compte : si l'utilisateur coche newsletter=1,
     * l'éventuelle entrée dans newsletter_subscriptions est supprimée (dédoublonnage).
     *
     * @param  string $email Adresse email à dédoublonner
     * @return void
     */
    public function deleteNewsletterSubscriptionByEmail(string $email): void
    {
        $this->db->execute(
            "DELETE FROM newsletter_subscriptions WHERE email = ?",
            [$email]
        );
    }

    /**
     * Retourne la clause SQL WHERE additionnelle pour un segment donné.
     *
     * @param string $segment Identifiant du segment
     * @return string Clause SQL (vide pour 'tous')
     */
    private function segmentWhere(string $segment): string
    {
        return match ($segment) {
            'particuliers'   => "AND a.account_type = 'individual'",
            'professionnels' => "AND a.account_type = 'company'",
            'commandes'      => "AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id = a.id)",
            'sans-commande'  => "AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = a.id)",
            'inactifs'       => "AND EXISTS (SELECT 1 FROM orders o WHERE o.user_id = a.id)
                                 AND (SELECT MAX(o2.ordered_at) FROM orders o2 WHERE o2.user_id = a.id)
                                     < DATE_SUB(NOW(), INTERVAL 6 MONTH)",
            default => '',
        };
    }

    /**
     * Retourne les abonnés newsletter filtrés par segment.
     *
     * Pour le segment 'tous', la requête inclut les abonnés sans compte via UNION
     * (newsletter_subscriptions confirmés non présents dans accounts avec newsletter=1).
     * Les autres segments (particuliers, professionnels, commandes…) ne concernent
     * que les comptes et n'incluent pas les abonnés sans compte.
     *
     * @param  string $segment Identifiant du segment (tous, particuliers, professionnels, commandes, sans-commande, inactifs, non-inscrit)
     * @param  int    $limit   Nombre de lignes
     * @param  int    $offset  Décalage
     * @return array<int, array<string, mixed>>
     */
    public function getNewsletterSubscribersBySegment(string $segment, int $limit, int $offset): array
    {
        if ($segment === 'non-inscrit') {
            return $this->db->fetchAll(
                "SELECT ns.email, NULL AS firstname, NULL AS lastname,
                        NULL AS company_name, 'individual' AS account_type,
                        ns.lang, NULL AS newsletter_unsubscribe_token,
                        'subscription' AS source,
                        ns.newsletter_consent_date AS created_at
                 FROM newsletter_subscriptions ns
                 WHERE ns.newsletter_confirmed = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM {$this->table} a WHERE a.email = ns.email AND a.newsletter = 1
                   )
                 ORDER BY ns.newsletter_consent_date DESC
                 LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
        }

        if ($segment === 'tous') {
            return $this->db->fetchAll(
                "SELECT unified.email, unified.firstname, unified.lastname,
                        unified.company_name, unified.account_type, unified.lang,
                        unified.newsletter_unsubscribe_token, unified.source, unified.created_at
                 FROM (
                     SELECT a.email, ai.firstname, ai.lastname, ac.company_name,
                            a.account_type, a.lang, a.newsletter_unsubscribe_token,
                            'account' AS source, a.created_at
                     FROM {$this->table} a
                     LEFT JOIN account_individuals ai ON ai.account_id = a.id
                     LEFT JOIN account_companies   ac ON ac.account_id = a.id
                     WHERE a.newsletter = 1 AND a.deleted_at IS NULL

                     UNION

                     SELECT ns.email, NULL AS firstname, NULL AS lastname,
                            NULL AS company_name, 'individual' AS account_type,
                            ns.lang, NULL AS newsletter_unsubscribe_token,
                            'subscription' AS source,
                            ns.newsletter_consent_date AS created_at
                     FROM newsletter_subscriptions ns
                     WHERE ns.newsletter_confirmed = 1
                       AND NOT EXISTS (
                           SELECT 1 FROM {$this->table} a2
                           WHERE a2.email = ns.email AND a2.newsletter = 1
                       )
                 ) AS unified
                 ORDER BY unified.created_at DESC
                 LIMIT ? OFFSET ?",
                [$limit, $offset]
            );
        }

        $where = $this->segmentWhere($segment);
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.account_type, a.lang, a.created_at,
                    a.newsletter_unsubscribe_token,
                    ai.firstname, ai.lastname,
                    ac.company_name
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.newsletter = 1 AND a.deleted_at IS NULL {$where}
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Compte les abonnés newsletter pour un segment donné.
     *
     * @param  string $segment Identifiant du segment
     * @return int
     */
    public function countNewsletterSubscribersBySegment(string $segment): int
    {
        if ($segment === 'non-inscrit') {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM newsletter_subscriptions ns
                 WHERE ns.newsletter_confirmed = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM {$this->table} a WHERE a.email = ns.email AND a.newsletter = 1
                   )"
            );
            return (int) ($row['total'] ?? 0);
        }

        $where = $this->segmentWhere($segment);

        // 'tous' ou segment inconnu → toutes sources via UNION (même logique que countNewsletterSubscribers())
        if ($where === '' && $segment !== 'non-inscrit') {
            return $this->countNewsletterSubscribers();
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM {$this->table} a
             WHERE a.newsletter = 1 AND a.deleted_at IS NULL {$where}"
        );
        return (int) ($row['total'] ?? 0);
    }

    /** @return array{string, array<int, mixed>} */
    /**
     * Active la newsletter pour un compte dont le consentement était en attente
     * (coché à l'inscription, activé lors de la vérification email).
     *
     * @param int $userId Identifiant du compte
     * @return void
     */
    public function activateNewsletterFromPending(int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET newsletter = 1, newsletter_optin_pending = 0
             WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Stocke un token de confirmation double opt-in newsletter (TTL 48 h).
     *
     * @param int    $userId Identifiant du compte
     * @param string $token  Token brut (bin2hex 32 bytes)
     * @return void
     */
    public function storeNewsletterConfirmToken(int $userId, string $token): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET newsletter_confirm_token = ?,
                 newsletter_confirm_expires_at = NOW() + INTERVAL 48 HOUR
             WHERE id = ?",
            [$token, $userId]
        );
    }

    /**
     * Valide un token de confirmation newsletter, active l'abonnement et efface le token.
     *
     * @param string $token Token brut reçu dans l'URL
     * @return array<string, mixed>|false Compte activé, ou false si token invalide/expiré
     */
    public function confirmNewsletterByToken(string $token): array|false
    {
        $account = $this->db->fetchOne(
            "SELECT id FROM {$this->table}
             WHERE newsletter_confirm_token = ?
               AND newsletter_confirm_expires_at > NOW()
               AND deleted_at IS NULL",
            [$token]
        );

        if (!$account) {
            return false;
        }

        $this->db->execute(
            "UPDATE {$this->table}
             SET newsletter = 1,
                 newsletter_confirm_token = NULL,
                 newsletter_confirm_expires_at = NULL
             WHERE id = ?",
            [(int) $account['id']]
        );

        return $account;
    }

    /**
     * Retourne les données de consentement RGPD pour l'export (Art. 20).
     *
     * - newsletter : statut courant + date d'activation (email_verified_at comme proxy
     *   de premier opt-in ; aucune colonne consent_date dédiée n'existe côté comptes).
     * - cgv : liste dédupliquée et triée des versions CGV acceptées via les commandes.
     *
     * @param int $userId Identifiant du compte
     * @return array{newsletter: array<string, mixed>, cgv_versions: array<int, string>}
     */
    public function getConsentData(int $userId): array
    {
        $account = $this->db->fetchOne(
            "SELECT newsletter, email_verified_at
             FROM {$this->table}
             WHERE id = ? AND deleted_at IS NULL",
            [$userId]
        );

        $newsletter = [
            'abonne'         => ($account && (bool) $account['newsletter']),
            'email_verifie_le' => $account['email_verified_at'] ?? null,
        ];

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT cgv_version
             FROM orders
             WHERE user_id = ?
             ORDER BY cgv_version ASC",
            [$userId]
        );

        $cgvVersions = array_column($rows, 'cgv_version');

        return [
            'newsletter'   => $newsletter,
            'cgv_versions' => $cgvVersions,
        ];
    }

    private function buildAdminFilters(?string $role, ?string $search, ?string $type = null, ?string $activity = null): array
    {
        // Exclut les supprimés par défaut — sauf si on filtre explicitement sur 'deleted'
        $conds  = $activity === 'deleted' ? [] : ['a.deleted_at IS NULL'];
        $params = [];

        $validRoles = ['customer', 'admin', 'super_admin'];
        if ($role !== null && in_array($role, $validRoles, true)) {
            $conds[]  = 'a.role = ?';
            $params[] = $role;
        }

        $validTypes = ['individual', 'company'];
        if ($type !== null && in_array($type, $validTypes, true)) {
            $conds[]  = 'a.account_type = ?';
            $params[] = $type;
        }

        if ($search !== null && $search !== '') {
            $like     = '%' . $search . '%';
            $conds[]  = '(a.email LIKE ? OR ai.lastname LIKE ? OR ai.firstname LIKE ? OR ac.company_name LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where = 'WHERE ' . implode(' AND ', $conds);
        return [$where, $params];
    }

    /**
     * Enregistre la déclaration de majorité d'un compte.
     *
     * @param int    $id Identifiant du compte
     * @param string $ip Adresse IP du déclarant
     * @return void
     */
    public function declareMajority(int $id, string $ip): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET majority_declared_at = NOW(), majority_declared_ip = ? WHERE id = ?",
            [$this->encryption->encrypt($ip), $id]
        );
    }

    /**
     * Vérifie si un compte a déclaré sa majorité.
     *
     * @param int $id Identifiant du compte
     * @return bool
     */
    public function hasDeclaredMajority(int $id): bool
    {
        $row = $this->db->fetchOne(
            "SELECT majority_declared_at FROM {$this->table} WHERE id = ? AND deleted_at IS NULL",
            [$id]
        );
        return $row !== false && ($row['majority_declared_at'] ?? null) !== null;
    }

    // ----------------------------------------------------------------
    // Gestion des comptes admin
    // ----------------------------------------------------------------

    /**
     * Retourne tous les comptes de rôle admin ou super_admin, avec date de dernière connexion.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAdmins(): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.role, a.is_blocked, a.created_at,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '') AS lastname,
                    a.last_login_at
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.role IN ('admin', 'super_admin')
               AND a.deleted_at IS NULL
             ORDER BY a.role DESC, a.id ASC"
        );
    }

    /**
     * Bloque un compte admin : is_blocked = 1, révoque toutes les sessions actives et appareils de confiance.
     *
     * @param int $id Identifiant du compte à bloquer
     * @return void
     */
    public function blockAccount(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET is_blocked = 1 WHERE id = ?",
            [$id]
        );
        $this->revokeAllSessions($id);
        $this->db->execute(
            "DELETE FROM trusted_devices WHERE user_id = ?",
            [$id]
        );
    }

    /**
     * Débloque un compte admin : is_blocked = 0.
     *
     * @param int $id Identifiant du compte à débloquer
     * @return void
     */
    public function unblockAccount(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET is_blocked = 0 WHERE id = ?",
            [$id]
        );
    }

    // ----------------------------------------------------------------
    // Marketing
    // ----------------------------------------------------------------

    /**
     * Retourne la segmentation des comptes actifs : pro/particulier, fr/en, total.
     *
     * @return array{pro: int, particulier: int, fr: int, en: int, total: int}
     */
    public function getSegmentation(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                SUM(CASE WHEN ac.account_id IS NOT NULL THEN 1 ELSE 0 END) AS pro,
                SUM(CASE WHEN ac.account_id IS NULL     THEN 1 ELSE 0 END) AS particulier,
                SUM(CASE WHEN a.lang = 'fr'             THEN 1 ELSE 0 END) AS fr,
                SUM(CASE WHEN a.lang = 'en'             THEN 1 ELSE 0 END) AS en,
                COUNT(*) AS total
             FROM {$this->table} a
             LEFT JOIN account_companies ac ON ac.account_id = a.id
             WHERE a.deleted_at IS NULL"
        );
        $row = $rows[0] ?? [];
        return [
            'pro'         => (int) ($row['pro']         ?? 0),
            'particulier' => (int) ($row['particulier'] ?? 0),
            'fr'          => (int) ($row['fr']          ?? 0),
            'en'          => (int) ($row['en']          ?? 0),
            'total'       => (int) ($row['total']       ?? 0),
        ];
    }
}
