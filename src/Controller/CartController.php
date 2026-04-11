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

        // Flash : articles retirés du panier lors d'un aller checkout → retour panier
        $removedCount = (int) ($_SESSION['flash']['cart_wines_removed'] ?? 0);
        unset($_SESSION['flash']['cart_wines_removed']);

        $userId = $this->resolveUserId();
        if ($userId !== null) {
            $isAuth       = true;
            $cartData     = $this->loadCartForUser($userId);
            $isB2B        = $cartData['isB2B'];
            $cartItems    = $cartData['cartItems'];
            $totalQty     = $cartData['totalQty'];
            $pricingRule  = $cartData['pricingRule'];
            $nextTier     = $cartData['nextTier'];
            $pricingModel = $cartData['pricingModel'] ?? null;
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

        $randomWine     = !$isB2B ? (new WineModel())->getRandomForCart() : null;
        $crossSellWines = [];
        $featuredWine   = null;

        if (!$isB2B) {
            if ($isAuth) {
                $crossSellData  = $this->resolveCrossSell($cartItems);
                $crossSellWines = $crossSellData['crossSellWines'];
                $featuredWine   = $crossSellData['featuredWine'];
            } else {
                // Invité : panier cookie inconnu côté serveur → 3 vins aléatoires
                $crossSellWines = (new WineModel())->getForCartCrossSell([], 3);
            }
        }

        $this->view('cart/index', [
            'lang'             => $lang,
            'removedCount'     => $removedCount,
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
            'crossSellWines'   => $crossSellWines,
            'featuredWine'     => $featuredWine,
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
     * Charge le contenu du panier et les données de tarification pour un utilisateur connecté.
     *
     * Retourne les données B2B, articles enrichis, quantité totale et règle de tarification.
     *
     * @param int $userId Identifiant de l'utilisateur connecté
     * @return array<string, mixed>
     */
    private function loadCartForUser(int $userId): array
    {
        $account = (new AccountModel())->findById($userId);
        $isB2B   = ($account !== false && ($account['account_type'] ?? '') === 'company');

        $cartItems    = [];
        $totalQty     = 0;
        $pricingRule  = null;
        $nextTier     = null;
        $pricingModel = null;

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

        return [
            'isB2B'        => $isB2B,
            'cartItems'    => $cartItems,
            'totalQty'     => $totalQty,
            'pricingRule'  => $pricingRule,
            'nextTier'     => $nextTier,
            'pricingModel' => $pricingModel,
        ];
    }

    /**
     * Enrichit chaque article du panier avec le prix récupéré depuis WineModel.
     * Les champs name et image sont aussi complétés si absents.
     * Les articles dont le vin est indisponible (available=0) sont silencieusement exclus.
     *
     * @param array<int, array<string, mixed>> $items Articles du panier (wine_id, qty, name, image)
     * @return array<int, array<string, mixed>>
     */
    private function enrichItemsWithPrice(array $items): array
    {
        $wineModel = new WineModel();
        $enriched = [];
        foreach ($items as $item) {
            $wine = $wineModel->getById((int) $item['wine_id']);

            // Filtrer silencieusement les vins indisponibles (available=0)
            if ($wine === null || !(bool) ($wine['available'] ?? false)) {
                continue;
            }

            $rawImage                  = $wine['image_path'] ?? '';
            $fullImage                 = $rawImage !== '' ? '/assets/images/wines/' . $rawImage : '';
            $item['price']             = (float) ($wine['price'] ?? 0.0);
            $item['image']             = ($item['image'] ?? '') !== '' ? $item['image'] : $fullImage;
            $item['name']              = ($item['name']  ?? '') !== '' ? $item['name']  : ($wine['label_name'] ?? '');
            $item['vintage']           = (int) ($wine['vintage'] ?? 0);
            $item['is_cuvee_speciale'] = (bool) ($wine['is_cuvee_speciale'] ?? false);
            $enriched[] = $item;
        }
        return $enriched;
    }

    /**
     * Résout la suggestion de vins cross-sell selon le contenu du panier.
     *
     * - Panier vide  → featuredWine (getFeatured/getLatest), crossSellWines vide
     * - 1 vin unique → vins similaires (même couleur, limit 3)
     * - 2+ vins uniques → 1 vin aléatoire hors panier
     *
     * @param  array<int, array<string, mixed>> $cartItems Articles du panier
     * @return array{crossSellWines: array<int, array<string, mixed>>, featuredWine: array<string, mixed>|null}
     */
    private function resolveCrossSell(array $cartItems): array
    {
        $wineModel      = new WineModel();
        $crossSellWines = [];
        $featuredWine   = null;

        $uniqueWineIds = array_unique(array_column($cartItems, 'wine_id'));
        $uniqueCount   = count($uniqueWineIds);

        if ($uniqueCount === 0) {
            // Panier vide — vin mis en avant ou dernier vin
            $featured = $wineModel->getFeatured();
            $featuredWine = $featured ?? ($wineModel->getLatest(1)[0] ?? null);
        } elseif ($uniqueCount === 1) {
            // 1 vin unique — vins similaires + au moins 1 cuvée spéciale si le vin du panier n'en est pas une
            $firstItem       = reset($cartItems);
            $wineId          = (int) ($firstItem['wine_id'] ?? 0);
            $wine            = $wineModel->getById($wineId);
            $color           = (string) ($wine['wine_color'] ?? '');
            $price           = (float) ($wine['price'] ?? 0.0);
            $cartIsCuvee     = !empty($wine['is_cuvee_speciale']);
            $crossSellWines  = $wineModel->getSimilar($wineId, $color, $price, 3);

            if (!$cartIsCuvee) {
                $hasCuvee = !empty(array_filter($crossSellWines, fn(array $w): bool => !empty($w['is_cuvee_speciale'])));
                if (!$hasCuvee) {
                    $excludeIds = array_map(fn(array $w): int => (int) $w['id'], $crossSellWines);
                    $excludeIds[] = $wineId;
                    $cuvee = $wineModel->getCuveeSpecialeByColor($color, $excludeIds);
                    if ($cuvee !== null) {
                        array_pop($crossSellWines);
                        array_unshift($crossSellWines, $cuvee);
                    }
                }
            }
        } else {
            // 2+ vins — 1 vin aléatoire hors panier
            $excludeIds     = array_map('intval', $uniqueWineIds);
            $crossSellWines = $wineModel->getForCartCrossSell($excludeIds, 1);
        }

        return ['crossSellWines' => $crossSellWines, 'featuredWine' => $featuredWine];
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
