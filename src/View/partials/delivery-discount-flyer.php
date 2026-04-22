<?php

/**
 * Dépliant des remises livraison — partial réutilisable.
 * Attend $pricingRules : array<int, array<string, mixed>> (résultat PricingModel::getAll())
 * Attend $navLang : string
 */

$pricingRules = $pricingRules ?? [];
$langKey      = ($navLang ?? 'fr') === 'en' ? 'en' : 'fr';
?>
<details class="delivery-flyer" id="delivery-flyer">
    <summary class="delivery-flyer__toggle">
        <?= $langKey === 'fr' ? '🚚 Remises livraison' : '🚚 Delivery discounts' ?>
    </summary>
    <div class="delivery-flyer__content">
        <?php if (empty($pricingRules)) : ?>
            <p class="delivery-flyer__empty">—</p>
        <?php else : ?>
            <table class="delivery-flyer__table">
                <thead>
                    <tr>
                        <th><?= $langKey === 'fr' ? 'Quantité' : 'Quantity' ?></th>
                        <th><?= $langKey === 'fr' ? 'Livraison' : 'Delivery' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricingRules as $rule) :
                        if (!(bool) $rule['active']) {
                            continue;
                        }
                        $label     = is_string($rule['label']) ? json_decode($rule['label'], true) : $rule['label'];
                        $labelText = $label[$langKey] ?? $label['fr'] ?? '';
                        $price     = (float) $rule['delivery_price'];
                        $priceLabel = $price === 0.0
                            ? ($langKey === 'fr' ? 'Gratuit' : 'Free')
                            : number_format($price, 2, ',', ' ') . ' €';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($labelText) ?></td>
                        <td><?= htmlspecialchars($priceLabel) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</details>
