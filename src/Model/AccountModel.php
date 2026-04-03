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
        $this->db->execute(
            "DELETE FROM addresses WHERE user_id IN ({$placeholders})",
            $ids
        );

        return count($ids);
    }

    // ----------------------------------------------------------------
    // Changement d'email (double opt-in)
    // ----------------------------------------------------------------

    /**
     * Enregistre le token de changement d'email (haché SHA-256) avec TTL et nouvelle adresse.
     *
     * @param int    $userId      Identifiant du compte
     * @param string $hashedToken Hash SHA-256 du token brut
     * @param string $newEmail    Nouvelle adresse email en attente de confirmation
     * @param string $expiresAt   Date d'expiration au format 'Y-m-d H:i:s'
     * @return void
     */
    public function saveEmailChangeToken(
        int $userId,
        string $hashedToken,
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
            $this->withProfile() . "
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
}
