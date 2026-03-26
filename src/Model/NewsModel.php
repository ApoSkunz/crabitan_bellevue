<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class NewsModel extends Model
{
    protected string $table = 'news';

    /**
     * Retourne les N dernières actualités, triées par date décroissante.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLatest(int $limit = 3): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, text_content, slug, image_path, created_at
             FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, title, text_content, slug, image_path, created_at
             FROM {$this->table}
             WHERE slug = ?",
            [$slug]
        );
        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, text_content, slug, image_path, created_at
             FROM {$this->table}
             ORDER BY created_at DESC"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaginated(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, text_content, slug, image_path, created_at
             FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function countAll(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) ($row['total'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function getPrev(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT slug, title FROM {$this->table}
             WHERE created_at > (SELECT created_at FROM {$this->table} WHERE slug = ?)
             ORDER BY created_at ASC
             LIMIT 1",
            [$slug]
        );
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function getNext(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT slug, title FROM {$this->table}
             WHERE created_at < (SELECT created_at FROM {$this->table} WHERE slug = ?)
             ORDER BY created_at DESC
             LIMIT 1",
            [$slug]
        );
        return $row ?: null;
    }

    // ----------------------------------------------------------------
    // Méthodes admin
    // ----------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function getForAdmin(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, slug, image_path, created_at
             FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    public function countForAdmin(): int
    {
        return $this->countAll();
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table} (title, text_content, image_path, link_path, slug, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $data['title'],
                $data['text_content'],
                $data['image_path'] ?: null,
                $data['link_path'] ?: null,
                $data['slug'],
            ]
        );
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET title = ?, text_content = ?, image_path = ?, link_path = ?, slug = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $data['title'],
                $data['text_content'],
                $data['image_path'] ?: null,
                $data['link_path'] ?: null,
                $data['slug'],
                $id,
            ]
        );
    }
}
