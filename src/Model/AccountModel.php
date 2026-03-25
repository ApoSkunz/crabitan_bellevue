<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

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
                 (email, password, account_type, role, lang, newsletter, email_verification_token)
                 VALUES (?, ?, ?, 'customer', ?, ?, ?)",
                [$email, $hashedPassword, $accountType, $lang, $newsletter, $verificationToken]
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
            "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    // ----------------------------------------------------------------
    // Méthodes admin
    // ----------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(int $limit, int $offset, ?string $role, ?string $search): array
    {
        [$where, $params] = $this->buildAdminFilters($role, $search);
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

    public function countForAdmin(?string $role, ?string $search): int
    {
        [$where, $params] = $this->buildAdminFilters($role, $search);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} a {$where}",
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

    /** @return array{string, array<int, mixed>} */
    private function buildAdminFilters(?string $role, ?string $search): array
    {
        $conds  = ['a.deleted_at IS NULL'];
        $params = [];

        $validRoles = ['customer', 'admin', 'super_admin'];
        if ($role !== null && in_array($role, $validRoles, true)) {
            $conds[]  = 'a.role = ?';
            $params[] = $role;
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
