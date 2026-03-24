<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;

/**
 * Stub CartApiController — API AJAX du panier (feat/cart à venir).
 */
class CartApiController extends Controller
{
    public function add(array $params): void
    {
        $this->json(['success' => false, 'message' => 'Not implemented'], 501);
    }

    public function update(array $params): void
    {
        $this->json(['success' => false, 'message' => 'Not implemented'], 501);
    }

    public function remove(array $params): void
    {
        $this->json(['success' => false, 'message' => 'Not implemented'], 501);
    }
}
