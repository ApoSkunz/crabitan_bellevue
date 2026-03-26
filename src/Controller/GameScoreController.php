<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\GameScoreModel;

class GameScoreController extends Controller
{
    private const ALLOWED_GAMES = ['vendangeuse', 'memo', 'labour', 'catapulte', 'vendangeexpress'];
    private const MAX_SCORE     = 99999;

    /**
     * POST /api/jeux/score
     * Body JSON : {"game":"vendangeuse","score":123}
     * Réponse   : {"record":456,"new_record":true}
     */
    public function save(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $body  = (string) file_get_contents('php://input');
        $data  = json_decode($body, true);

        $game  = isset($data['game'])  ? trim((string) $data['game'])  : '';
        $score = isset($data['score']) ? (int) $data['score']          : -1;

        if (!in_array($game, self::ALLOWED_GAMES, true) || $score < 0 || $score > self::MAX_SCORE) {
            $this->json(['error' => 'Invalid payload'], 400);
        }

        $model     = new GameScoreModel();
        $newRecord = $model->updateIfBetter($game, $score);
        $record    = $model->getBestScore($game);

        $this->json(['record' => $record, 'new_record' => $newRecord]);
    }

    /**
     * GET /api/jeux/score?game=vendangeuse
     */
    public function get(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $game = isset($_GET['game']) ? trim((string) $_GET['game']) : '';

        if (!in_array($game, self::ALLOWED_GAMES, true)) {
            $this->json(['error' => 'Unknown game'], 400);
        }

        $model  = new GameScoreModel();
        $record = $model->getBestScore($game);
        $this->json(['record' => $record]);
    }
}
