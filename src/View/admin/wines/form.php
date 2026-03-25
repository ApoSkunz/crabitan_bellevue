<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$isEdit  = $wine !== null && isset($wine['id']);
$action  = $isEdit
    ? '/admin/vins/' . (int) $wine['id'] . '/modifier'
    : '/admin/vins/ajouter';

$currentAppellation = $wine['label_name'] ?? '';
$colorLabels = ['sweet' => 'Liquoreux', 'white' => 'Blanc', 'rosé' => 'Rosé', 'red' => 'Rouge'];

$jsonFields = ['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation', 'award', 'extra_comment'];
$jsonLabels = [
    'oenological_comment' => 'Commentaire œnologique',
    'soil'                => 'Sol',
    'pruning'             => 'Taille',
    'harvest'             => 'Vendanges',
    'vinification'        => 'Vinification',
    'barrel_fermentation' => 'Élevage / Barrique',
    'award'               => 'Récompenses',
    'extra_comment'       => 'Commentaire supplémentaire',
];
$jsonRequired = ['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'];

$decoded = [];
foreach ($jsonFields as $field) {
    $raw = $wine[$field] ?? '{}';
    $decoded[$field] = is_string($raw) ? (json_decode($raw, true) ?? ['fr' => '', 'en' => '']) : ['fr' => '', 'en' => ''];
}

function fieldVal(mixed $wine, string $key, mixed $default = ''): string
{
    return htmlspecialchars((string) ($wine[$key] ?? $default));
}

function hasError(array $errors, string $key): bool
{
    return isset($errors[$key]);
}

$maxYear = (int) date('Y');
?>

<?php if (!empty($errors)) : ?>
    <div class="admin-flash admin-flash--error">Veuillez corriger les erreurs ci-dessous.</div>
<?php endif; ?>

<div class="admin-page-header">
    <h1><?= $isEdit ? 'Modifier un vin' : 'Ajouter un vin' ?></h1>
    <a href="/admin/vins" class="admin-btn admin-btn--outline">← Retour</a>
</div>

