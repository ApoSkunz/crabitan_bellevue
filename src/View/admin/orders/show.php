<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$statusLabels = [
    'pending'    => 'En attente',
    'paid'       => 'Payée',
    'processing' => 'En préparation',
    'shipped'    => 'Expédiée',
    'delivered'  => 'Livrée',
    'cancelled'  => 'Annulée',
    'refunded'   => 'Remboursée',
];
$allStatuses = array_keys($statusLabels);

// Contenu du panier (snapshot JSON)
$cartItems = json_decode($order['content'] ?? '[]', true) ?? [];
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Commande <code style="font-size:1.1rem;font-variant:normal;"><?= htmlspecialchars($order['order_reference']) ?></code></h1>
    <a href="/admin/commandes" class="admin-btn admin-btn--outline">← Retour</a>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start;">

    <!-- Gauche : infos commande + articles -->
    <div>

        <!-- Articles -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card__header"><h2>Articles commandés</h2></div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Vin</th>
                            <th>Format</th>
                            <th>Qté</th>
                            <th>Prix unit.</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cartItems)) : ?>
                        <tr><td colspan="5" style="text-align:center;color:#8a7a60;padding:1.5rem;">Données indisponibles</td></tr>
                    <?php else : ?>
                        <?php foreach ($cartItems as $item) : ?>
                            <tr>
                                <td><?= htmlspecialchars($item['label_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($item['format'] ?? 'bottle') ?></td>
                                <td><?= (int) ($item['qty'] ?? 0) ?></td>
                                <td><?= number_format((float) ($item['price'] ?? 0), 2, ',', ' ') ?>&nbsp;€</td>
                                <td><?= number_format((float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0), 2, ',', ' ') ?>&nbsp;€</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:1rem 1.5rem;text-align:right;border-top:1px solid rgba(8,8,8,0.06);">
                <strong style="font-family:var(--font-sans);font-size:0.9rem;">
                    Total : <?= number_format((float) $order['price'], 2, ',', ' ') ?>&nbsp;€
                </strong>
            </div>
        </div>

        <!-- Adresses -->
        <div class="admin-card">
            <div class="admin-card__header"><h2>Adresses</h2></div>
            <div class="admin-card__body" style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div>
                    <p style="font-family:var(--font-sans);font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#8a7a60;margin-bottom:0.5rem;">Facturation</p>
                    <p style="font-size:0.875rem;line-height:1.7;color:#1a1208;">
                        <?= htmlspecialchars($order['bill_firstname'] . ' ' . $order['bill_lastname']) ?><br>
                        <?= htmlspecialchars($order['bill_street'] ?? '') ?><br>
                        <?= htmlspecialchars($order['bill_zip'] ?? '') ?> <?= htmlspecialchars($order['bill_city'] ?? '') ?><br>
                        <?= htmlspecialchars($order['bill_country'] ?? '') ?>
                        <?php if ($order['bill_phone'] ?? '') : ?><br><?= htmlspecialchars($order['bill_phone']) ?><?php endif; ?>
                    </p>
                </div>
                <?php if ($order['del_street'] ?? null) : ?>
                <div>
                    <p style="font-family:var(--font-sans);font-size:0.68rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#8a7a60;margin-bottom:0.5rem;">Livraison</p>
                    <p style="font-size:0.875rem;line-height:1.7;color:#1a1208;">
                        <?= htmlspecialchars($order['del_firstname'] . ' ' . $order['del_lastname']) ?><br>
                        <?= htmlspecialchars($order['del_street']) ?><br>
                        <?= htmlspecialchars($order['del_zip'] ?? '') ?> <?= htmlspecialchars($order['del_city'] ?? '') ?><br>
                        <?= htmlspecialchars($order['del_country'] ?? '') ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Droite : récap + changement de statut -->
    <div>

        <!-- Récap -->
        <div class="admin-card" style="margin-bottom:1.5rem;">
            <div class="admin-card__header"><h2>Résumé</h2></div>
            <div class="admin-card__body">
                <dl class="admin-dl">
                    <dt>Statut</dt>
                    <dd><span class="badge badge--<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($statusLabels[$order['status']] ?? $order['status']) ?></span></dd>
                    <dt>Client</dt>
                    <dd>
                        <?= htmlspecialchars(trim($order['firstname'] . ' ' . $order['lastname'])) ?><br>
                        <span style="font-size:0.8rem;color:#8a7a60;"><?= htmlspecialchars($order['email']) ?></span>
                    </dd>
                    <dt>Paiement</dt>
                    <dd><?= htmlspecialchars($order['payment_method']) ?></dd>
                    <dt>Montant</dt>
                    <dd><strong><?= number_format((float) $order['price'], 2, ',', ' ') ?>&nbsp;€</strong></dd>
                    <dt>Date</dt>
                    <dd><?= date('d/m/Y à H:i', strtotime($order['ordered_at'])) ?></dd>
                    <?php if ($order['delivery_tracking'] ?? '') : ?>
                    <dt>Suivi</dt>
                    <dd><code style="font-size:0.8rem;"><?= htmlspecialchars($order['delivery_tracking']) ?></code></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Changement de statut -->
        <div class="admin-card">
            <div class="admin-card__header"><h2>Changer le statut</h2></div>
            <div class="admin-card__body">
                <form method="POST" action="/admin/commandes/<?= (int) $order['id'] ?>/statut">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="admin-field" style="margin-bottom:1rem;">
                        <label class="admin-field__label" for="status">Nouveau statut</label>
                        <select id="status" name="status" class="admin-field__select">
                            <?php foreach ($allStatuses as $s) : ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($statusLabels[$s] ?? $s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;">
                        Mettre à jour
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
