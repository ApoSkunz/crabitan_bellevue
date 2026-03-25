<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$isEdit = $article !== null && isset($article['id']);
$action = $isEdit
    ? '/admin/actualites/' . (int) $article['id'] . '/modifier'
    : '/admin/actualites/ajouter';

$titleData   = json_decode($article['title'] ?? '{}', true) ?? ['fr' => '', 'en' => ''];
$contentData = json_decode($article['text_content'] ?? '{}', true) ?? ['fr' => '', 'en' => ''];

function hasError(array $errors, string $key): bool
{
    return isset($errors[$key]);
}
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
    <form method="POST" action="<?= htmlspecialchars($action) ?>"
          class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <!-- ---- Titre bilingue ---- -->
        <div class="admin-form__section">
            <h3>Titre *</h3>
            <div class="admin-form__grid">
                <div class="admin-field">
                    <label class="admin-field__label" for="title_fr">Français *</label>
                    <input type="text" id="title_fr" name="title_fr" required
                           class="admin-field__input<?= hasError($errors, 'title_fr') ? ' is-error' : '' ?>"
                           value="<?= htmlspecialchars($titleData['fr'] ?? '') ?>"
                           oninput="updateNewsSlug(this.value)">
                    <?php if (hasError($errors, 'title_fr')) : ?>
                        <span class="admin-field__error"><?= htmlspecialchars($errors['title_fr']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="title_en">Anglais <span style="font-weight:400;font-size:0.72rem;">(auto si vide)</span></label>
                    <input type="text" id="title_en" name="title_en"
                           class="admin-field__input"
                           value="<?= htmlspecialchars($titleData['en'] ?? '') ?>">
                </div>
            </div>
            <div class="admin-field" style="margin-top:0.75rem;">
                <label class="admin-field__label" for="news-slug-preview">Slug (généré depuis le titre FR)</label>
                <input type="text" id="news-slug-preview" readonly
                       class="admin-field__input"
                       style="background:rgba(0,0,0,0.03);color:#8a7a60;cursor:default;"
                       value="<?= htmlspecialchars($article['slug'] ?? '') ?>">
                <?php if ($isEdit) : ?>
                    <p style="font-size:0.72rem;color:#8a7a60;margin-top:0.25rem;">
                        Le slug est recalculé automatiquement à chaque enregistrement.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ---- Contenu bilingue ---- -->
        <div class="admin-form__section">
            <h3>Contenu *</h3>
            <div class="admin-form__grid" style="align-items:start;">
                <div class="admin-field">
                    <label class="admin-field__label" for="text_content_fr">Français *</label>
                    <textarea id="text_content_fr" name="text_content_fr" required
                              class="admin-field__textarea<?= hasError($errors, 'text_content_fr') ? ' is-error' : '' ?>"
                              rows="8"><?= htmlspecialchars($contentData['fr'] ?? '') ?></textarea>
                    <?php if (hasError($errors, 'text_content_fr')) : ?>
                        <span class="admin-field__error"><?= htmlspecialchars($errors['text_content_fr']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="text_content_en">Anglais <span style="font-weight:400;font-size:0.72rem;">(auto si vide)</span></label>
                    <textarea id="text_content_en" name="text_content_en"
                              class="admin-field__textarea"
                              rows="8"><?= htmlspecialchars($contentData['en'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ---- Image ---- -->
        <div class="admin-form__section">
            <h3>Image <?= !$isEdit ? '*' : '' ?></h3>

            <?php if ($isEdit && !empty($article['image_path'])) : ?>
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.75rem;color:#8a7a60;margin-bottom:0.5rem;">Image actuelle :</p>
                    <img id="image-preview"
                         src="/assets/images/news/<?= htmlspecialchars($article['image_path']) ?>"
                         alt="" style="max-height:140px;max-width:220px;object-fit:cover;
                                       border:1px solid rgba(0,0,0,0.1);border-radius:4px;">
                </div>
            <?php else : ?>
                <img id="image-preview" src="" alt=""
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
                       class="admin-field__input<?= hasError($errors, 'image') ? ' is-error' : '' ?>"
                       onchange="previewImage(this)">
                <?php if (hasError($errors, 'image')) : ?>
                    <span class="admin-field__error"><?= htmlspecialchars($errors['image']) ?></span>
                <?php endif; ?>
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
    const preview = document.getElementById('image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = ''; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
