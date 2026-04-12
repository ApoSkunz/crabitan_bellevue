<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
$statusLabels = [
    'pending'          => 'En attente',
    'paid'             => 'Payée',
    'processing'       => 'En préparation',
    'shipped'          => 'Expédiée',
    'delivered'        => 'Livrée',
    'cancelled'        => 'Annulée',
    'refunded'         => 'Remboursée',
    'return_requested' => 'Retour en cours',
    'refund_refused'   => 'Remboursement refusé',
    'refund_pending'   => 'Remboursement en attente',
];
$paymentLabels = [
    'card'     => 'Carte bancaire',
    'virement' => 'Virement',
    'cheque'   => 'Chèque',
];
if (!function_exists('buildPaginationUrl')) {
function buildPaginationUrl(
    int $p,
    ?string $status,
    string $search,
    ?string $payment,
    int $perPage,
    bool $archived = false
): string {
    $q = ['page' => $p];
    if ($status) {
        $q['status'] = $status;
    }
    if ($payment) {
        $q['payment'] = $payment;
    }
    if ($search !== '') {
        $q['search'] = $search;
    }
    if ($perPage !== 10) {
        $q['per_page'] = $perPage;
    }
    if ($archived) {
        $q['archived'] = '1';
    }
    return '/admin/commandes?' . http_build_query($q);
}
}
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="admin-page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
    <h1>
        <?= $archived ? 'Commandes archivées' : 'Commandes' ?>
        <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= $total ?>)</small>
    </h1>
    <?php if ($archived) : ?>
        <a href="/admin/commandes" class="admin-btn admin-btn--outline admin-btn--sm">← Commandes actives</a>
    <?php else : ?>
        <a href="/admin/commandes?archived=1" class="admin-btn admin-btn--outline admin-btn--sm">Archivées (&gt;10 ans)</a>
    <?php endif; ?>
</div>

<?php if ($archived) : ?>
    <div style="background:#fff8e1;border:1px solid #f59e0b;border-radius:6px;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.87rem;color:#92400e;">
        &#9432; Ces commandes datent de plus de 10 ans (Art. L123-22 C.com.). Les données personnelles ont été pseudonymisées conformément au RGPD Art. 5.
    </div>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" action="/admin/commandes" class="admin-filters">
    <select name="status" class="admin-filters__select" aria-label="Filtrer par statut">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= $status === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="payment" class="admin-filters__select" aria-label="Filtrer par paiement">
        <option value="">Tous les paiements</option>
        <?php foreach ($paymentLabels as $val => $label) : ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= ($payment ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="search" class="admin-filters__input" placeholder="Email ou référence…"
           value="<?= htmlspecialchars($search) ?>">
    <select name="per_page" class="admin-filters__select" aria-label="Lignes par page">
        <?php foreach ([10, 25, 50] as $n) : ?>
            <option value="<?= $n ?>" <?= (int) $perPage === $n ? 'selected' : '' ?>><?= $n ?> / page</option>
        <?php endforeach; ?>
    </select>
    <?php if ($archived) : ?>
        <input type="hidden" name="archived" value="1">
    <?php endif; ?>
    <button type="submit" class="admin-filters__btn">Filtrer</button>
    <?php if ($status || ($payment ?? null) || $search || $perPage !== 10) : ?>
        <a href="/admin/commandes<?= $archived ? '?archived=1' : '' ?>"
           class="admin-btn admin-btn--outline admin-btn--sm">Réinitialiser</a>
    <?php endif; ?>
</form>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Statut</th>
                    <th class="col-hide-tablet">Paiement</th>
                    <th>Montant</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)) : ?>
                <tr><td colspan="7" style="text-align:center;color:#8a7a60;padding:2rem;">Aucune commande trouvée</td></tr>
            <?php else : ?>
                <?php foreach ($orders as $order) : ?>
                    <tr>
                        <td data-label="Référence"><code style="font-size:0.8rem;"><?= htmlspecialchars($order['order_reference']) ?></code></td>
                        <td data-label="Client">
                            <?php if (str_ends_with((string) ($order['email'] ?? ''), '@purged.invalid')) : ?>
                                <em style="color:#8a7a60;font-size:0.85rem;">Compte anonymisé</em>
                            <?php else : ?>
                                <div><?= htmlspecialchars(trim($order['firstname'] . ' ' . $order['lastname'])) ?></div>
                                <div style="font-size:0.75rem;color:#8a7a60;"><?= htmlspecialchars($order['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Statut"><span class="badge badge--<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span></td>
                        <td class="col-hide-tablet" data-label="Paiement" style="font-size:0.8rem;"><?= htmlspecialchars($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></td>
                        <td data-label="Montant"><?= number_format((float) $order['price'], 2, ',', ' ') ?>&nbsp;€</td>
                        <td data-label="Date" style="white-space:nowrap;font-size:0.82rem;"><?= date('d/m/Y H:i', strtotime($order['ordered_at'])) ?></td>
                        <td><a href="/admin/commandes/<?= (int) $order['id'] ?>" class="admin-btn admin-btn--outline admin-btn--sm">Voir</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-pagination">
            <a href="<?= htmlspecialchars(buildPaginationUrl(max(1, $page - 1), $status, $search, $payment ?? null, $perPage, $archived)) ?>"
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="<?= htmlspecialchars(buildPaginationUrl($i, $status, $search, $payment ?? null, $perPage, $archived)) ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= htmlspecialchars(buildPaginationUrl(min($totalPages, $page + 1), $status, $search, $payment ?? null, $perPage, $archived)) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
