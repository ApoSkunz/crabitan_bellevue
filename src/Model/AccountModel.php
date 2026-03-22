<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class AccountModel extends Model
{
    protected string $table = 'accounts';

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE email = ? AND deleted_at IS NULL",
            [$email]
        );
    }

    public function findByVerificationToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE email_verification_token = ? AND deleted_at IS NULL",
            [$token]
        );
    }

    public function findByGoogleId(string $googleId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE google_id = ? AND deleted_at IS NULL",
            [$googleId]
        );
    }

    public function create( // NOSONAR — 9 params nécessaires, DTO prévu avec feat/account
        string $lastname,
        string $firstname,
        string $email,
        string $hashedPassword,
        string $gender,
        ?string $companyName,
        string $lang,
        int $newsletter,
        string $verificationToken
    ): string {
        return $this->db->insert(
            "INSERT INTO {$this->table}
             (lastname, firstname, email, password, role, gender, company_name, lang, newsletter, email_verification_token)
             VALUES (?, ?, ?, ?, 'customer', ?, ?, ?, ?, ?)",
            [$lastname, $firstname, $email, $hashedPassword, $gender, $companyName, $lang, $newsletter, $verificationToken]
        );
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
}
