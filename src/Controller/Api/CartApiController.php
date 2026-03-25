<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;

/**
 * Stub CartApiController — API AJAX du panier (feat/cart à venir).
 */
class CartApiController extends Controller
{
    private const MSG_NOT_IMPLEMENTED = 'Not implemented';

    public function add(array $params): void // NOSONAR — stub, $params requis par le router
    {
        $this->json(['success' => false, 'message' => self::MSG_NOT_IMPLEMENTED], 501);
    }

    public function update(array $params): void // NOSONAR — stub, $params requis par le router
    {
        $this->json(['success' => false, 'message' => self::MSG_NOT_IMPLEMENTED], 501);
    }

    public function remove(array $params): void // NOSONAR — stub, $params requis par le router
    {
        $this->json(['success' => false, 'message' => self::MSG_NOT_IMPLEMENTED], 501);
    }
}
