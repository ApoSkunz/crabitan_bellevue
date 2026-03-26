<?php
$pageTitle     = __('account.orders_title');
$activeSection = 'orders';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $orders */
$statusColors = [
    'pending'    => 'grey',
    'paid'       => 'blue',
    'processing' => 'orange',
    'shipped'    => 'purple',
    'delivered'  => 'green',
    'cancelled'  => 'red',
    'refunded'   => 'red',
];
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('panel.orders') ?></h1>
            </header>

            <?php if ($orders === []) : ?>
                <p class="account-empty"><?= __('account.orders_empty') ?></p>
            <?php else : ?>
                <div class="account-table-wrap">
                    <table class="account-table">
                        <thead>
                            <tr>
                                <th><?= __('account.order_ref') ?></th>
                                <th><?= __('account.order_date') ?></th>
                                <th><?= __('account.order_status') ?></th>
                                <th><?= __('account.order_price') ?></th>
                                <th><?= __('account.order_invoice') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order) : ?>
                                <tr>
                                    <td class="account-table__ref">
                                        <?= htmlspecialchars($order['order_reference']) ?>
                                    </td>
                                    <td><?= htmlspecialchars(
                                        date('d/m/Y', strtotime($order['ordered_at']))
                                    ) ?></td>
                                    <td>
                                        <span class="account-badge account-badge--<?= $statusColors[$order['status']] ?? 'grey' ?>">
                                            <?= __('order.status.' . $order['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format((float) $order['price'], 2, ',', ' ') ?> €</td>
                                    <td>
                                        <?php if ($order['path_invoice']) : ?>
                                            <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>/facture"
                                               class="btn btn--ghost btn--sm">
                                                <?= __('account.download_invoice') ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="account-table__na">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1) : ?>
                    <nav class="account-pagination" aria-label="<?= __('account.pagination') ?>">
                        <?php if ($page > 1) : ?>
                            <a class="account-pagination__link" href="?page=<?= $page - 1 ?>">←</a>
                        <?php endif; ?>
                        <span class="account-pagination__info"><?= $page ?> / <?= $pages ?></span>
                        <?php if ($page < $pages) : ?>
                            <a class="account-pagination__link" href="?page=<?= $page + 1 ?>">→</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
