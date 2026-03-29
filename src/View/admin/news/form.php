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
                           value="<?= htmlspecialchars($titleData['fr'] ?? '') ?>"
                           oninput="updateNewsSlug(this.value)">
                    <span class="admin-field__error"
                          id="err-title_fr"
                          style="display:<?= hasError($errors, 'title_fr') ? 'block' : 'none' ?>;">
                        <?= hasError($errors, 'title_fr') ? htmlspecialchars($errors['title_fr']) : 'Ce champ est obligatoire.' ?>
                    </span>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="title_en">Anglais <span style="font-weight:400;font-size:0.72rem;">(traduit automatiquement)</span></label>
                    <input type="text" id="title_en" name="title_en"
                           class="admin-field__input"
                           style="background:rgba(0,0,0,0.03);color:#8a7a60;cursor:default;"
                           value="<?= htmlspecialchars($titleData['en'] ?? '') ?>" readonly>
                </div>
            </div>
            <div class="admin-field" style="margin-top:0.75rem;">
                <label class="admin-field__label" for="news-slug-preview">Slug (généré depuis le titre FR)</label>
                <input type="text" id="news-slug-preview" readonly
                       class="admin-field__input"
                       style="background:rgba(0,0,0,0.03);color:#8a7a60;cursor:default;"
                       value="<?= htmlspecialchars($article['slug'] ?? '') ?>">
                <p style="font-size:0.72rem;color:#8a7a60;margin-top:0.25rem;">
                    Mis à jour automatiquement depuis le titre FR à chaque enregistrement.
                </p>
            </div>
        </div>

        <!-- ---- Contenu bilingue ---- -->
        <div class="admin-form__section">
            <h3>Contenu *</h3>
            <div class="admin-form__grid" style="align-items:start;">
                <div class="admin-field">
                    <label class="admin-field__label" for="text_content_fr">Français *</label>
                    <textarea id="text_content_fr" name="text_content_fr"
                              class="admin-field__textarea<?= hasError($errors, 'text_content_fr') ? $errClass : '' ?>"
                              rows="8"><?= htmlspecialchars($contentData['fr'] ?? '') ?></textarea>
                    <span class="admin-field__error"
                          id="err-text_content_fr"
                          style="display:<?= hasError($errors, 'text_content_fr') ? 'block' : 'none' ?>;">
                        <?= hasError($errors, 'text_content_fr') ? htmlspecialchars($errors['text_content_fr']) : 'Ce champ est obligatoire.' ?>
                    </span>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="text_content_en">Anglais <span style="font-weight:400;font-size:0.72rem;">(traduit automatiquement)</span></label>
                    <textarea id="text_content_en" name="text_content_en"
                              class="admin-field__textarea"
                              style="background:rgba(0,0,0,0.03);color:#8a7a60;cursor:default;"
                              rows="8" readonly><?= htmlspecialchars($contentData['en'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ---- Image ---- -->
        <div class="admin-form__section">
            <h3>Image <?= !$isEdit ? '*' : '' ?></h3>

            <?php if ($isEdit && !empty($article['image_path'])) : ?>
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.75rem;color:#8a7a60;margin-bottom:0.5rem;">Image actuelle :</p>
                    <img class="js-image-preview"
                         src="/assets/images/news/<?= htmlspecialchars($article['image_path']) ?>"
                         alt="" style="max-height:140px;max-width:220px;object-fit:cover;
                                       border:1px solid rgba(0,0,0,0.1);border-radius:4px;">
                </div>
            <?php else : ?>
                <img class="js-image-preview" src="" alt=""
                     style="display:none;max-height:140px;max-width:220px;object-fit:cover;
                            border:1px solid rgba(0,0,0,0.1);border-radius:4px;margin-bottom:0.75rem;">
            <?php endif; ?>

            <div class="admin-field">
                <label class="admin-field__label" for="image">
                    <?= $isEdit ? 'Changer l\'image (l\'ancienne sera supprimée)' : 'Téléverser l\'image *' ?>
                    <span style="font-weight:400;font-size:0.75rem;"> — jpg / png / webp</span>
                </label>
                <input type="file" id="image" name="image"
                       accept="image/jpeg,image/png,image/webp"
                       class="admin-field__input<?= hasError($errors, 'image') ? $errClass : '' ?>"
                       onchange="previewImage(this)">
                <span class="admin-field__error"
                      id="err-image"
                      style="display:<?= hasError($errors, 'image') ? 'block' : 'none' ?>;">
                    <?= hasError($errors, 'image') ? htmlspecialchars($errors['image']) : 'Une image est obligatoire pour la création d\'un article.' ?>
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

<script>
document.getElementById('news-form').addEventListener('submit', function(e) {
    let hasError = false;
    const checks = [
        { id: 'title_fr',        errId: 'err-title_fr',        getValue: el => el.value.trim() },
        { id: 'text_content_fr', errId: 'err-text_content_fr', getValue: el => el.value.trim() },
        <?php if (!$isEdit) : ?>
        { id: 'image',           errId: 'err-image',           getValue: el => el.files && el.files.length > 0 ? '1' : '' },
        <?php endif; ?>
    ];
    checks.forEach(function(check) {
        const el  = document.getElementById(check.id);
        const err = document.getElementById(check.errId);
        if (!el || !err) return;
        if (!check.getValue(el)) {
            el.classList.add('is-error');
            err.style.display = 'block';
            hasError = true;
        } else {
            el.classList.remove('is-error');
            err.style.display = 'none';
        }
    });
    if (hasError) {
        e.preventDefault();
        document.querySelector('.admin-field__error[style*="block"]')
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
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = ''; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
