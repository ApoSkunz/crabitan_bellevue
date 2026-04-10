<?php

declare(strict_types=1);

/**
 * Stepper de progression du tunnel de commande.
 *
 * @var int    $checkoutStep Étape active (1 = Panier, 2 = Livraison, 3 = Confirmation)
 * @var string $lang         Code de langue courant ('fr' ou 'en')
 */

$checkoutStep = isset($checkoutStep) ? (int) $checkoutStep : 1;
$lang         = isset($lang) ? (string) $lang : 'fr';

$steps = [
    1 => __('stepper.cart'),
    2 => __('stepper.delivery'),
    3 => __('stepper.confirmation'),
];
?>
<nav class="checkout-stepper" aria-label="<?= htmlspecialchars(__('stepper.aria_label') ?: 'Étapes de la commande') ?>">
    <ol class="checkout-stepper__list">
        <?php foreach ($steps as $stepNum => $stepLabel) :
            $isDone   = $stepNum < $checkoutStep || ($stepNum === $checkoutStep && $checkoutStep === count($steps));
            $isActive = $stepNum === $checkoutStep && !$isDone;
            if ($isDone) {
                $modifier    = 'checkout-stepper__step--done';
                $ariaCurrent = '';
            } elseif ($isActive) {
                $modifier    = 'checkout-stepper__step--active';
                $ariaCurrent = ' aria-current="step"';
            } else {
                $modifier    = 'checkout-stepper__step--pending';
                $ariaCurrent = '';
            }
        ?>
        <li class="checkout-stepper__step <?= $modifier ?>"<?= $ariaCurrent ?>>
            <span class="checkout-stepper__icon" aria-hidden="true">
                <?= $isDone ? '✓' : $stepNum ?>
            </span>
            <span class="checkout-stepper__label"><?= htmlspecialchars($stepLabel) ?></span>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
