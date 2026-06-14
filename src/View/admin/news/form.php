<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$isEdit = $article !== null && isset($article['id']);
$action = $isEdit
    ? '/admin/actualites/' . (int) $article['id'] . '/modifier'
    : '/admin/actualites/ajouter';

$titleData   = json_decode($article['title'] ?? '{}', true) ?? ['fr' => '', 'en' => ''];
$contentData = json_decode($article['text_content'] ?? '{}', true) ?? ['fr' => '', 'en' => ''];

if (!function_exists('hasError')) {
function hasError(array $errors, string $key): bool
{
    return isset($errors[$key]);
}
}
$errClass = ' is-error';
$uHidden  = ' u-hidden';
?>

<?php if (!empty($errors)) : ?>
    <div class="admin-flash admin-flash--error">Veuillez corriger les erreurs ci-dessous.</div>
<?php endif; ?>

<div class="admin-page-header">
    <h1><?= $isEdit ? 'Modifier un article' : 'Ajouter un article' ?></h1>
    <a href="/admin/actualites" class="admin-btn admin-btn--outline">← Retour</a>
</div>

<div class="admin-card">
    <div class="admin-card__body">
    <form id="news-form" method="POST" action="<?= htmlspecialchars($action) ?>"
          class="admin-form" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <!-- ---- Titre bilingue ---- -->
        <div class="admin-form__section">
            <h3>Titre *</h3>
            <div class="admin-form__grid">
                <div class="admin-field">
                    <label class="admin-field__label" for="title_fr">Français *</label>
                    <input type="text" id="title_fr" name="title_fr"
                           class="admin-field__input<?= hasError($errors, 'title_fr') ? $errClass : '' ?>"
                           value="<?= htmlspecialchars($titleData['fr'] ?? '') ?>">
                    <span class="admin-field__error<?= hasError($errors, 'title_fr') ? '' : $uHidden ?>"
                          id="err-title_fr">
                        <?= hasError($errors, 'title_fr') ? htmlspecialchars($errors['title_fr']) : 'Ce champ est obligatoire.' ?>
                    </span>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="title_en">Anglais <span class="u-font-normal u-text-2xs">(traduit automatiquement)</span></label>
                    <input type="text" id="title_en" name="title_en"
                           class="admin-field__input admin-field__input--readonly"
                           value="<?= htmlspecialchars($titleData['en'] ?? '') ?>" readonly>
                </div>
            </div>
            <div class="admin-field u-mt-3">
                <label class="admin-field__label" for="news-slug-preview">Slug (généré depuis le titre FR)</label>
                <input type="text" id="news-slug-preview" readonly
                       class="admin-field__input admin-field__input--readonly"
                       value="<?= htmlspecialchars($article['slug'] ?? '') ?>">
                <p class="u-text-2xs u-mt-1 text-muted">
                    Mis à jour automatiquement depuis le titre FR à chaque enregistrement.
                </p>
            </div>
        </div>

        <!-- ---- Contenu bilingue ---- -->
        <div class="admin-form__section">
            <h3>Contenu *</h3>
            <div class="admin-form__grid u-items-start">
                <div class="admin-field">
                    <label class="admin-field__label" for="text_content_fr">Français *</label>
                    <textarea id="text_content_fr" name="text_content_fr"
                              class="admin-field__textarea<?= hasError($errors, 'text_content_fr') ? $errClass : '' ?>"
                              rows="8"><?= htmlspecialchars($contentData['fr'] ?? '') ?></textarea>
                    <span class="admin-field__error<?= hasError($errors, 'text_content_fr') ? '' : $uHidden ?>"
                          id="err-text_content_fr">
                        <?= hasError($errors, 'text_content_fr') ? htmlspecialchars($errors['text_content_fr']) : 'Ce champ est obligatoire.' ?>
                    </span>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="text_content_en">Anglais <span class="u-font-normal u-text-2xs">(traduit automatiquement)</span></label>
                    <textarea id="text_content_en" name="text_content_en"
                              class="admin-field__textarea admin-field__textarea--readonly"
                              rows="8" readonly><?= htmlspecialchars($contentData['en'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ---- Image ---- -->
        <div class="admin-form__section">
            <h3>Image</h3>

            <?php if ($isEdit && !empty($article['image_path'])) : ?>
                <div class="u-mb-4">
                    <p class="u-text-2xs text-muted u-mb-2">Image actuelle :</p>
                    <img class="js-image-preview admin-image-preview"
                         src="/assets/images/news/<?= htmlspecialchars($article['image_path']) ?>"
                         alt="">
                </div>
            <?php else : ?>
                <img class="js-image-preview admin-image-preview u-hidden u-mb-3" src="" alt="">
            <?php endif; ?>

            <div class="admin-field">
                <label class="admin-field__label" for="image">
                    <?= $isEdit ? 'Changer l\'image (l\'ancienne sera supprimée)' : 'Téléverser une image (optionnel)' ?>
                    <span class="u-font-normal u-text-2xs"> — jpg / png / webp</span>
                </label>
                <input type="file" id="image" name="image"
                       accept="image/jpeg,image/png,image/webp"
                       class="admin-field__input<?= hasError($errors, 'image') ? $errClass : '' ?>">
                <span class="admin-field__error<?= hasError($errors, 'image') ? '' : $uHidden ?>"
                      id="err-image">
                    <?= hasError($errors, 'image') ? htmlspecialchars($errors['image']) : '' ?>
                </span>
            </div>
        </div>

        <!-- ---- Lien externe optionnel ---- -->
        <div class="admin-form__section">
            <h3>Lien externe (optionnel)</h3>
            <div class="admin-field">
                <label class="admin-field__label" for="link_path">URL</label>
                <input type="url" id="link_path" name="link_path"
                       class="admin-field__input"
                       placeholder="https://…"
                       value="<?= htmlspecialchars($article['link_path'] ?? '') ?>">
            </div>
        </div>

        <div class="admin-form__actions">
            <button type="submit" class="admin-btn admin-btn--primary">
                <?= $isEdit ? 'Enregistrer les modifications' : 'Publier l\'article' ?>
            </button>
            <a href="/admin/actualites" class="admin-btn admin-btn--outline">Annuler</a>
        </div>

    </form>
    </div>
</div>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
document.getElementById('news-form').addEventListener('submit', function(e) {
    let hasError = false;
    const checks = [
        { id: 'title_fr',        errId: 'err-title_fr',        getValue: el => el.value.trim() },
        { id: 'text_content_fr', errId: 'err-text_content_fr', getValue: el => el.value.trim() },
    ];
    checks.forEach(function(check) {
        const el  = document.getElementById(check.id);
        const err = document.getElementById(check.errId);
        if (!el || !err) return;
        if (!check.getValue(el)) {
            el.classList.add('is-error');
            err.classList.remove('u-hidden');
            hasError = true;
        } else {
            el.classList.remove('is-error');
            err.classList.add('u-hidden');
        }
    });
    if (hasError) {
        e.preventDefault();
        document.querySelector('.admin-field__error:not(.u-hidden)')
            ?.closest('.admin-field')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

function toSlug(str) {
    return str.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .substring(0, 80);
}

function updateNewsSlug(val) {
    document.getElementById('news-slug-preview').value = toSlug(val);
}

function previewImage(input) {
    const preview = document.querySelector('.js-image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.classList.remove('u-hidden'); };
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const titleInput = document.getElementById('title_fr');
    const imageInput = document.getElementById('image');
    if (titleInput) { titleInput.addEventListener('input', function () { updateNewsSlug(this.value); }); }
    if (imageInput) { imageInput.addEventListener('change', function () { previewImage(this); }); }
});
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
