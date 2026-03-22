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
}
