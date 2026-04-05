<?php
$pageTitle     = __('panel.invoices');
$activeSection = 'invoices';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $invoices */
/** @var string $lang */
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('panel.invoices') ?></h1>
            </header>

            <?php if (empty($invoices)) : ?>
                <div class="account-empty">
                    <p><?= __('account.no_invoices') ?></p>
                </div>
            <?php else : ?>
                <div class="account-orders-list">
                    <?php foreach ($invoices as $inv) : ?>
                        <div class="account-order-card">
                            <div class="account-order-card__header">
                                <span class="account-order-card__ref">
                                    <?php if ($inv['invoice_number']) : ?>
                                        <?= htmlspecialchars((string) $inv['invoice_number']) ?>
                                    <?php else : ?>
                                        <?= htmlspecialchars((string) $inv['order_reference']) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="account-order-card__date">
                                    <?= date('d/m/Y', strtotime((string) $inv['ordered_at'])) ?>
                                </span>
                                <span class="account-order-card__total">
                                    <?= number_format((float) $inv['price'], 2, ',', '&nbsp;') ?>&nbsp;&euro;
                                </span>
                            </div>
                            <div class="account-order-card__actions">
                                <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $inv['order_id'] ?>/facture"
                                   class="account-btn account-btn--outline">
                                    <?= __('account.download_invoice') ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
