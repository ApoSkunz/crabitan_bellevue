<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Jwt;
use Model\CartModel;
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
     * Si l'utilisateur est un invité, passe un tableau vide à la vue.
     * Les admins/super_admins sont redirigés vers /admin.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function index(array $params): void
    {
        $this->denyAdmin();

        $lang      = $this->resolveLang($params);
        $cartItems = [];
        $isAuth    = false;

        $userId = $this->resolveUserId();
        if ($userId !== null) {
            $isAuth    = true;
            $cartModel = new CartModel();
            $row       = $cartModel->findByUserId($userId);
            if ($row !== false) {
                $cartItems = $cartModel->getContent($row);
                $cartItems = $this->enrichItemsWithPrice($cartItems);
            }
        }

        $this->view('cart/index', [
            'lang'      => $lang,
            'cartItems' => $cartItems,
            'isAuth'    => $isAuth,
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
            $item['price'] = $wine !== null ? (float) ($wine['price'] ?? 0.0) : 0.0;
            $item['image'] = ($item['image'] ?? '') !== '' ? $item['image'] : ($wine['image_path'] ?? '');
            $item['name']  = ($item['name']  ?? '') !== '' ? $item['name']  : ($wine['label_name'] ?? '');
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
            if (in_array($payload['role'] ?? '', ['admin', 'super_admin'], true)) {
                $this->redirect('/admin');
            }
        } catch (\Throwable) {
            // Token invalide : on laisse passer, la page gérera l'auth
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
