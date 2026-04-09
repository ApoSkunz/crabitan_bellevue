<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class AccountModel extends Model // NOSONAR php:S1448 — regroupement intentionnel ; découpage prévu à l'audit génie logiciel
{
    protected string $table = 'accounts';

    private function withProfile(): string
    {
        return "SELECT a.*,
                       ai.lastname, ai.firstname, ai.civility,
                       ac.company_name, ac.siret
                FROM {$this->table} a
                LEFT JOIN account_individuals ai ON ai.account_id = a.id
                LEFT JOIN account_companies   ac ON ac.account_id = a.id";
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.email = ? AND a.deleted_at IS NULL",
            [$email]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.id = ? AND a.deleted_at IS NULL",
            [$id]
        );
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
     * @param string $email     Adresse email Google
     * @param string $googleId  Identifiant Google (sub)
     * @param string $lang      Langue de l'interface (fr|en)
     * @param string $firstname Prénom fourni par Google
     * @param string $lastname  Nom de famille fourni par Google
     * @return int Identifiant du compte créé
     */
    public function createFromGoogle(
        string $email,
        string $googleId,
        string $lang,
        string $firstname,
        string $lastname
    ): int {
        $this->db->beginTransaction();
        try {
            $accountId = $this->db->insert(
                "INSERT INTO {$this->table}
                 (email, password, account_type, role, lang, newsletter, google_id,
                  email_verified_at, newsletter_unsubscribe_token)
                 VALUES (?, NULL, 'individual', 'customer', ?, 0, ?, NOW(), ?)",
                [$email, $lang, $googleId, bin2hex(random_bytes(32))]
            );

            $this->db->insert(
                "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                 VALUES (?, ?, ?, NULL)",
                [(int) $accountId, $lastname ?: 'Google', $firstname ?: 'Utilisateur']
            );

            $this->db->commit();
            return (int) $accountId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

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

    public function updatePassword(int $id, string $hashedPassword): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET password = ? WHERE id = ?",
            [$hashedPassword, $id]
        );
    }

    public function updateIndividualProfile(int $id, string $civility, string $firstname, string $lastname): void
    {
        $this->db->execute(
            "UPDATE account_individuals SET civility = ?, firstname = ?, lastname = ?
             WHERE account_id = ?",
            [$civility, $firstname, $lastname, $id]
        );
    }

    public function updateCompanyProfile(int $id, string $companyName, ?string $siret): void
    {
        $this->db->execute(
            "UPDATE account_companies SET company_name = ?, siret = ? WHERE account_id = ?",
            [$companyName, $siret, $id]
        );
    }

    public function updateNewsletter(int $id, bool $subscribe): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET newsletter = ? WHERE id = ?",
            [$subscribe ? 1 : 0, $id]
        );
    }

    public function revokeAllSessions(int $id): void
    {
        // Utilisé lors de la suppression de compte pour révoquer toutes les sessions actives
        $this->db->execute(
            "UPDATE connections SET status = 'revoked' WHERE user_id = ? AND status = 'active'",
            [$id]
        );
    }

    public function markAsConnected(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET has_connected = 1 WHERE id = ?",
            [$id]
        );
    }

    public function updateLang(int $id, string $lang): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET lang = ? WHERE id = ?",
            [$lang, $id]
        );
    }

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
     * Marque le rappel de suppression comme envoyé pour un compte.
     *
     * @param int $id       Identifiant du compte
     * @param int $daysAhead 7 (rappel J-7) ou 1 (rappel J-1)
     */
    public function markDeletionReminderSent(int $id, int $daysAhead): void
    {
        $column = match ($daysAhead) {
            7       => 'deletion_reminder_7d_sent_at',
            1       => 'deletion_reminder_1d_sent_at',
            default => throw new \InvalidArgumentException("daysAhead must be 1 or 7, got {$daysAhead}"),
        };

        $this->db->execute(
            "UPDATE {$this->table} SET {$column} = NOW() WHERE id = ?",
            [$id]
        );
    }

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

    public function findByUnsubscribeToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT id, email, lang FROM {$this->table}
             WHERE newsletter_unsubscribe_token = ? AND deleted_at IS NULL",
            [$token]
        );
    }

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
     * Anonymise les comptes dont la suppression programmée est échue.
     * Les données de commandes sont conservées (obligations légales comptables — 10 ans).
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
        // Les adresses preserved_for_order = 1 sont purgées séparément par bin/purge_addresses.php.
        $this->db->execute(
            "DELETE FROM addresses WHERE user_id IN ({$placeholders}) AND preserved_for_order = 0",
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
     * @param int $months Durée d'inactivité en mois (lu depuis RGPD_INACTIVITY_MONTHS)
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
               AND COALESCE(
                   (SELECT MAX(c.created_at) FROM connections c WHERE c.user_id = a.id),
                   a.created_at
               ) < DATE_SUB(NOW(), INTERVAL ? MONTH)",
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
     * Soft-delete un compte inactif : pose deleted_at, scheduled_deletion_at J+30,
     * génère un token de réactivation et révoque toutes les sessions actives.
     *
     * Préserve les adresses liées à des commandes actives (pending/processing).
     *
     * @param int $id Identifiant du compte
     */
    public function softDeleteForInactivity(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET deleted_at = NOW(),
                 scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
                 reactivation_token = ?
             WHERE id = ?",
            [bin2hex(random_bytes(32)), $id]
        );

        $this->revokeAllSessions($id);
        $this->preserveAddressesForActiveOrders($id);
    }

    /**
     * Marque les adresses liées à des commandes actives comme préservées.
     * Elles seront purgées par bin/purge_addresses.php une fois les commandes terminées.
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
            [$hashedToken, $newEmail, $expiresAt, $userId]
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
        return $this->db->fetchOne(
            "SELECT id, email, email_change_new_email, email_change_expires_at, email_change_used_at, lang
             FROM {$this->table}
             WHERE email_change_token = ? AND deleted_at IS NULL",
            [$hashedToken]
        );
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
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(int $limit, int $offset, ?string $role, ?string $search, ?string $type = null): array
    {
        [$where, $params] = $this->buildAdminFilters($role, $search, $type);
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.account_type, a.role, a.lang, a.newsletter,
                    a.email_verified_at, a.has_connected, a.created_at, a.deleted_at,
                    a.is_blocked,
                    ai.lastname, ai.firstname, ai.civility,
                    ac.company_name, ac.siret
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countForAdmin(?string $role, ?string $search, ?string $type = null): int
    {
        [$where, $params] = $this->buildAdminFilters($role, $search, $type);
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

    /** @return array<int, array<string, mixed>> */
    public function getNewsletterSubscribers(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.account_type, a.lang, a.created_at,
                    a.newsletter_unsubscribe_token,
                    ai.firstname, ai.lastname,
                    ac.company_name
             FROM {$this->table} a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             WHERE a.newsletter = 1 AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function countNewsletterSubscribers(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table}
             WHERE newsletter = 1 AND deleted_at IS NULL"
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

    private function buildAdminFilters(?string $role, ?string $search, ?string $type = null): array
    {
        $conds  = ['a.deleted_at IS NULL'];
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
            [$ip, $id]
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
                    (SELECT c.created_at
                     FROM connections c
                     WHERE c.user_id = a.id AND c.status = 'active'
                     ORDER BY c.created_at DESC
                     LIMIT 1) AS last_login_at
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
}
