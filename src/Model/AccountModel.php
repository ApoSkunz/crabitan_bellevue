<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

// NOSONAR php:S1448 — regroupement intentionnel ; découpage prévu à l'audit génie logiciel
class AccountModel extends Model
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

    public function findByVerificationToken(string $token): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.email_verification_token = ? AND a.deleted_at IS NULL",
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

    public function findByAppleId(string $appleId): array|false
    {
        return $this->db->fetchOne(
            $this->withProfile() . " WHERE a.apple_id = ? AND a.deleted_at IS NULL",
            [$appleId]
        );
    }

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
                 (email, password, account_type, role, lang, newsletter,
                  email_verification_token, newsletter_unsubscribe_token)
                 VALUES (?, ?, ?, 'customer', ?, ?, ?, ?)",
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
            "UPDATE {$this->table} SET email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?",
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
                 google_id = NULL,
                 apple_id = NULL,
                 scheduled_deletion_at = NULL
             WHERE id IN ({$placeholders})",
            $ids
        );

        $this->db->execute(
            "UPDATE account_individuals
             SET firstname = 'Supprimé', lastname = 'Supprimé', civility = ''
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
