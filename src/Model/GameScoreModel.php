<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class GameScoreModel extends Model
{
    protected string $table = 'game_scores';

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
