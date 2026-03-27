<?php
/**
 * Champs communs du formulaire d'adresse.
 * Variables attendues : $address (array|null — null = création)
 */
$v = $address ?? [];
?>
<div class="form-group">
    <label for="addr-type"><?= __('account.address_type_label') ?> *</label>
    <select id="addr-type" name="type" required<?= isset($v['type']) ? ' disabled' : '' ?>>
        <option value="billing"<?= ($v['type'] ?? '') === 'billing' ? ' selected' : '' ?>>
            <?= __('account.address_type_billing') ?>
        </option>
        <option value="delivery"<?= ($v['type'] ?? '') === 'delivery' ? ' selected' : '' ?>>
            <?= __('account.address_type_delivery') ?>
        </option>
    </select>
    <?php if (isset($v['type'])) : ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($v['type']) ?>">
    <?php endif; ?>
</div>

<div class="form-group">
    <label for="addr-civility"><?= __('account.civility') ?></label>
    <select id="addr-civility" name="civility">
        <option value="M"<?= ($v['civility'] ?? '') === 'M' ? ' selected' : '' ?>><?= __('account.civility_m') ?></option>
        <option value="F"<?= ($v['civility'] ?? '') === 'F' ? ' selected' : '' ?>><?= __('account.civility_f') ?></option>
        <option value="other"<?= ($v['civility'] ?? '') === 'other' ? ' selected' : '' ?>><?= __('account.civility_other') ?></option>
    </select>
</div>

<div class="form-group">
    <label for="addr-firstname"><?= __('account.firstname') ?> *</label>
    <input type="text" id="addr-firstname" name="firstname"
           value="<?= htmlspecialchars($v['firstname'] ?? '') ?>"
           required autocomplete="given-name">
</div>

<div class="form-group">
    <label for="addr-lastname"><?= __('account.lastname') ?> *</label>
    <input type="text" id="addr-lastname" name="lastname"
           value="<?= htmlspecialchars($v['lastname'] ?? '') ?>"
           required autocomplete="family-name">
</div>

<div class="form-group">
    <label for="addr-street"><?= __('account.address_street') ?> *</label>
    <input type="text" id="addr-street" name="street"
           value="<?= htmlspecialchars($v['street'] ?? '') ?>"
           required autocomplete="street-address">
</div>

<div class="form-group form-group--row">
    <div>
        <label for="addr-zip"><?= __('account.address_zip') ?> *</label>
        <input type="text" id="addr-zip" name="zip_code"
               value="<?= htmlspecialchars($v['zip_code'] ?? '') ?>"
               required autocomplete="postal-code" maxlength="10">
    </div>
    <div>
        <label for="addr-city"><?= __('account.address_city') ?> *</label>
        <input type="text" id="addr-city" name="city"
               value="<?= htmlspecialchars($v['city'] ?? '') ?>"
               required autocomplete="address-level2">
    </div>
</div>

<div class="form-group">
    <label for="addr-country"><?= __('account.address_country') ?></label>
    <input type="text" id="addr-country" name="country"
           value="<?= htmlspecialchars($v['country'] ?? 'France') ?>"
           autocomplete="country-name">
</div>

<div class="form-group">
    <label for="addr-phone"><?= __('account.address_phone') ?></label>
    <input type="tel" id="addr-phone" name="phone"
           value="<?= htmlspecialchars($v['phone'] ?? '') ?>"
           autocomplete="tel">
</div>
