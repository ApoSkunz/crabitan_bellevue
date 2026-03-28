<?php
/**
 * Champs communs du formulaire d'adresse.
 * Variables attendues : $address (array|null — null = création)
 */
$v        = $address ?? [];
$isEdit   = isset($v['type']);
$addrType = $v['type'] ?? '';

// Téléphone : afficher tel quel (format international stocké)
$phoneDisplay = $v['phone'] ?? '';

$countries = [
    'France', 'Belgique', 'Luxembourg', 'Suisse', 'Allemagne', 'Pays-Bas',
    'Espagne', 'Italie', 'Royaume-Uni', 'Portugal', 'Autriche', 'Irlande',
    'Danemark', 'Suède', 'Norvège', 'Finlande', 'Pologne', 'République tchèque',
    'Hongrie', 'Roumanie', 'Grèce', 'Croatie', 'Slovénie', 'Slovaquie',
    'Estonie', 'Lettonie', 'Lituanie', 'Malte', 'Chypre', 'Bulgarie',
    'Maroc', 'Tunisie', 'Algérie', 'États-Unis', 'Canada', 'Japon',
    'Australie', 'Singapour', 'Émirats arabes unis',
];
?>
<div class="form-group">
    <label for="addr-type"><?= __('account.address_type_label') ?> *</label>
    <select id="addr-type" name="type" required<?= $isEdit ? ' disabled' : '' ?>>
        <option value="billing"<?= $addrType === 'billing' ? ' selected' : '' ?>>
            <?= __('account.address_type_billing') ?>
        </option>
        <option value="delivery"<?= $addrType === 'delivery' ? ' selected' : '' ?>>
            <?= __('account.address_type_delivery') ?>
        </option>
    </select>
    <?php if ($isEdit) : ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($addrType) ?>">
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

<!-- Pays AVANT code postal -->
<div class="form-group">
    <label for="addr-country"><?= __('account.address_country') ?> *</label>
    <input type="text" id="addr-country" name="country"
           value="<?= htmlspecialchars($v['country'] ?? 'France') ?>"
           required autocomplete="country-name"
           list="addr-country-list"
           class="js-addr-country">
    <datalist id="addr-country-list">
        <?php foreach ($countries as $c) : ?>
            <option value="<?= htmlspecialchars($c) ?>">
        <?php endforeach; ?>
    </datalist>
    <p class="form-hint js-delivery-notice" hidden><?= __('account.address_delivery_only_france') ?></p>
</div>

<!-- Code postal + ville -->
<div class="form-group form-group--row">
    <div>
        <label for="addr-zip"><?= __('account.address_zip') ?> *</label>
        <input type="text" id="addr-zip" name="zip_code"
               value="<?= htmlspecialchars($v['zip_code'] ?? '') ?>"
               required autocomplete="postal-code"
               maxlength="10"
               placeholder="75001">
        <p class="form-hint js-zip-error" hidden><?= __('account.address_zip_invalid') ?></p>
    </div>
    <div>
        <label for="addr-city"><?= __('account.address_city') ?> *</label>
        <input type="text" id="addr-city" name="city"
               value="<?= htmlspecialchars($v['city'] ?? '') ?>"
               required autocomplete="address-level2"
               list="addr-city-suggestions">
        <datalist id="addr-city-suggestions"></datalist>
    </div>
</div>

<!-- Rue APRÈS ville -->
<div class="form-group">
    <label for="addr-street"><?= __('account.address_street') ?> *</label>
    <input type="text" id="addr-street" name="street"
           value="<?= htmlspecialchars($v['street'] ?? '') ?>"
           required autocomplete="street-address">
</div>

<div class="form-group">
    <label for="addr-phone"><?= __('account.address_phone') ?> *</label>
    <input type="tel" id="addr-phone" name="phone"
           value="<?= htmlspecialchars($phoneDisplay) ?>"
           required autocomplete="tel"
           placeholder="+33 6 12 34 56 78">
    <p class="form-hint"><?= __('account.address_phone_hint') ?></p>
</div>

