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
    'return_requested' => 'teal',
    'refund_refused'   => 'red',
];
$isReturnFlow  = in_array($order['status'], ['return_requested', 'refunded', 'refund_refused'], true);
$returnEndStep = $order['status'] === 'refund_refused' ? 'refund_refused' : 'refunded';
$timeline      = $isReturnFlow
    ? ['pending', 'paid', 'processing', 'shipped', 'delivered', 'return_requested', $returnEndStep]
    : ['pending', 'paid', 'processing', 'shipped', 'delivered'];
$currentIdx  = array_search($order['status'], $timeline, true);
$cancellable = $order['status'] === 'pending';
/** @var bool $cancellableReturn */
/** @var string|null $returnDeadline */
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

                        <?php if (!empty($order['delivered_at'])) : ?>
                            <dt><?= __('account.order_delivered_at') ?></dt>
                            <dd><?= htmlspecialchars(date('d/m/Y', strtotime($order['delivered_at']))) ?></dd>
                        <?php endif; ?>
                    </dl>

                </div>
            </section>

            <!-- Timeline statut (commandes non annulées) -->
            <?php if ($order['status'] !== 'cancelled') : ?>
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
            <?php $qs = '?subject='; ?>
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
            <?php elseif (!in_array($order['status'], ['delivered', 'cancelled', 'refunded', 'return_requested', 'refund_refused'], true)) : ?>
                <section class="account-section">
                    <p class="order-contact-notice">
                        <?= __('account.order_contact_for_cancel') ?>
                        <?php
                        $mailtoSubject = rawurlencode(__('account.order_contact_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHref    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . $qs . $mailtoSubject;
                        ?>
                        <a href="<?= $mailtoHref ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                    <p class="order-contact-notice" style="margin-top:0.5rem;">
                        <?= __('account.order_return_after_delivery') ?>
                    </p>
                </section>
            <?php endif; ?>

            <!-- Rétractation légale (15 j après livraison) -->
            <?php if ($cancellableReturn) : ?>
                <section class="account-section account-section--danger">
                    <h2 class="account-section__title"><?= __('account.order_return_request_btn') ?></h2>
                    <p><?= sprintf(__('account.order_return_window'), htmlspecialchars($returnDeadline ?? '')) ?></p>
                    <p style="margin-bottom:1rem;">
                        <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>/fiche-retour"
                           class="btn btn--ghost btn--sm" target="_blank" rel="noopener noreferrer">
                            <?= __('account.order_return_slip_btn') ?>
                        </a>
                    </p>
                    <form method="POST"
                          action="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>/annuler"
                          data-confirm="<?= htmlspecialchars(__('account.order_return_confirm')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn--danger">
                            <?= __('account.order_return_request_btn') ?>
                        </button>
                    </form>
                </section>
            <?php elseif ($returnExpired ?? false) : ?>
                <section class="account-section">
                    <p class="order-contact-notice">
                        <?= __('account.order_return_expired') ?>
                        <?php
                        $mailtoSubjectExpired = rawurlencode(__('account.order_return_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHrefExpired    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . $qs . $mailtoSubjectExpired;
                        ?>
                        <a href="<?= $mailtoHrefExpired ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                </section>
            <?php elseif ($deliveredNoDate ?? false) : ?>
                <section class="account-section">
                    <p class="order-contact-notice">
                        <?= __('account.order_return_no_date') ?>
                        <?php
                        $mailtoSubjectNoDate = rawurlencode(__('account.order_return_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHrefNoDate    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . $qs . $mailtoSubjectNoDate;
                        ?>
                        <a href="<?= $mailtoHrefNoDate ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                </section>
            <?php elseif ($order['status'] === 'return_requested') : ?>
                <section class="account-section">
                    <h2 class="account-section__title"><?= __('account.order_return_in_progress_title') ?></h2>
                    <p class="order-contact-notice">
                        <?= __('account.order_return_in_progress') ?>
                        <?php
                        $mailtoSubjectReturn = rawurlencode(__('account.order_return_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHrefReturn    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . $qs . $mailtoSubjectReturn;
                        ?>
                        <a href="<?= $mailtoHrefReturn ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                    <p style="margin-top:0.75rem;">
                        <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>/fiche-retour"
                           class="btn btn--ghost btn--sm" target="_blank" rel="noopener noreferrer">
                            <?= __('account.order_return_slip_btn') ?>
                        </a>
                    </p>
                </section>
            <?php elseif ($order['status'] === 'refund_refused') : ?>
                <section class="account-section">
                    <p class="order-contact-notice">
                        <?= __('account.order_refund_refused_notice') ?>
                        <?php
                        $mailtoSubjectRefused = rawurlencode(__('account.order_return_subject') . ' ' . htmlspecialchars($order['order_reference']));
                        $mailtoHrefRefused    = 'mailto:' . htmlspecialchars($ownerEmail ?? '') . $qs . $mailtoSubjectRefused;
                        ?>
                        <a href="<?= $mailtoHrefRefused ?>"><?= __('account.order_contact_link') ?></a>
                    </p>
                </section>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
