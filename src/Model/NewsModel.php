<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des actualités (news) du site.
 */
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
            "SELECT id, title, text_content, slug, image_path, link_path, created_at
             FROM {$this->table}
             ORDER BY created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Retourne une actualité par son slug.
     *
     * @param string $slug Slug de l'actualité
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, title, text_content, slug, image_path, link_path, created_at
             FROM {$this->table}
             WHERE slug = ?",
            [$slug]
        );
        return $row ?: null;
    }

    /**
     * Retourne toutes les actualités triées par date décroissante.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, text_content, slug, image_path, link_path, created_at
             FROM {$this->table}
             ORDER BY created_at DESC"
        );
    }

    /**
     * Retourne le nombre total d'actualités.
     *
     * @return int
     */
    public function countAll(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne l'actualité précédente (plus récente) par rapport au slug donné.
     *
     * @param string $slug Slug de référence
     * @return array<string, mixed>|null
     */
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

    /**
     * Retourne l'actualité suivante (plus ancienne) par rapport au slug donné.
     *
     * @param string $slug Slug de référence
     * @return array<string, mixed>|null
     */
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

    /**
     * Retourne une page d'actualités pour la vue admin.
     *
     * @param int $limit  Nombre d'actualités à retourner
     * @param int $offset Décalage (pagination)
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * Retourne le nombre total d'actualités (admin).
     *
     * @return int
     */
    public function countForAdmin(): int
    {
        return $this->countAll();
    }

    /**
     * Retourne une actualité par son identifiant.
     *
     * @param int $id Identifiant de l'actualité
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, title, text_content, image_path, link_path, slug, created_at, updated_at
             FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * Crée une nouvelle actualité.
     *
     * @param array<string, mixed> $data Données de l'actualité (title, text_content, slug…)
     * @return int Identifiant de l'actualité créée
     */
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

    /**
     * Met à jour une actualité existante.
     *
     * @param int                  $id   Identifiant de l'actualité
     * @param array<string, mixed> $data Nouvelles données (title, text_content, slug…)
     * @return void
     */
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
