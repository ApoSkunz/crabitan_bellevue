<?php
$pageTitle     = __('account.export_title');
$activeSection = 'export';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.export_title') ?></h1>
            </header>

            <section class="account-section">
                <p class="account-export__intro"><?= __('account.export_intro') ?></p>

                <p><strong><?= __('account.export_includes') ?></strong></p>
                <ul class="account-export__list">
                    <li><?= __('account.export_item_account') ?></li>
                    <li><?= __('account.export_item_orders') ?></li>
                    <li><?= __('account.export_item_addresses') ?></li>
                    <li><?= __('account.export_item_favorites') ?></li>
                </ul>

                <p class="account-export__retention"><?= __('account.export_retention') ?></p>

                <p class="account-export__generated">
                    <?= __('account.export_at') ?> : <?= htmlspecialchars(date('d/m/Y à H:i')) ?>
                </p>

                <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/export/telecharger"
                   class="btn btn--gold">
                    <?= __('account.export_download') ?>
                </a>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
