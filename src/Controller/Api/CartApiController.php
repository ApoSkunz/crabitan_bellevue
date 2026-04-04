<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Core\Jwt;
use Model\CartModel;
use Model\WineModel;

/**
 * API AJAX du panier utilisateur (connecté uniquement).
 *
 * Toutes les actions exigent un JWT valide dans le cookie auth_token
 * et un token CSRF valide dans le corps POST.
 * Seuls wine_id + qty sont stockés — les détails (nom, image, prix) sont
 * récupérés à la demande via l'endpoint GET /api/cart/details.
 */
class CartApiController extends Controller
{
    private CartModel $cartModel;
    private WineModel $wineModel;

    /**
     * Initialise les modèles du panier et des vins.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->cartModel = new CartModel();
        $this->wineModel = new WineModel();
    }

    // ----------------------------------------------------------------
    // GET /api/cart/details?ids=1,2,3
    // ----------------------------------------------------------------

    /**
     * Retourne le nom, l'image et le prix des vins dont les IDs sont passés en paramètre.
     *
     * Accessible sans authentification (invités + connectés).
     * Paramètre GET : ids (liste d'entiers séparés par des virgules).
     * Retourne : [{wine_id, name, image, price}].
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function details(array $params): void // NOSONAR — $params requis par le router
    {
        $raw = $_GET['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $raw)));

        if (empty($ids)) {
            $this->json([]);
        }

        $result = [];
        foreach (array_unique($ids) as $id) {
            $wine = $this->wineModel->getById($id);
            if ($wine !== null) {
                $rawImage = $wine['image_path'] ?? '';
                $result[] = [
                    'wine_id' => (int) $id,
                    'name'    => $wine['label_name'] ?? '',
                    'image'   => $rawImage !== '' ? '/assets/images/wines/' . $rawImage : '',
                    'price'   => (float) ($wine['price'] ?? 0),
                ];
            }
        }

        $this->json($result);
    }

    // ----------------------------------------------------------------
    // GET /api/cart/count
    // ----------------------------------------------------------------

    /**
     * Retourne le nombre total d'articles dans le panier de l'utilisateur connecté.
     *
     * Retourne : {total_quantity: int}.
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function count(array $params): void // NOSONAR — $params requis par le router
    {
        $userId = $this->requireAuth();

        $existing       = $this->cartModel->findByUserId($userId);
        $items          = $existing !== false ? $this->cartModel->getContent($existing) : [];
        $totalQuantity  = array_sum(array_column($items, 'qty'));

        $this->json(['total_quantity' => (int) $totalQuantity]);
    }

    // ----------------------------------------------------------------
    // POST /api/cart/add
    // ----------------------------------------------------------------

    /**
     * Ajoute ou cumule un article dans le panier BDD de l'utilisateur.
     *
     * Entrée POST attendue : wine_id (int), quantity (int), csrf_token (string).
     * Retourne : {success, total_quantity, items}.
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function add(array $params): void // NOSONAR — $params requis par le router
    {
        $userId = $this->requireAuth();
        $this->requireCsrf();

        $wineId   = (int) $this->request->post('wine_id', '0');
        $quantity = (int) $this->request->post('quantity', '1');

        if ($wineId <= 0) {
            $this->json(['success' => false, 'message' => 'wine_id invalide'], 422);
        }

        $wine  = $this->wineModel->getById($wineId);
        $stock = $wine !== null ? (int) ($wine['quantity'] ?? 0) : 0;

        if ($wine === null || $stock <= 0) {
            $this->json(['success' => false, 'message' => 'Vin indisponible'], 422);
        }

        // Charger le panier existant et mettre à jour
        $existing = $this->cartModel->findByUserId($userId);
        $items    = $existing !== false ? $this->cartModel->getContent($existing) : [];

        $items = $this->upsertItem($items, $wineId, $quantity, $stock);

        $this->cartModel->save($userId, $items);

        $this->json([
            'success'        => true,
            'total_quantity' => array_sum(array_column($items, 'qty')),
            'items'          => $items,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/cart/update
    // ----------------------------------------------------------------

    /**
     * Met à jour la quantité d'un article du panier.
     *
     * Une quantité de 0 supprime l'article.
     * La quantité est plafonnée au stock disponible.
     * Entrée POST : wine_id (int), quantity (int), csrf_token (string).
     * Retourne : {success, total_quantity, items}.
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function update(array $params): void // NOSONAR — $params requis par le router
    {
        $userId = $this->requireAuth();
        $this->requireCsrf();

        $wineId   = (int) $this->request->post('wine_id', '0');
        $quantity = (int) $this->request->post('quantity', '0');

        if ($wineId <= 0) {
            $this->json(['success' => false, 'message' => 'wine_id invalide'], 422);
        }

        $existing = $this->cartModel->findByUserId($userId);
        $items    = $existing !== false ? $this->cartModel->getContent($existing) : [];

        if ($quantity <= 0) {
            // Supprimer l'item
            $items = array_values(array_filter(
                $items,
                fn(array $item): bool => (int) $item['wine_id'] !== $wineId
            ));
        } else {
            $wine  = $this->wineModel->getById($wineId);
            $stock = $wine !== null ? (int) ($wine['quantity'] ?? 0) : 0;

            $items = $this->upsertItem($items, $wineId, $quantity, $stock, true);
        }

        $this->cartModel->save($userId, $items);

        $this->json([
            'success'        => true,
            'total_quantity' => array_sum(array_column($items, 'qty')),
            'items'          => $items,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/cart/remove
    // ----------------------------------------------------------------

    /**
     * Supprime un article du panier.
     *
     * Entrée POST : wine_id (int), csrf_token (string).
     * Retourne : {success, total_quantity}.
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function remove(array $params): void // NOSONAR — $params requis par le router
    {
        $userId = $this->requireAuth();
        $this->requireCsrf();

        $wineId = (int) $this->request->post('wine_id', '0');

        if ($wineId <= 0) {
            $this->json(['success' => false, 'message' => 'wine_id invalide'], 422);
        }

        $existing = $this->cartModel->findByUserId($userId);
        $items    = $existing !== false ? $this->cartModel->getContent($existing) : [];

        $found = false;
        $items = array_values(array_filter(
            $items,
            function (array $item) use ($wineId, &$found): bool {
                if ((int) $item['wine_id'] === $wineId) {
                    $found = true;
                    return false;
                }
                return true;
            }
        ));

        if (!$found) {
            $this->json(['success' => false, 'message' => 'Article non trouvé dans le panier'], 404);
        }

        $this->cartModel->save($userId, $items);

        $this->json([
            'success'        => true,
            'total_quantity' => array_sum(array_column($items, 'qty')),
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Vérifie la présence d'un JWT valide dans le cookie auth_token.
     * Retourne l'ID utilisateur si authentifié, ou répond 401.
     *
     * @return int Identifiant de l'utilisateur connecté
     */
    private function requireAuth(): int
    {
        $token = $_COOKIE['auth_token'] ?? null;
        if ($token === null) {
            $this->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        try {
            $payload = Jwt::decode($token);
        } catch (\Throwable) {
            $this->json(['success' => false, 'message' => 'Token invalide'], 401);
        }

        $role = $payload['role'] ?? '';
        if (in_array($role, ['admin', 'super_admin'], true)) {
            $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        return (int) ($payload['sub'] ?? 0);
    }

    /**
     * Vérifie le token CSRF dans le corps POST.
     * Répond 403 si le token est absent ou invalide.
     *
     * @return void
     */
    private function requireCsrf(): void
    {
        $token = $this->request->post('csrf_token', '');
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
            $this->json(['success' => false, 'message' => 'Token CSRF invalide'], 403);
        }
    }

    /**
     * Insère ou met à jour un article dans la liste du panier.
     *
     * Seuls wine_id et qty sont stockés — les détails (nom, image, prix)
     * sont récupérés depuis la BDD à la demande via /api/cart/details.
     * En mode replace ($replace = true) la quantité est remplacée,
     * sinon elle est cumulée avec l'existante.
     * La quantité finale est plafonnée au stock.
     *
     * @param array<int, array<string, mixed>> $items   Liste actuelle des articles
     * @param int                              $wineId  Identifiant du vin
     * @param int                              $qty     Quantité à ajouter ou remplacer
     * @param int                              $stock   Stock disponible (plafond)
     * @param bool                             $replace True = remplace la qté, False = cumule
     * @return array<int, array<string, mixed>>
     */
    private function upsertItem(
        array $items,
        int $wineId,
        int $qty,
        int $stock,
        bool $replace = false
    ): array {
        $found = false;
        foreach ($items as &$item) {
            if ((int) $item['wine_id'] === $wineId) {
                $newQty      = $replace ? $qty : (int) $item['qty'] + $qty;
                $item['qty'] = min($newQty, $stock);
                $found       = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            $items[] = [
                'wine_id' => $wineId,
                'qty'     => min($qty, $stock),
            ];
        }

        return $items;
    }
}
