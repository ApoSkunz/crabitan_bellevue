<?php
$pageTitle     = __('panel.invoices');
$activeSection = 'invoices';
$noindex       = true;
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
                <p class="account-empty"><?= __('account.no_invoices') ?></p>
            <?php else : ?>
                <div class="account-table-wrap">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th><?= __('account.order_invoice') ?></th>
                                <th><?= __('account.order_ref') ?></th>
                                <th><?= __('account.order_date') ?></th>
                                <th style="text-align:right;"><?= __('account.order_price') ?></th>
                                <th style="text-align:center;"><?= __('account.download_invoice') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv) : ?>
                                <tr>
                                    <td data-label="<?= htmlspecialchars(__('account.order_invoice')) ?>">
                                        <span class="account-table__ref">
                                            <?= htmlspecialchars(
                                                (string) ($inv['invoice_number'] ?? $inv['order_reference'])
                                            ) ?>
                                        </span>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(__('account.order_ref')) ?>" style="font-size:0.85rem;color:var(--color-text-muted);">
                                        <?= htmlspecialchars((string) $inv['order_reference']) ?>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(__('account.order_date')) ?>" style="white-space:nowrap;">
                                        <?= date('d/m/Y', strtotime((string) $inv['ordered_at'])) ?>
                                    </td>
                                    <td data-label="<?= htmlspecialchars(__('account.order_price')) ?>" style="font-weight:600;">
                                        <?= number_format((float) $inv['price'], 2, ',', '&nbsp;') ?>&nbsp;&euro;
                                    </td>
                                    <td data-label="<?= htmlspecialchars(__('account.download_invoice')) ?>">
                                        <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $inv['order_id'] ?>/facture"
                                           class="btn btn--ghost btn--sm"
                                           target="_blank" rel="noopener noreferrer">
                                            <?= __('account.download_invoice_detail') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