<script>
(function () {
    var zipInput     = document.getElementById('addr-zip');
    var cityInput    = document.getElementById('addr-city');
    var datalist     = document.getElementById('addr-city-suggestions');
    var typeSelect   = document.getElementById('addr-type');
    var countryInput = document.querySelector('.js-addr-country');
    var zipError     = document.querySelector('.js-zip-error');
    var delivNote    = document.querySelector('.js-delivery-notice');

    // Cache BAN API résultats pour le zip courant
    var cachedZip      = '';
    var cachedFeatures = [];

    // France métro hors Corse : 01000–95999, sauf 20xxx
    function isValidMetroZip(zip) {
        if (!/^\d{5}$/.test(zip)) return false;
        var n   = parseInt(zip, 10);
        var pfx = parseInt(zip.substring(0, 2), 10);
        return n >= 1000 && n <= 95999 && pfx !== 20;
    }

    function populateCityDatalist(features) {
        if (!datalist) return;
        datalist.innerHTML = '';
        features.forEach(function (f) {
            var opt = document.createElement('option');
            opt.value = f.properties.city || f.properties.label;
            datalist.appendChild(opt);
        });
    }

    function fetchCitiesForZip(zip) {
        fetch('https://api-adresse.data.gouv.fr/search/?q=' + zip + '&postcode=' + zip + '&type=municipality&limit=6')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                cachedZip      = zip;
                cachedFeatures = data.features || [];
                populateCityDatalist(cachedFeatures);
                // Auto-fill si une seule commune et champ ville vide
                if (cachedFeatures.length === 1 && cityInput && cityInput.value === '') {
                    cityInput.value = cachedFeatures[0].properties.city || cachedFeatures[0].properties.label;
                }
            })
            .catch(function () {});
    }

    function applyDeliveryMode(isDelivery) {
        if (!countryInput) return;
        if (isDelivery) {
            countryInput.value    = 'France';
            countryInput.readOnly = true;
            countryInput.classList.add('form-input--readonly');
            if (delivNote) delivNote.hidden = false;
        } else {
            countryInput.readOnly = false;
            countryInput.classList.remove('form-input--readonly');
            if (delivNote) delivNote.hidden = true;
        }
    }

    function currentType() {
        if (typeSelect) return typeSelect.value;
        var hidden = document.querySelector('input[name="type"]');
        return hidden ? hidden.value : '';
    }

    // Init type
    applyDeliveryMode(currentType() === 'delivery');
    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            applyDeliveryMode(this.value === 'delivery');
        });
    }

    // ----------------------------------------------------------------
    // Datalist VILLE — clear-on-focus pour montrer toutes les options
    // (les navigateurs filtrent le datalist selon la valeur courante)
    // ----------------------------------------------------------------
    var cityBeforeFocus = '';
    if (cityInput) {
        cityInput.addEventListener('focus', function () {
            if (cachedFeatures.length > 1) {
                cityBeforeFocus = this.value;
                this.value = '';
                populateCityDatalist(cachedFeatures);
            }
        });
        cityInput.addEventListener('blur', function () {
            if (this.value === '' && cityBeforeFocus !== '') {
                this.value = cityBeforeFocus;
            }
            cityBeforeFocus = '';
        });
    }

    // ----------------------------------------------------------------
    // Datalist PAYS — même comportement clear-on-focus
    // ----------------------------------------------------------------
    var countryBeforeFocus = '';
    if (countryInput && !countryInput.readOnly) {
        countryInput.addEventListener('focus', function () {
            if (this.readOnly) return;
            countryBeforeFocus = this.value;
            this.value = '';
        });
        countryInput.addEventListener('blur', function () {
            if (this.value === '' && countryBeforeFocus !== '') {
                this.value = countryBeforeFocus;
            }
            countryBeforeFocus = '';
        });
    }

    // ----------------------------------------------------------------
    // Zip → BAN API villes (France métro uniquement)
    // ----------------------------------------------------------------
    var banTimer;
    if (zipInput) {
        zipInput.addEventListener('input', function () {
            var zip = this.value.trim();
            if (zipError) zipError.hidden = true;
            clearTimeout(banTimer);

            // Nouveau zip → vider le cache
            if (zip !== cachedZip) {
                cachedZip      = '';
                cachedFeatures = [];
                if (datalist) datalist.innerHTML = '';
            }

            if (zip.length !== 5 || !/^\d{5}$/.test(zip)) return;

            if (!isValidMetroZip(zip)) {
                if (zipError) zipError.hidden = false;
                return;
            }

            // Auto-set France si pays vide ou déjà France
            if (countryInput && !countryInput.readOnly) {
                var cur = countryInput.value.trim();
                if (cur === '' || cur === 'France') {
                    countryInput.value    = 'France';
                    countryInput.readOnly = true;
                    countryInput.classList.add('form-input--readonly');
                    if (delivNote) delivNote.hidden = false;
                }
            }

            banTimer = setTimeout(function () { fetchCitiesForZip(zip); }, 350);
        });

        // ----------------------------------------------------------------
        // Init BAN sur formulaire de modification (zip déjà renseigné)
        // ----------------------------------------------------------------
        var initZip = zipInput.value.trim();
        if (initZip.length === 5 && isValidMetroZip(initZip)) {
            fetchCitiesForZip(initZip);
        }
    }
})();
</script>
