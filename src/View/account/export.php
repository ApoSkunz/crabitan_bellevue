<?php
$pageTitle     = __('account.export_title');
$activeSection = 'export';
$noindex       = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var string $maskedEmail */
/** @var string|null $sent         Adresse masquée si l'export vient d'être envoyé */
/** @var string|null $error        Message d'erreur éventuel */
/** @var string|null $lastExportAt Date/heure de la dernière demande (format d/m/Y à H:i) ou null */
/** @var string $csrf */
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.export_title') ?></h1>
            </header>

            <?php if ($sent !== null) : ?>
                <div class="alert alert--success" role="alert">
                    <strong><?= __('account.export_sent_title') ?></strong><br>
                    <?= sprintf(__('account.export_sent_body'), htmlspecialchars($sent)) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== null) : ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <section class="account-section">
                <p class="account-export__intro"><?= __('account.export_intro') ?></p>

                <p><strong><?= __('account.export_includes') ?></strong></p>
                <ul class="account-export__list">
                    <li><?= __('account.export_item_account') ?></li>
                    <li><?= __('account.export_item_orders') ?></li>
                    <li><?= __('account.export_item_addresses') ?></li>
                    <li><?= __('account.export_item_favorites') ?></li>
                    <li><?= __('account.export_item_trusted_devices') ?></li>
                    <li><?= __('account.export_item_sessions') ?></li>
                </ul>

                <p class="account-export__retention"><?= __('account.export_retention') ?></p>

                <p class="account-export__meta">
                    <?= __('account.export_last_request') ?> :
                    <strong>
                        <?= $lastExportAt !== null
                            ? htmlspecialchars($lastExportAt)
                            : __('account.export_no_request') ?>
                    </strong><br>
                    <?= __('account.export_send_to') ?> :
                    <strong><?= htmlspecialchars($maskedEmail) ?></strong>
                </p>

                <form method="POST"
                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/export/envoyer">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="btn btn--gold">
                        <?= __('account.export_send_btn') ?>
                    </button>
                </form>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
