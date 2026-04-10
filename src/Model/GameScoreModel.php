<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des records de score pour les mini-jeux du site.
 */
class GameScoreModel extends Model
{
    protected string $table = 'game_scores';

    /**
     * Retourne le meilleur score enregistré pour un jeu donné.
     *
     * @param string $game Identifiant du jeu
     * @return int Score maximum ou 0 si aucun score existant
     */
    public function getBestScore(string $game): int
    {
        $row = $this->db->fetchOne(
            "SELECT score FROM {$this->table} WHERE game = ?",
            [$game]
        );
        return $row ? (int) $row['score'] : 0;
    }

    /**
     * Met à jour le record si le score soumis est supérieur.
     *
     * @return bool true si nouveau record
     */
    public function updateIfBetter(string $game, int $score): bool
    {
        $current = $this->getBestScore($game);
        if ($score <= $current) {
            return false;
        }
        $this->db->execute(
            "INSERT INTO {$this->table} (game, score)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE score = ?, achieved_at = NOW()",
            [$game, $score, $score]
        );
        return true;
    }
}
