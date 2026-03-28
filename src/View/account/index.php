<?php
/** @var array<string, mixed>|false $account */
$displayName = '';
if ($account) {
    $displayName = $account['account_type'] === 'company'
        ? ($account['company_name'] ?? '')
        : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
}
$pageTitle     = __('account.title');
$activeSection = 'index';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <?php if ($info ?? null) : ?>
                <p class="alert alert--info"><?= htmlspecialchars($info) ?></p>
            <?php endif; ?>

            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.welcome') ?></h1>
                <?php if ($displayName !== '') : ?>
                    <p class="account-header__name"><?= htmlspecialchars($displayName) ?></p>
                <?php endif; ?>
            </header>

            <div class="account-dashboard">
                <?php if (!$isCompany) : ?>
                <a class="account-card" href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes">
                    <span class="account-card__count"><?= (int) $orderCount ?></span>
                    <span class="account-card__label"><?= __('panel.orders') ?></span>
                </a>

                <a class="account-card" href="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses">
                    <span class="account-card__count"><?= (int) $addressCount ?></span>
                    <span class="account-card__label"><?= __('panel.addresses') ?></span>
                </a>
                <?php endif; ?>

                <a class="account-card" href="/<?= htmlspecialchars($lang) ?>/mon-compte/favoris">
                    <span class="account-card__count"><?= (int) $favoriteCount ?></span>
                    <span class="account-card__label"><?= __('panel.favorites') ?></span>
                </a>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