<div class="admin-card">
    <div class="admin-card__body">
    <form method="POST" action="<?= htmlspecialchars($action) ?>"
          class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <!-- ---- Appellation + couleur auto ---- -->
        <div class="admin-form__grid">

            <div class="admin-field admin-field--full">
                <label class="admin-field__label" for="appellation">Appellation *</label>
                <select id="appellation" name="appellation" required
                        class="admin-field__select<?= hasError($errors, 'appellation') ? ' is-error' : '' ?>"
                        onchange="onAppellationChange(this.value)">
                    <option value="">— Choisir une appellation —</option>
                    <?php foreach ($appellations as $label => $color) : ?>
                        <option value="<?= htmlspecialchars($label) ?>"
                            <?= $currentAppellation === $label ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (hasError($errors, 'appellation')) : ?>
                    <span class="admin-field__error"><?= htmlspecialchars($errors['appellation']) ?></span>
                <?php endif; ?>
                <p id="color-preview" style="margin-top:0.35rem;font-size:0.8rem;color:#8a7a60;">
                    <?php
                    $initColor = $appellations[$currentAppellation] ?? '';
                    echo $initColor !== '' ? 'Couleur : <strong>' . htmlspecialchars($colorLabels[$initColor] ?? $initColor) . '</strong>' : '';
                    ?>
                </p>
                <input type="hidden" id="wine_color" name="wine_color"
                       value="<?= htmlspecialchars($appellations[$currentAppellation] ?? '') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="format">Format *</label>
                <select id="format" name="format" required class="admin-field__select">
                    <option value="bottle" <?= ($wine['format'] ?? 'bottle') === 'bottle' ? 'selected' : '' ?>>Bouteille</option>
                    <option value="bib"    <?= ($wine['format'] ?? '') === 'bib' ? 'selected' : '' ?>>Bag-in-Box</option>
                </select>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="vintage">Millésime * (max <?= $maxYear ?>)</label>
                <input type="number" id="vintage" name="vintage" required
                       min="1900" max="<?= $maxYear ?>"
                       class="admin-field__input<?= hasError($errors, 'vintage') ? ' is-error' : '' ?>"
                       value="<?= fieldVal($wine, 'vintage', date('Y')) ?>">
                <?php if (hasError($errors, 'vintage')) : ?>
                    <span class="admin-field__error"><?= htmlspecialchars($errors['vintage']) ?></span>
                <?php endif; ?>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="price">Prix (€) *</label>
                <input type="number" id="price" name="price" required
                       min="0.10" step="0.10"
                       class="admin-field__input<?= hasError($errors, 'price') ? ' is-error' : '' ?>"
                       value="<?= fieldVal($wine, 'price', '0.00') ?>">
                <?php if (hasError($errors, 'price')) : ?>
                    <span class="admin-field__error"><?= htmlspecialchars($errors['price']) ?></span>
                <?php endif; ?>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="quantity">Quantité produite (bt) *</label>
                <input type="number" id="quantity" name="quantity" required min="0"
                       class="admin-field__input<?= hasError($errors, 'quantity') ? ' is-error' : '' ?>"
                       value="<?= fieldVal($wine, 'quantity', '0') ?>">
                <?php if (hasError($errors, 'quantity')) : ?>
                    <span class="admin-field__error"><?= htmlspecialchars($errors['quantity']) ?></span>
                <?php endif; ?>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="area">Superficie (ha) *</label>
                <input type="number" id="area" name="area" required min="0" step="0.01"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'area', '0') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="age_of_vineyard">Âge des vignes (ans) *</label>
                <input type="number" id="age_of_vineyard" name="age_of_vineyard" required min="0"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'age_of_vineyard', '0') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="city">Commune *</label>
                <input type="text" id="city" name="city" required
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'city') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="variety_of_vine">Cépage(s) *</label>
                <input type="text" id="variety_of_vine" name="variety_of_vine" required
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'variety_of_vine') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="certification_label">Label / Certification *</label>
                <input type="text" id="certification_label" name="certification_label" required
                       placeholder="ex: AOC, IGP…"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'certification_label') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="slug-preview">Slug (généré automatiquement)</label>
                <input type="text" id="slug-preview" readonly
                       class="admin-field__input"
                       style="background:rgba(0,0,0,0.03);color:#8a7a60;cursor:default;"
                       value="<?= fieldVal($wine, 'slug') ?>">
                <p style="font-size:0.72rem;color:#8a7a60;margin-top:0.25rem;">
                    Généré depuis l'appellation + millésime<?= $isEdit ? ' — mis à jour à chaque modification' : '' ?>.
                </p>
            </div>

            <div class="admin-field admin-field--full">
                <div class="admin-field__check">
                    <input type="hidden" name="available" value="0">
                    <input type="checkbox" id="available" name="available" value="1"
                           <?= ($wine['available'] ?? 1) ? 'checked' : '' ?>>
                    <label for="available">Disponible à la vente</label>
                </div>
                <div class="admin-field__check">
                    <input type="hidden" name="is_cuvee_speciale" value="0">
                    <input type="checkbox" id="is_cuvee_speciale" name="is_cuvee_speciale" value="1"
                           <?= ($wine['is_cuvee_speciale'] ?? 0) ? 'checked' : '' ?>>
                    <label for="is_cuvee_speciale">Cuvée spéciale</label>
                </div>
            </div>
        </div>

        <!-- ---- Image ---- -->
        <div class="admin-form__section">
            <h3>Image du vin <?= !$isEdit ? '*' : '' ?></h3>

            <?php if ($isEdit && !empty($wine['image_path'])) : ?>
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.75rem;color:#8a7a60;margin-bottom:0.5rem;">Image actuelle :</p>
                    <img id="image-preview"
                         src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
                         alt="Image actuelle"
                         style="max-height:160px;max-width:240px;object-fit:contain;
                                border:1px solid rgba(0,0,0,0.1);border-radius:4px;">
                </div>
            <?php else : ?>
                <img id="image-preview" src="" alt=""
                     style="display:none;max-height:160px;max-width:240px;object-fit:contain;
                            border:1px solid rgba(0,0,0,0.1);border-radius:4px;margin-bottom:0.75rem;">
            <?php endif; ?>

            <div class="admin-field">
                <label class="admin-field__label" for="image">
                    <?= $isEdit ? 'Changer l\'image — jpg / png / webp (l\'ancienne sera supprimée)' : 'Téléverser l\'image — jpg / png / webp *' ?>
                </label>
                <input type="file" id="image" name="image"
                       accept="image/jpeg,image/png,image/webp"
                       class="admin-field__input<?= hasError($errors, 'image') ? ' is-error' : '' ?>"
                       onchange="previewImage(this)">
                <?php if (hasError($errors, 'image')) : ?>
                    <span class="admin-field__error"><?= htmlspecialchars($errors['image']) ?></span>
                <?php endif; ?>
                <p style="font-size:0.73rem;color:#8a7a60;margin-top:0.3rem;">
                    Nom généré automatiquement : <code>Wine_Appellation_Millésime_token.ext</code>
                </p>
            </div>
        </div>

        <!-- ---- Descriptions bilingues ---- -->
        <div class="admin-form__section">
            <h3>Descriptions bilingues</h3>
            <p style="font-size:0.75rem;color:#8a7a60;margin-bottom:1rem;">
                Les champs marqués * sont obligatoires. La version EN est traduite automatiquement
                lors de l'enregistrement (champ non éditable).
            </p>
            <?php foreach ($jsonFields as $field) : ?>
                <?php $isRequired = in_array($field, $jsonRequired, true); ?>
                <div style="margin-bottom:1.5rem;">
                    <p style="font-family:var(--font-sans);font-size:0.72rem;font-weight:700;
                               letter-spacing:0.1em;text-transform:uppercase;color:#6b5f50;margin-bottom:0.5rem;">
                        <?= htmlspecialchars($jsonLabels[$field]) ?><?= $isRequired ? ' *' : '' ?>
                    </p>
                    <div class="admin-form__grid" style="align-items:start;">
                        <div class="admin-field">
                            <label class="admin-field__label" for="<?= $field ?>_fr">Français<?= $isRequired ? ' *' : '' ?></label>
                            <textarea id="<?= $field ?>_fr" name="<?= $field ?>_fr"
                                      class="admin-field__textarea<?= hasError($errors, $field . '_fr') ? ' is-error' : '' ?>" rows="3"
                                      <?= $isRequired ? 'required' : '' ?>><?= htmlspecialchars($decoded[$field]['fr'] ?? '') ?></textarea>
                            <?php if (hasError($errors, $field . '_fr')) : ?>
                                <span class="admin-field__error"><?= htmlspecialchars($errors[$field . '_fr']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-field">
                            <label class="admin-field__label" for="<?= $field ?>_en">Anglais <span style="font-weight:400;font-size:0.72rem;">(traduit automatiquement)</span></label>
                            <textarea id="<?= $field ?>_en" name="<?= $field ?>_en"
                                      class="admin-field__textarea"
                                      style="background:rgba(0,0,0,0.03);color:#8a7a60;cursor:default;"
                                      rows="3" readonly><?= htmlspecialchars($decoded[$field]['en'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-form__actions">
            <button type="submit" class="admin-btn admin-btn--primary">
                <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le vin' ?>
            </button>
            <a href="/admin/vins" class="admin-btn admin-btn--outline">Annuler</a>
        </div>

    </form>
    </div>
</div>

<script>
const APPELLATION_COLORS = <?= json_encode(array_map(
    fn($c) => match ($c) {
        'sweet' => 'Liquoreux', 'white' => 'Blanc', 'rosé' => 'Rosé', 'red' => 'Rouge', default => $c,
    },
    $appellations
), JSON_UNESCAPED_UNICODE) ?>;
const APPELLATION_VALUES = <?= json_encode($appellations, JSON_UNESCAPED_UNICODE) ?>;

function toSlug(str) {
    return str.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function updateSlugPreview() {
    const appellation = document.getElementById('appellation').value;
    const vintage     = document.getElementById('vintage').value;
    if (appellation && vintage) {
        document.getElementById('slug-preview').value = toSlug(appellation + '-' + vintage);
    }
}

function onAppellationChange(val) {
    document.getElementById('wine_color').value = APPELLATION_VALUES[val] || '';
    const preview = document.getElementById('color-preview');
    preview.innerHTML = val ? 'Couleur\u00a0: <strong>' + (APPELLATION_COLORS[val] || '') + '</strong>' : '';
    updateSlugPreview();
}

document.getElementById('vintage').addEventListener('input', updateSlugPreview);
updateSlugPreview();

function previewImage(input) {
    const preview = document.getElementById('image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = ''; };
        reader.readAsDataURL(input.files[0]);
    }
}

</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
