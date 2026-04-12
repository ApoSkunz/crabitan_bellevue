<?php

/**
 * Câblage des dépendances applicatives.
 *
 * Ce fichier est le point central de configuration du DI container.
 * Enregistrer ici toutes les dépendances qui nécessitent d'être
 * partagées (singleton) ou recréées (bind) à chaque résolution.
 *
 * Usage dans les controllers (futur) :
 *   $accountService = $container->get(AccountService::class);
 */

declare(strict_types=1);

use Core\Container;
use Model\AccountModel;
use Service\AccountService;
use Service\MailService;
use Service\RateLimiterService;
use Service\RgpdExportService;

$container = new Container();

// ── Infrastructure ──────────────────────────────────────────────────────────

$container->singleton(AccountModel::class, fn(Container $c) =>
    new AccountModel());

$container->singleton(MailService::class, fn(Container $c) =>
    new MailService());

$container->singleton(RateLimiterService::class, fn(Container $c) =>
    new RateLimiterService());

// ── Services métier ─────────────────────────────────────────────────────────

$container->singleton(AccountService::class, fn(Container $c) =>
    new AccountService(
        $c->get(AccountModel::class),
        $c->get(MailService::class)
    ));

$container->singleton(RgpdExportService::class, fn(Container $c) =>
    new RgpdExportService());

return $container;
