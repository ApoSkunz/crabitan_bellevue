<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Middleware\AuthMiddleware;
use Model\FavoriteModel;

class FavoriteApiController extends Controller
{
    // ----------------------------------------------------------------
    // POST /api/favorites/toggle
    // ----------------------------------------------------------------

    public function toggle(array $params): void // NOSONAR — $params requis par le router
    {
        $payload = AuthMiddleware::handle();
        $userId  = (int) $payload['sub'];

        $body   = $this->request->body;
        $wineId = isset($body['wine_id']) ? (int) $body['wine_id'] : 0;

        if ($wineId <= 0) {
            $this->json(['success' => false, 'message' => 'wine_id invalide'], 422);
        }

        $model = new FavoriteModel();
        $liked = $model->toggle($userId, $wineId);

        $this->json(['success' => true, 'liked' => $liked]);
    }
}
