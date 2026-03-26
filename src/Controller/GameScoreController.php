<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Model\GameScoreModel;

class GameScoreController extends Controller
{
    private const ALLOWED_GAMES = ['vendangeuse', 'memo', 'tracteur'];
    private const MAX_SCORE     = 99999;

    /**
     * POST /api/jeux/score
     * Body JSON : {"game":"vendangeuse","score":123}
     * Réponse   : {"record":456,"new_record":true}
     */
    public function save(array $params): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        header('Content-Type: application/json; charset=utf-8');

        $body  = (string) file_get_contents('php://input');
        $data  = json_decode($body, true);

        $game  = isset($data['game'])  ? trim((string) $data['game'])  : '';
        $score = isset($data['score']) ? (int) $data['score']          : -1;

        if (!in_array($game, self::ALLOWED_GAMES, true) || $score < 0 || $score > self::MAX_SCORE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            return;
        }

        $model     = new GameScoreModel();
        $newRecord = $model->updateIfBetter($game, $score);
        $record    = $model->getBestScore($game);

        echo json_encode(['record' => $record, 'new_record' => $newRecord]);
    }

    /**
     * GET /api/jeux/score?game=vendangeuse
     */
    public function get(array $params): void // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        header('Content-Type: application/json; charset=utf-8');

        $game = isset($_GET['game']) ? trim((string) $_GET['game']) : '';

        if (!in_array($game, self::ALLOWED_GAMES, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown game']);
            return;
        }

        $model  = new GameScoreModel();
        $record = $model->getBestScore($game);
        echo json_encode(['record' => $record]);
    }
}
