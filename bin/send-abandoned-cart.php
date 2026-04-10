#!/usr/bin/env php
<?php

/**
 * Cron — Relance panier abandonné J+3.
 *
 * Envoie un email de relance aux utilisateurs dont le panier n'a pas été
 * commandé depuis 72 à 96 heures (fenêtre J+3).
 *
 * Usage : php bin/send-abandoned-cart.php
 * Planification recommandée : 0 9 * * * (tous les jours à 9h)
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

use Model\AccountModel;
use Model\CartModel;
use Model\WineModel;
use Service\MailService;

$cartModel    = new CartModel();
$accountModel = new AccountModel();
$wineModel    = new WineModel();
$mailService  = new MailService();

$appUrl  = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

/**
 * Récupère les paniers abandonnés dans la fenêtre J+3 (72h–96h).
 *
 * @var array<int, array<string, mixed>> $abandoned
 */
$abandoned = $cartModel->getAbandoned(72, 96);

$sent   = 0;
$errors = 0;

foreach ($abandoned as $cart) {
    $userId = (int) ($cart['user_id'] ?? 0);
    if ($userId <= 0) {
        continue;
    }

    $account = $accountModel->findById($userId);
    if ($account === false) {
        error_log("[send-abandoned-cart] Compte introuvable pour user_id={$userId}");
        $errors++;
        continue;
    }

    $email = (string) ($account['email'] ?? '');
    if ($email === '') {
        error_log("[send-abandoned-cart] Email vide pour user_id={$userId}");
        $errors++;
        continue;
    }

    $firstname = (string) ($account['firstname'] ?? $account['company_name'] ?? 'Client');
    $lang      = in_array((string) ($account['lang'] ?? ''), ['fr', 'en'], true)
        ? (string) $account['lang']
        : 'fr';

    // Enrichir les items du panier avec les données produit (nom, image, prix)
    $rawItems    = $cartModel->getContent($cart);
    $enrichedItems = [];

    foreach ($rawItems as $item) {
        $wineId = (int) ($item['wine_id'] ?? 0);
        if ($wineId <= 0) {
            continue;
        }

        $wine = $wineModel->findById($wineId);
        if ($wine === false) {
            continue;
        }

        $enrichedItems[] = [
            'label_name' => (string) ($wine['label_name'] ?? ''),
            'vintage'    => (int) ($wine['vintage'] ?? 0),
            'image_path' => (string) ($wine['image_path'] ?? ''),
            'price'      => (float) ($wine['price'] ?? 0.0),
            'qty'        => (int) ($item['qty'] ?? 0),
        ];
    }

    if (empty($enrichedItems)) {
        continue;
    }

    $cartUrl = $lang === 'fr'
        ? $appUrl . '/fr/panier'
        : $appUrl . '/en/cart';

    try {
        $mailService->sendAbandonedCart($email, $firstname, $enrichedItems, $lang, $cartUrl);
        $sent++;
        echo "[send-abandoned-cart] Email relance envoyé à {$email} (user_id={$userId})\n";
    } catch (\Throwable $e) {
        error_log("[send-abandoned-cart] Erreur envoi à {$email}: " . $e->getMessage());
        $errors++;
    }
}

echo "[send-abandoned-cart] Terminé — {$sent} email(s) envoyé(s), {$errors} erreur(s).\n";
