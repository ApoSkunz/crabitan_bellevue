<?php
$pageTitle     = __('account.orders_title');
$activeSection = 'orders';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $orders */
/** @var int    $page         */
/** @var int    $pages        */
/** @var int    $total        */
/** @var int    $perPage      */
/** @var string $period       */
/** @var string $statusFilter */
/** @var array<int, int> $years */
$selected = static fn(bool $c): string => $c ? ' selected' : '';

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

// Reconstruit l'URL de pagination en conservant les filtres actifs
if (!function_exists('ordersUrl')) {
    function ordersUrl(int $p, int $perPage, string $period, string $statusFilter): string
    {
        $q = http_build_query(array_filter([
            'page'     => $p,
            'per_page' => $perPage !== 10 ? $perPage : null,
            'period'   => $period !== 'all' ? $period : null,
            'status'   => $statusFilter !== '' ? $statusFilter : null,
        ]));
        return '?' . $q;
    }
}
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('panel.orders') ?></h1>
            </header>

            <!-- Filtres -->
            <form class="account-filters" method="GET" action="">
                <div class="account-filters__group">
                    <label for="filter-period"><?= __('account.filter_period') ?></label>
                    <select id="filter-period" name="period" onchange="this.form.submit()">
                        <option value="all"<?= $selected($period === 'all') ?>>
                            <?= __('account.filter_all') ?>
                        </option>
                        <option value="3months"<?= $selected($period === '3months') ?>>
                            <?= __('account.filter_3months') ?>
                        </option>
                        <?php foreach ($years as $yr) : ?>
                            <option value="<?= (int) $yr ?>"<?= $selected($period === (string) $yr) ?>>
                                <?= (int) $yr ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="account-filters__group">
                    <label for="filter-status"><?= __('account.filter_status') ?></label>
                    <select id="filter-status" name="status" onchange="this.form.submit()">
                        <option value=""<?= $selected($statusFilter === '') ?>>
                            <?= __('account.filter_status_all') ?>
                        </option>
                        <?php foreach (['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $s) : ?>
                            <option value="<?= $s ?>"<?= $selected($statusFilter === $s) ?>>
                                <?= __('order.status.' . $s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="account-filters__group">
                    <label for="filter-per-page"><?= __('account.per_page') ?></label>
                    <select id="filter-per-page" name="per_page" onchange="this.form.submit()">
                        <?php foreach ([10, 25, 50] as $n) : ?>
                            <option value="<?= $n ?>"<?= $selected($perPage === $n) ?>>
                                <?= $n ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="page" value="1">
            </form>

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
                                <th></th>
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
                                               class="btn btn--ghost btn--sm"
                                               target="_blank" rel="noopener noreferrer">
                                                <?= __('account.download_invoice') ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="account-table__na">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/commandes/<?= (int) $order['id'] ?>"
                                           class="btn btn--ghost btn--sm">
                                            <?= __('account.order_detail_link') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1) : ?>
                    <nav class="account-pagination" aria-label="<?= __('account.pagination') ?>">
                        <?php if ($page > 1) : ?>
                            <a class="account-pagination__link"
                               href="<?= ordersUrl($page - 1, $perPage, $period, $statusFilter) ?>">←</a>
                        <?php endif; ?>
                        <span class="account-pagination__info"><?= $page ?> / <?= $pages ?></span>
                        <?php if ($page < $pages) : ?>
                            <a class="account-pagination__link"
                               href="<?= ordersUrl($page + 1, $perPage, $period, $statusFilter) ?>">→</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
