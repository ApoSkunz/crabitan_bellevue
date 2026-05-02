<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$formatLabels = ['bottle' => 'Bouteille', 'bib' => 'Bag-in-Box'];

if (!function_exists('pricingLabel')) {
function pricingLabel(mixed $raw, string $lang): string
{
    if (!is_string($raw)) {
        return '';
    }
    $data = json_decode($raw, true) ?? [];
    return $data[$lang] ?? $data['fr'] ?? '';
}
}
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Tarifs de livraison</h1>
</div>

<!-- ================================================================
     Remise Click & Collect — singleton BDD
================================================================ -->
<div class="admin-card u-mb-6">
    <div class="admin-card__header"><h2>Remise Click &amp; Collect</h2></div>
    <div class="admin-card__body">
        <form method="POST" action="/admin/tarifs/click-collect" class="u-flex u-gap-3 u-items-end u-flex-wrap">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div>
                <label for="cc-discount" class="admin-pricing-cc-label">
                    Remise par bouteille (€)
                </label>
                <input type="number" id="cc-discount" name="cc_discount"
                       min="0" step="0.01" required
                       value="<?= htmlspecialchars(number_format((float) ($ccDiscount ?? 2.20), 2, '.', '')) ?>"
                       class="admin-pricing-cc-input">
            </div>
            <button type="submit" class="admin-btn admin-btn--primary">Enregistrer</button>
            <p class="admin-pricing-cc-note">
                Appliquée sur chaque bouteille des commandes <em>Retrait au château</em>.
            </p>
        </form>
    </div>
</div>

<!-- ================================================================
     Tableau de référence lisible
================================================================ -->
<?php if (!empty($rules)) : ?>
<div class="admin-card u-mb-6">
    <div class="admin-card__header"><h2>Récapitulatif des tarifs actuels</h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Format</th>
                    <th>Tranche</th>
                    <th>Label (FR)</th>
                    <th>Actif</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rules as $rule) : ?>
                <tr class="<?= $rule['active'] ? '' : 'u-opacity-55' ?>">
                    <td><?= htmlspecialchars($formatLabels[$rule['format']] ?? $rule['format']) ?></td>
                    <td class="u-nowrap">
                        <?= (int) $rule['min_quantity'] ?>
                        -
                        <?= $rule['max_quantity'] !== null ? (int) $rule['max_quantity'] : '∞' ?>
                    </td>
                    <td><?= htmlspecialchars(pricingLabel($rule['label'], 'fr')) ?></td>
                    <td class="text-center">
                        <?php if ($rule['active']) : ?>
                            <span class="u-text-85 u-text-success">✓</span>
                        <?php else : ?>
                            <span class="u-text-85 u-text-error">✗</span>
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
                        <th>Label FR</th>
                        <th>Label EN</th>
                        <th>Actif</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rules)) : ?>
                    <tr>
                        <td colspan="7" class="admin-table-empty-cell">
                            Aucune règle de tarification — lancez le seed pricing pour initialiser.
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rules as $rule) : ?>
                        <input type="hidden" name="id[]" value="<?= (int) $rule['id'] ?>">
                        <input type="hidden" name="withdrawal_<?= (int) $rule['id'] ?>"
                               value="<?= htmlspecialchars(number_format((float) $rule['withdrawal_price'], 2, '.', '')) ?>">
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
                                    <span title="Illimité" class="text-muted">∞</span>
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
                                <input type="text"
                                       name="label_fr_<?= (int) $rule['id'] ?>"
                                       value="<?= htmlspecialchars(pricingLabel($rule['label'], 'fr')) ?>">
                            </td>
                            <td>
                                <input type="text"
                                       name="label_en_<?= (int) $rule['id'] ?>"
                                       value="<?= htmlspecialchars(pricingLabel($rule['label'], 'en')) ?>">
                            </td>
                            <td class="text-center">
                                <input type="hidden"
                                       name="active_<?= (int) $rule['id'] ?>" value="0">
                                <input type="checkbox"
                                       name="active_<?= (int) $rule['id'] ?>" value="1"
                                       <?= $rule['active'] ? 'checked' : '' ?>
                                       class="admin-pricing-checkbox">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($rules)) : ?>
        <div class="admin-form__actions u-justify-end">
            <button type="submit" class="admin-btn admin-btn--primary">
                Enregistrer les tarifs
            </button>
        </div>
        <?php endif; ?>

    </form>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
