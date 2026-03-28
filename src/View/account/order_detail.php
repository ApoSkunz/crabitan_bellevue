<?php
$pageTitle     = __('account.order_detail') . ' ' . htmlspecialchars($order['order_reference'] ?? '');
$activeSection = 'orders';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<string, mixed> $order */
/** @var array<int, array<string, mixed>> $items */
/** @var float|null $shippingDiscount */
$statusColors = [
    'pending'          => 'grey',
    'paid'             => 'blue',
    'processing'       => 'orange',
    'shipped'          => 'purple',
    'delivered'        => 'green',
    'cancelled'        => 'red',
    'refunded'         => 'red',
    'return_requested' => 'orange',
];
$timeline = ['pending', 'paid', 'processing', 'shipped', 'delivered'];
$currentIdx  = array_search($order['status'], $timeline, true);
$cancellable = $order['status'] === 'pending';
$paymentMap  = [
    'card'     => __('account.payment.card'),
    'virement' => __('account.payment.virement'),
    'cheque'   => __('account.payment.cheque'),
];
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.order_detail') ?></h1>
                <a class="account-header__back" href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes">
                    <?= __('account.order_back') ?>
                </a>
            </header>

            <?php if ($success) : ?>
                <div class="alert alert--success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Résumé -->
            <section class="account-section">
                <div class="order-detail-header">
                    <dl class="order-detail-meta">
                        <dt><?= __('account.order_ref') ?></dt>
                        <dd><?= htmlspecialchars($order['order_reference']) ?></dd>

                        <dt><?= __('account.order_date') ?></dt>
                        <dd><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['ordered_at']))) ?></dd>

                        <dt><?= __('account.order_status') ?></dt>
                        <dd>
                            <span class="account-badge account-badge--<?= $statusColors[$order['status']] ?? 'grey' ?>">
                                <?= __('order.status.' . $order['status']) ?>
                            </span>
                        </dd>

                        <dt><?= __('account.order_payment') ?></dt>
                        <dd><?= htmlspecialchars($paymentMap[$order['payment_method'] ?? ''] ?? ucfirst($order['payment_method'] ?? '')) ?></dd>
                    </dl>

                </div>
            </section>

            <!-- Timeline statut (commandes non annulées/remboursées) -->
            <?php if (!in_array($order['status'], ['cancelled', 'refunded'], true)) : ?>
                <section class="account-section">
                    <ol class="order-timeline" aria-label="<?= __('account.order_status') ?>">
                        <?php foreach ($timeline as $idx => $step) : ?>
                            <?php
                            $isDone    = $currentIdx !== false && $idx <= $currentIdx;
                            $isCurrent = $idx === $currentIdx;
                            ?>
                            <li class="order-timeline__step<?= $isDone ? ' order-timeline__step--done' : '' ?><?= $isCurrent ? ' order-timeline__step--current' : '' ?>">
                                <span class="order-timeline__dot"></span>
                                <span class="order-timeline__label"><?= __('order.status.' . $step) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <!-- Articles -->
            <section class="account-section">
                <h2 class="account-section__title"><?= __('account.order_items') ?></h2>
                <div class="account-table-wrap">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th><?= __('account.order_ref') ?></th>
                                <th><?= __('account.item_qty') ?></th>
                                <th><?= __('account.item_unit_price') ?></th>
                                <th><?= __('account.item_subtotal') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item) : ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($item['label_name'] ?? '—') ?>
                                        <?php if (!empty($item['vintage'])) : ?>
                                            <span class="account-table__vintage"><?= (int) $item['vintage'] ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['format'])) : ?>
                                            <span class="account-table__format"><?= htmlspecialchars($item['format']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) ($item['qty'] ?? 0) ?></td>
                                    <td><?= number_format((float) ($item['price'] ?? 0), 2, ',', ' ') ?> €</td>
                                    <td><?= number_format((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0), 2, ',', ' ') ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php
                            $itemsSubtotal = array_reduce($items, fn ($carry, $i) => $carry + (float) ($i['price'] ?? 0) * (int) ($i['qty'] ?? 0), 0.0);
                            ?>
                            <tr>
                                <td colspan="3"><?= __('account.order_subtotal') ?></td>
                                <td><?= number_format($itemsSubtotal, 2, ',', ' ') ?> €</td>
                            </tr>
                            <?php if ($shippingDiscount !== null && $shippingDiscount > 0.0) : ?>
                                <tr class="order-discount-row">
                                    <td colspan="3"><?= __('account.order_shipping_discount') ?></td>
                                    <td>− <?= number_format($shippingDiscount, 2, ',', ' ') ?> €</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="order-total-row">
                                <td colspan="3"><strong><?= __('account.order_total') ?></strong></td>
                                <td><strong><?= number_format((float) $order['price'], 2, ',', ' ') ?> €</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php if ($order['path_invoice']) : ?>
                    <p class="order-invoice-bottom">
                        <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>/facture"
                           class="btn btn--ghost btn--sm"
                           target="_blank" rel="noopener noreferrer">
                            <?= __('account.download_invoice_detail') ?>
                        </a>
                    </p>
                <?php endif; ?>
            </section>

            <!-- Adresses -->
            <div class="order-addresses">
                <section class="account-section order-address-block">
                    <h2 class="account-section__title"><?= __('account.order_billing') ?></h2>
                    <address class="order-address">
                        <?= htmlspecialchars($order['bill_civility'] . ' ' . $order['bill_firstname'] . ' ' . $order['bill_lastname']) ?><br>
                        <?= htmlspecialchars($order['bill_street'] ?? '') ?><br>
                        <?= htmlspecialchars(($order['bill_zip'] ?? '') . ' ' . ($order['bill_city'] ?? '')) ?><br>
                        <?= htmlspecialchars($order['bill_country'] ?? '') ?>
                        <?php if (!empty($order['bill_phone'])) : ?>
                            <br><?= htmlspecialchars($order['bill_phone']) ?>
                        <?php endif; ?>
                    </address>
                </section>

                <?php if (!empty($order['del_street'])) : ?>
                    <section class="account-section order-address-block">
                        <h2 class="account-section__title"><?= __('account.order_delivery') ?></h2>
                        <address class="order-address">
                            <?= htmlspecialchars($order['del_civility'] . ' ' . $order['del_firstname'] . ' ' . $order['del_lastname']) ?><br>
                            <?= htmlspecialchars($order['del_street'] ?? '') ?><br>
                            <?= htmlspecialchars(($order['del_zip'] ?? '') . ' ' . ($order['del_city'] ?? '')) ?><br>
                            <?= htmlspecialchars($order['del_country'] ?? '') ?>
                        </address>
                    </section>
                <?php endif; ?>
            </div>

            <!-- Annulation (uniquement si en attente de paiement) -->
            <?php if ($cancellable) : ?>
                <section class="account-section account-section--danger">
                    <h2 class="account-section__title"><?= __('account.order_cancel_btn') ?></h2>
                    <p><?= __('account.order_cancel_confirm') ?></p>
                    <form method="POST"
                          action="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>/annuler"
                          data-confirm="<?= htmlspecialchars(__('account.order_cancel_confirm')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn--danger">
                            <?= __('account.order_cancel_btn') ?>
                        </button>
                    </form>
                </section>
            <?php elseif (!in_array($order['status'], ['delivered', 'cancelled', 'refunded'], true)) : ?>
                <section class="account-section">
                    <p class="order-contact-notice">
                        <?= __('account.order_contact_for_cancel') ?>
                        <?php
                        $mailtoSubject = rawurlencode(__('account.order_contact_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHref    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . '?subject=' . $mailtoSubject;
                        ?>
                        <a href="<?= $mailtoHref ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                </section>
            <?php endif; ?>

            <!-- Retour -->
            <?php if ($order['status'] === 'delivered') : ?>
                <section class="account-section">
                    <p class="order-contact-notice">
                        <?= __('account.order_return_notice') ?>
                        <?php
                        $mailtoSubjectReturn = rawurlencode(__('account.order_return_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHrefReturn    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . '?subject=' . $mailtoSubjectReturn;
                        ?>
                        <a href="<?= $mailtoHrefReturn ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                </section>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
