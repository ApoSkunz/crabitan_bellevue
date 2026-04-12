<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\GameScoreModel;
use Service\RateLimiterService;

class GameScoreController extends Controller
{
    private const ALLOWED_GAMES  = ['vendangeuse', 'memo', 'labour', 'catapulte', 'vendangeexpress'];
    private const MAX_SCORE      = 99999;
    private const MAX_SAVE_RATE  = 20;  // VULN-007 : max 20 soumissions par minute par IP
    private const WINDOW_RATE    = 60;

    /**
     * POST /api/jeux/score
     * Body JSON : {"game":"vendangeuse","score":123}
     * Réponse   : {"record":456,"new_record":true}
     */
    public function save(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        // VULN-007 : rate limit par IP — 20 soumissions/minute
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limiter = new RateLimiterService();
        if (!$limiter->checkLimit('gamescore:' . $ip, self::MAX_SAVE_RATE, self::WINDOW_RATE)) {
            $this->json(['error' => 'Too Many Requests'], 429);
        }
        $limiter->recordAttempt('gamescore:' . $ip, self::WINDOW_RATE);

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
