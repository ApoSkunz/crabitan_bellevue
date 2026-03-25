<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$isEdit    = $wine !== null && isset($wine['id']);
$action    = $isEdit ? '/admin/vins/' . (int) $wine['id'] . '/modifier' : '/admin/vins/ajouter';

// Décode les champs JSON pour affichage en édition
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

$decoded = [];
foreach ($jsonFields as $field) {
    $raw = $wine[$field] ?? '{}';
    // Si le champ vient du POST (formulaire), il est déjà une string; sinon c'est du JSON BDD
    if (is_string($raw)) {
        $decoded[$field] = json_decode($raw, true) ?? ['fr' => '', 'en' => ''];
    } else {
        $decoded[$field] = ['fr' => '', 'en' => ''];
    }
}

function fieldVal(mixed $wine, string $key, mixed $default = ''): mixed
{
    return htmlspecialchars((string) ($wine[$key] ?? $default));
}

function hasError(array $errors, string $key): bool
{
    return isset($errors[$key]);
}
?>

<?php if (!empty($errors)) : ?>
    <div class="admin-flash admin-flash--error">
        Veuillez corriger les erreurs ci-dessous.
    </div>
<?php endif; ?>

<div class="admin-page-header">
    <h1><?= $isEdit ? 'Modifier un vin' : 'Ajouter un vin' ?></h1>
    <a href="/admin/vins" class="admin-btn admin-btn--outline">← Retour</a>
</div>

<div class="admin-card">
    <div class="admin-card__body">
    <form method="POST" action="<?= htmlspecialchars($action) ?>" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <!-- ---- Informations principales ---- -->
        <div class="admin-form__grid">
            <div class="admin-field admin-field--full">
                <label class="admin-field__label" for="label_name">Nom du vin *</label>
                <input type="text" id="label_name" name="label_name" required
                       class="admin-field__input<?= hasError($errors, 'label_name') ? ' is-error' : '' ?>"
                       value="<?= fieldVal($wine, 'label_name') ?>">
                <?php if (hasError($errors, 'label_name')) : ?>
                    <span style="color:#dc2626;font-size:0.75rem;"><?= htmlspecialchars($errors['label_name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="wine_color">Couleur *</label>
                <select id="wine_color" name="wine_color" class="admin-field__select<?= hasError($errors, 'wine_color') ? ' is-error' : '' ?>">
                    <?php foreach (['red' => 'Rouge', 'white' => 'Blanc', 'rosé' => 'Rosé', 'sweet' => 'Liquoreux'] as $val => $label) : ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= ($wine['wine_color'] ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="format">Format</label>
                <select id="format" name="format" class="admin-field__select">
                    <option value="bottle" <?= ($wine['format'] ?? 'bottle') === 'bottle' ? 'selected' : '' ?>>Bouteille</option>
                    <option value="bib"    <?= ($wine['format'] ?? '') === 'bib' ? 'selected' : '' ?>>Bag-in-Box</option>
                </select>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="vintage">Millésime *</label>
                <input type="number" id="vintage" name="vintage" required min="1900" max="<?= (int) date('Y') + 2 ?>"
                       class="admin-field__input<?= hasError($errors, 'vintage') ? ' is-error' : '' ?>"
                       value="<?= fieldVal($wine, 'vintage', date('Y')) ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="price">Prix (€) *</label>
                <input type="number" id="price" name="price" required min="0.01" step="0.01"
                       class="admin-field__input<?= hasError($errors, 'price') ? ' is-error' : '' ?>"
                       value="<?= fieldVal($wine, 'price', '0.00') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="quantity">Stock (bouteilles)</label>
                <input type="number" id="quantity" name="quantity" min="0"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'quantity', '0') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="area">Superficie (ha)</label>
                <input type="number" id="area" name="area" min="0" step="0.01"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'area', '0') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="age_of_vineyard">Âge des vignes (ans)</label>
                <input type="number" id="age_of_vineyard" name="age_of_vineyard" min="0"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'age_of_vineyard', '0') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="city">Commune</label>
                <input type="text" id="city" name="city"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'city') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="variety_of_vine">Cépage(s)</label>
                <input type="text" id="variety_of_vine" name="variety_of_vine"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'variety_of_vine') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="certification_label">Label / Certification</label>
                <input type="text" id="certification_label" name="certification_label"
                       class="admin-field__input"
                       value="<?= fieldVal($wine, 'certification_label') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="slug">Slug URL</label>
                <input type="text" id="slug" name="slug"
                       class="admin-field__input"
                       placeholder="généré automatiquement si vide"
                       value="<?= fieldVal($wine, 'slug') ?>">
            </div>

            <div class="admin-field admin-field--full">
                <label class="admin-field__label" for="image_path">Chemin de l'image</label>
                <input type="text" id="image_path" name="image_path"
                       class="admin-field__input"
                       placeholder="/assets/images/wines/mon-vin.jpg"
                       value="<?= fieldVal($wine, 'image_path') ?>">
            </div>

            <div class="admin-field">
                <div class="admin-field__check">
                    <input type="hidden" name="available" value="0">
                    <input type="checkbox" id="available" name="available" value="1"
                           <?= ($wine['available'] ?? 1) ? 'checked' : '' ?>>
                    <label for="available">Disponible à la vente</label>
                </div>
            </div>

            <div class="admin-field">
                <div class="admin-field__check">
                    <input type="hidden" name="is_cuvee_speciale" value="0">
                    <input type="checkbox" id="is_cuvee_speciale" name="is_cuvee_speciale" value="1"
                           <?= ($wine['is_cuvee_speciale'] ?? 0) ? 'checked' : '' ?>>
                    <label for="is_cuvee_speciale">Cuvée spéciale</label>
                </div>
            </div>
        </div>

        <!-- ---- Champs bilingues ---- -->
        <div class="admin-form__section">
            <h3>Descriptions bilingues</h3>
            <?php foreach ($jsonFields as $field) : ?>
                <div style="margin-bottom:1.25rem;">
                    <p style="font-family:var(--font-sans);font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#6b5f50;margin-bottom:0.5rem;">
                        <?= htmlspecialchars($jsonLabels[$field]) ?>
                    </p>
                    <div class="admin-form__grid">
                        <div class="admin-field">
                            <label class="admin-field__label" for="<?= $field ?>_fr">Français</label>
                            <textarea id="<?= $field ?>_fr" name="<?= $field ?>_fr"
                                      class="admin-field__textarea"><?= htmlspecialchars($decoded[$field]['fr'] ?? '') ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label class="admin-field__label" for="<?= $field ?>_en">Anglais</label>
                            <textarea id="<?= $field ?>_en" name="<?= $field ?>_en"
                                      class="admin-field__textarea"><?= htmlspecialchars($decoded[$field]['en'] ?? '') ?></textarea>
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

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
