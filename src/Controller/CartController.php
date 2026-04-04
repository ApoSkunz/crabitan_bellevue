<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Jwt;
use Model\AccountModel;
use Model\CartModel;
use Model\PricingRuleModel;
use Model\WineModel;

/**
 * Contrôleur de la page panier.
 *
 * L'index est accessible à tous (invités et connectés).
 * Pour les utilisateurs connectés, le contenu du panier est chargé depuis la BDD.
 * Pour les invités, la vue reçoit un tableau vide — le JS lit le cookie cb-cart.
 * Les admins sont redirigés vers /admin.
 */
class CartController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/panier
    // ----------------------------------------------------------------

    /**
     * Affiche la page du panier.
     *
     * Si l'utilisateur est connecté (JWT valide), charge les articles depuis la BDD.
     * Si l'utilisateur est un invité, passe un tableau vide à la vue (le JS lit le cookie cb-cart).
     * Un vin aléatoire est passé à la vue (auth panier vide ou invité) pour suggestion d'achat.
     * Les admins/super_admins sont redirigés vers /admin.
     * Les clients B2B (account_type = 'company') voient un message de contact à la place du panier.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function index(array $params): void
    {
        $this->denyAdmin();

        $lang        = $this->resolveLang($params);
        $cartItems   = [];
        $isAuth      = false;
        $isB2B       = false;
        $pricingRule = null;
        $nextTier    = null;
        $totalQty    = 0;

        $userId = $this->resolveUserId();
        if ($userId !== null) {
            $isAuth       = true;
            $accountModel = new AccountModel();
            $account      = $accountModel->findById($userId);
            $isB2B        = ($account !== false && ($account['account_type'] ?? '') === 'company');

            if (!$isB2B) {
                $cartModel = new CartModel();
                $row       = $cartModel->findByUserId($userId);
                if ($row !== false) {
                    $cartItems = $cartModel->getContent($row);
                    $cartItems = $this->enrichItemsWithPrice($cartItems);
                }

                if (!empty($cartItems)) {
                    $totalQty     = (int) array_sum(array_column($cartItems, 'qty'));
                    $pricingModel = new PricingRuleModel();
                    $pricingRule  = $pricingModel->findForQuantity($totalQty);
                    $nextTier     = $pricingModel->findNextTierFor($totalQty);
                }
            }
        }

        $pricingModel    = $pricingModel ?? new PricingRuleModel();
        $pricingRules    = $pricingModel->findAllActive();

        $deliveryDiscount = 0.0;
        if ($pricingRule !== null) {
            $deliveryPrice    = (float) ($pricingRule['delivery_price'] ?? 0.0);
            $priceType        = (string) ($pricingRule['price_type'] ?? 'fixed');
            $deliveryDiscount = $priceType === 'per_bottle'
                ? round($deliveryPrice * $totalQty, 2)
                : $deliveryPrice;
        }
        $subtotal = array_sum(array_map(
            fn(array $i): float => (float)($i['price'] ?? 0.0) * (int)($i['qty'] ?? 1),
            $cartItems
        ));

        // Cookie badge count — évite le flash à 0 côté JS au chargement de page
        if ($isAuth && !$isB2B) {
            setcookie('cb-cart-count', (string) $totalQty, ['expires' => time() + 7 * 24 * 3600, 'path' => '/', 'secure' => false, 'httponly' => false, 'samesite' => 'Lax']); // phpcs:ignore Generic.Files.LineLength
        }

        $randomWine = (!$isB2B && empty($cartItems))
            ? (new WineModel())->getRandomForCart()
            : null;

        $this->view('cart/index', [
            'lang'             => $lang,
            'cartItems'        => $cartItems,
            'isAuth'           => $isAuth,
            'isB2B'            => $isB2B,
            'pricingRule'      => $pricingRule,
            'nextTier'         => $nextTier,
            'totalQty'         => $totalQty,
            'subtotal'         => $subtotal,
            'deliveryDiscount' => $deliveryDiscount,
            'pricingRules'     => $pricingRules,
            'randomWine'       => $randomWine,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/panier/ajouter  (fallback sans JS — non implémenté)
    // ----------------------------------------------------------------

    /**
     * Fallback POST pour l'ajout au panier sans JS.
     * Redirige vers la page panier (l'API AJAX est la voie normale).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function add(array $params): void
    {
        $this->denyAdmin();
        $this->redirectToCart($params);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/panier/modifier  (fallback sans JS — non implémenté)
    // ----------------------------------------------------------------

    /**
     * Fallback POST pour la modification du panier sans JS.
     * Redirige vers la page panier.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function update(array $params): void // NOSONAR — php:S4144 : fallback intentionnel, API AJAX est la voie principale
    {
        $this->denyAdmin();
        $this->redirectToCart($params);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/panier/supprimer  (fallback sans JS — non implémenté)
    // ----------------------------------------------------------------

    /**
     * Fallback POST pour la suppression d'un article sans JS.
     * Redirige vers la page panier.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function remove(array $params): void // NOSONAR — php:S4144 : fallback intentionnel, API AJAX est la voie principale
    {
        $this->denyAdmin();
        $this->redirectToCart($params);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Enrichit chaque article du panier avec le prix récupéré depuis WineModel.
     * Les champs name et image sont aussi complétés si absents.
     *
     * @param array<int, array<string, mixed>> $items Articles du panier (wine_id, qty, name, image)
     * @return array<int, array<string, mixed>>
     */
    private function enrichItemsWithPrice(array $items): array
    {
        $wineModel = new WineModel();
        foreach ($items as &$item) {
            $wine          = $wineModel->getById((int) $item['wine_id']);
            $rawImage      = $wine['image_path'] ?? '';
            $fullImage     = $rawImage !== '' ? '/assets/images/wines/' . $rawImage : '';
            $item['price']            = $wine !== null ? (float) ($wine['price'] ?? 0.0) : 0.0;
            $item['image']            = ($item['image'] ?? '') !== '' ? $item['image'] : $fullImage;
            $item['name']             = ($item['name']  ?? '') !== '' ? $item['name']  : ($wine['label_name'] ?? '');
            $item['is_cuvee_speciale'] = $wine !== null ? (bool) ($wine['is_cuvee_speciale'] ?? false) : false;
        }
        unset($item);
        return $items;
    }

    /**
     * Redirige vers la page panier dans la langue courante.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    private function redirectToCart(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->redirect('/' . $lang . '/panier');
    }

    /**
     * Refuse l'accès aux administrateurs en les redirigeant vers /admin.
     *
     * @return void
     */
    private function denyAdmin(): void
    {
        $token = $_COOKIE['auth_token'] ?? null;
        if ($token === null) {
            return;
        }
        try {
            $payload = Jwt::decode($token);
        } catch (\Throwable) {
            return; // Token invalide : on laisse passer, la page gérera l'auth
        }
        if (in_array($payload['role'] ?? '', ['admin', 'super_admin'], true)) {
            $this->redirect('/admin');
        }
    }

    /**
     * Résout l'ID utilisateur depuis le cookie JWT auth_token.
     * Retourne null si le cookie est absent ou le JWT invalide/expiré.
     *
     * @return int|null Identifiant de l'utilisateur connecté, ou null
     */
    private function resolveUserId(): ?int
    {
        $token = $_COOKIE['auth_token'] ?? null;
        if ($token === null) {
            return null;
        }
        try {
            $payload = Jwt::decode($token);
            $id      = (int) ($payload['sub'] ?? 0);
            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
