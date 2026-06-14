<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$pendingCount = 0;
$paidCount    = 0;
foreach (['pending', 'paid', 'processing'] as $s) {
    $paidCount    += $ordersByStatus[$s] ?? 0;
}
$pendingCount = $ordersByStatus['pending'] ?? 0;
$totalOrders  = array_sum($ordersByStatus)
    - ($ordersByStatus['cancelled'] ?? 0)
    - ($ordersByStatus['refunded']  ?? 0);
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="admin-stats">
    <div class="admin-stat-card admin-stat-card--gold">
        <div class="admin-stat-card__label">Vins au catalogue</div>
        <div class="admin-stat-card__value"><?= $winesTotal ?></div>
        <div class="admin-stat-card__sub"><?= $winesAvail ?> disponibles</div>
    </div>
    <div class="admin-stat-card admin-stat-card--amber">
        <div class="admin-stat-card__label">Commandes en attente</div>
        <div class="admin-stat-card__value"><?= $pendingCount ?></div>
        <div class="admin-stat-card__sub"><?= $totalOrders ?> au total</div>
    </div>
    <div class="admin-stat-card admin-stat-card--green">
        <div class="admin-stat-card__label">CA <?= date('Y') ?></div>
        <div class="admin-stat-card__value"><?= number_format($revenueYear, 0, ',', ' ') ?>&nbsp;€</div>
        <div class="admin-stat-card__sub u-flex u-flex-col u-gap-1">
            <span><?= date('Y') - 1 ?> : <?= number_format($revenueLastYear, 0, ',', ' ') ?>&nbsp;€</span>
            <span>30 j : <?= number_format($revenue30, 0, ',', ' ') ?>&nbsp;€</span>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-card--blue">
        <div class="admin-stat-card__label">Comptes clients</div>
        <div class="admin-stat-card__value"><?= $accountsTotal ?></div>
        <div class="admin-stat-card__sub">Actifs (non supprimés)</div>
    </div>
</div>

<!-- Statuts des commandes -->
<?php
$statusLabels = [
    'pending'          => 'En attente',
    'paid'             => 'Payée',
    'processing'       => 'En préparation',
    'shipped'          => 'Expédiée',
    'delivered'        => 'Livrée',
    'cancelled'        => 'Annulée',
    'refunded'         => 'Remboursée',
    'return_requested' => 'Retour en cours',
    'refund_pending'   => 'Remboursement en attente',
    'refund_refused'   => 'Remboursement refusé',
];
?>
<?php if (!empty($ordersByStatus)) : ?>
<div class="admin-card u-mb-6">
    <div class="admin-card__header"><h2>Répartition par statut</h2></div>
    <div class="admin-card__body u-flex u-flex-wrap u-gap-3">
        <?php
        foreach ($statusLabels as $key => $label) :
            $cnt = $ordersByStatus[$key] ?? 0;
            ?>
            <a href="/admin/commandes?status=<?= urlencode($key) ?>" class="u-no-underline">
                <span class="badge badge--<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?> (<?= $cnt ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Commandes récentes -->
<div class="admin-card">
    <div class="admin-card__header">
        <h2>Dernières commandes</h2>
        <a href="/admin/commandes" class="admin-btn admin-btn--outline admin-btn--sm">Voir tout</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Statut</th>
                    <th>Montant</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentOrders)) : ?>
                <tr><td colspan="6" class="text-center u-text-soft u-p-8">Aucune commande</td></tr>
            <?php else : ?>
                <?php foreach ($recentOrders as $order) : ?>
                    <tr>
                        <td><code class="u-text-sm"><?= htmlspecialchars($order['order_reference']) ?></code></td>
                        <td><?= htmlspecialchars($order['firstname'] ?: $order['email']) ?></td>
                        <td><span class="badge badge--<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
                        <td><?= number_format((float) $order['price'], 2, ',', ' ') ?>&nbsp;€</td>
                        <td class="u-nowrap"><?= date('d/m/Y', strtotime($order['ordered_at'])) ?></td>
                        <td><a href="/admin/commandes/<?= (int) $order['id'] ?>" class="admin-btn admin-btn--outline admin-btn--sm">Voir</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
