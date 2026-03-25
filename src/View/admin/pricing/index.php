<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$formatLabels = ['bottle' => 'Bouteille', 'bib' => 'Bag-in-Box'];

if (!function_exists('pricingLabel')) :
function pricingLabel(mixed $raw, string $lang): string
{
    if (!is_string($raw)) {
        return '';
    }
    $data = json_decode($raw, true) ?? [];
    return $data[$lang] ?? $data['fr'] ?? '';
}
endif;
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Tarifs de livraison &amp; retrait</h1>
</div>

<!-- ================================================================
     Tableau de référence lisible
================================================================ -->
<?php if (!empty($rules)) : ?>
<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__header"><h2>Récapitulatif des tarifs actuels</h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Format</th>
                    <th>Tranche</th>
                    <th>Label (FR)</th>
                    <th>Livraison (€)</th>
                    <th>Retrait cave (€)</th>
                    <th>Actif</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule) : ?>
                <tr style="<?= $rule['active'] ? '' : 'opacity:0.45;' ?>">
                    <td><?= htmlspecialchars($formatLabels[$rule['format']] ?? $rule['format']) ?></td>
                    <td style="white-space:nowrap;">
                        <?= (int) $rule['min_quantity'] ?>
                        –
                        <?= $rule['max_quantity'] !== null ? (int) $rule['max_quantity'] : '∞' ?>
                    </td>
                    <td><?= htmlspecialchars(pricingLabel($rule['label'], 'fr')) ?></td>
                    <td><?= number_format((float) $rule['delivery_price'], 2, ',', ' ') ?>&nbsp;€</td>
                    <td><?= number_format((float) $rule['withdrawal_price'], 2, ',', ' ') ?>&nbsp;€</td>
                    <td style="text-align:center;">
                        <?php if ($rule['active']) : ?>
                            <span style="color:#15803d;font-size:0.85rem;">✓</span>
                        <?php else : ?>
                            <span style="color:#b91c1c;font-size:0.85rem;">✗</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ================================================================
     Formulaire inline de mise à jour
================================================================ -->
<div class="admin-card admin-pricing-form">
    <form method="POST" action="/admin/tarifs">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Format</th>
                        <th>Qté min</th>
                        <th>Qté max</th>
                        <th>Livraison (€)</th>
                        <th>Retrait cave (€)</th>
                        <th>Label FR</th>
                        <th>Label EN</th>
                        <th>Actif</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rules)) : ?>
                    <tr>
                        <td colspan="8"
                            style="text-align:center;color:#8a7a60;padding:2rem;">
                            Aucune règle de tarification — lancez le seed pricing pour initialiser.
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rules as $rule) : ?>
                        <input type="hidden" name="id[]" value="<?= (int) $rule['id'] ?>">
                        <tr>
                            <td><?= htmlspecialchars($formatLabels[$rule['format']] ?? $rule['format']) ?></td>
                            <td>
                                <input type="number"
                                       name="min_qty_<?= (int) $rule['id'] ?>"
                                       min="0" step="1"
                                       value="<?= (int) $rule['min_quantity'] ?>">
                            </td>
                            <td>
                                <?php if ($rule['max_quantity'] !== null) : ?>
                                    <input type="number"
                                           name="max_qty_<?= (int) $rule['id'] ?>"
                                           min="1" step="1"
                                           value="<?= (int) $rule['max_quantity'] ?>">
                                <?php else : ?>
                                    <span title="Illimité" style="color:#8a7a60;">∞</span>
                                    <input type="hidden" name="max_qty_<?= (int) $rule['id'] ?>" value="">
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number"
                                       name="delivery_<?= (int) $rule['id'] ?>"
                                       min="0" step="0.01"
                                       value="<?= htmlspecialchars(number_format((float) $rule['delivery_price'], 2, '.', '')) ?>">
                            </td>
                            <td>
                                <input type="number"
                                       name="withdrawal_<?= (int) $rule['id'] ?>"
                                       min="0" step="0.01"
                                       value="<?= htmlspecialchars(number_format((float) $rule['withdrawal_price'], 2, '.', '')) ?>">
                            </td>
                            <td>
                                <input type="text"
                                       name="label_fr_<?= (int) $rule['id'] ?>"
                                       value="<?= htmlspecialchars(pricingLabel($rule['label'], 'fr')) ?>">
                            </td>
                            <td>
                                <input type="text"
                                       name="label_en_<?= (int) $rule['id'] ?>"
                                       value="<?= htmlspecialchars(pricingLabel($rule['label'], 'en')) ?>">
                            </td>
                            <td style="text-align:center;">
                                <input type="hidden"
                                       name="active_<?= (int) $rule['id'] ?>" value="0">
                                <input type="checkbox"
                                       name="active_<?= (int) $rule['id'] ?>" value="1"
                                       <?= $rule['active'] ? 'checked' : '' ?>
                                       style="accent-color:#c9a84c;width:1rem;height:1rem;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($rules)) : ?>
        <div style="padding:1.25rem 1.5rem;border-top:1px solid rgba(8,8,8,0.07);
                    display:flex;justify-content:flex-end;">
            <button type="submit" class="admin-btn admin-btn--primary">
                Enregistrer les tarifs
            </button>
        </div>
        <?php endif; ?>

    </form>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
