<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;

/**
 * Stub CartController — gestion du panier (feat/cart à venir).
 * Les routes POST redirigent vers le panier jusqu'à l'implémentation complète.
 */
class CartController extends Controller
{
    // ----------------------------------------------------------------
    // GET /{lang}/panier
    // ----------------------------------------------------------------

    public function index(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('cart/index', ['lang' => $lang]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/panier/ajouter
    // ----------------------------------------------------------------

    public function add(array $params): void
    {
        $this->redirectToCart($params);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/panier/modifier
    // ----------------------------------------------------------------

    public function update(array $params): void
    {
        $this->redirectToCart($params);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/panier/supprimer
    // ----------------------------------------------------------------

    public function remove(array $params): void
    {
        $this->redirectToCart($params);
    }

    // ----------------------------------------------------------------

    private function redirectToCart(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->redirect('/' . $lang . '/panier');
    }
}
