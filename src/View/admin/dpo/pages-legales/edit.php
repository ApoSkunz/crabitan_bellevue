<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array<string, mixed> $document */
?>

<?php if (!empty($flashError)) : ?>
<div class="admin-flash admin-flash--error" role="alert"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Modifier — <?= htmlspecialchars($document['label']) ?></h1>
</div>

<div class="admin-card admin-card--medium">
    <div class="admin-card__body">

        <dl class="admin-dpo-dl">
            <dt class="admin-dpo-dl__label">Slug</dt>
            <dd class="u-font-mono"><?= htmlspecialchars($document['slug']) ?></dd>

            <dt class="admin-dpo-dl__label">Version actuelle</dt>
            <dd class="u-font-semibold admin-text-amber">v<?= htmlspecialchars($document['version']) ?></dd>

            <dt class="admin-dpo-dl__label">Dernière MàJ</dt>
            <dd><?= htmlspecialchars(date('d/m/Y', strtotime($document['updated_at']))) ?></dd>

            <dt class="admin-dpo-dl__label">Notification</dt>
            <dd><?= (int) $document['requires_notification'] === 1
                    ? '<span class="u-font-semibold u-text-success">Oui (email utilisateurs)</span>'
                    : '<span class="admin-muted">Non (document interne)</span>' ?></dd>
        </dl>

        <form method="POST"
              action="/admin/dpo/pages-legales/<?= rawurlencode($document['slug']) ?>/modifier">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="u-mb-4">
                <label for="version" class="u-block u-text-85 u-font-semibold u-mb-1 admin-dpo-label">
                    Nouveau numéro de version <span class="admin-text-required">*</span>
                    <span class="u-font-normal admin-muted">(format MAJEUR.MINEUR — ex. 1.2)</span>
                </label>
                <input type="text" id="version" name="version"
                       value="<?= htmlspecialchars($document['version']) ?>"
                       pattern="^\d+\.\d+(\.\d+)?$" required
                       class="admin-dpo-edit-input">
            </div>

            <div class="u-mb-4">
                <label for="updated_at" class="u-block u-text-85 u-font-semibold u-mb-1 admin-dpo-label">
                    Date de mise à jour <span class="admin-text-required">*</span>
                </label>
                <input type="date" id="updated_at" name="updated_at"
                       value="<?= htmlspecialchars($document['updated_at']) ?>"
                       required
                       class="admin-dpo-edit-input">
            </div>

            <?php if ((int) $document['requires_notification'] === 1) : ?>
            <div class="u-mb-4">
                <label for="change_note" class="u-block u-text-85 u-font-semibold u-mb-1 admin-dpo-label">
                    Résumé des modifications
                    <span class="u-font-normal admin-muted">(optionnel — inclus dans l'email de notification)</span>
                </label>
                <textarea id="change_note" name="change_note" rows="3"
                          placeholder="Ex. : Ajout de la clause relative aux cookies de mesure d'audience…"
                          class="admin-dpo-edit-textarea"></textarea>
            </div>

            <div class="admin-dpo-notif-box">
                <label class="admin-dpo-notif-label">
                    <input type="checkbox" name="send_notification" value="1"
                           class="u-flex-shrink-0">
                    <span>
                        <strong>Envoyer une notification email aux utilisateurs concernés</strong><br>
                        <span class="admin-dpo-notif-hint">
                            Une page de confirmation indiquera le nombre d'utilisateurs qui vont recevoir l'email.
                        </span>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <div class="u-flex u-gap-3 u-items-center">
                <button type="submit" class="btn btn--primary btn--sm">
                    Enregistrer la version
                </button>
                <a href="/admin/dpo/pages-legales" class="admin-dpo-confirm-link">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
