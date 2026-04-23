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
        <?= $langKey === 'fr' ? 'Remises livraison' : 'Delivery discounts' ?>
    </summary>
    <div class="delivery-flyer__content">
        <?php if (empty($pricingRules)) : ?>
            <p class="delivery-flyer__empty">—</p>
        <?php else : ?>
            <ul class="delivery-flyer__list">
                <?php foreach ($pricingRules as $rule) :
                    if (!(bool) $rule['active']) {
                        continue;
                    }
                    $label     = is_string($rule['label']) ? json_decode($rule['label'], true) : $rule['label'];
                    $labelText = $label[$langKey] ?? $label['fr'] ?? '';
                ?>
                <li class="delivery-flyer__item"><?= htmlspecialchars($labelText) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</details>
