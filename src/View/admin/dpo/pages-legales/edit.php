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

<div class="admin-card" style="max-width:600px;">
    <div class="admin-card__body">

        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.35rem 1.5rem;
                   font-size:0.875rem;margin-bottom:1.5rem;">
            <dt style="color:#8a7a60;">Slug</dt>
            <dd style="font-family:monospace;"><?= htmlspecialchars($document['slug']) ?></dd>

            <dt style="color:#8a7a60;">Version actuelle</dt>
            <dd style="font-weight:600;color:#8a6a2e;">v<?= htmlspecialchars($document['version']) ?></dd>

            <dt style="color:#8a7a60;">Dernière MàJ</dt>
            <dd><?= htmlspecialchars(date('d/m/Y', strtotime($document['updated_at']))) ?></dd>

            <dt style="color:#8a7a60;">Notification</dt>
            <dd><?= (int) $document['requires_notification'] === 1
                    ? '<span style="color:#2e7d32;font-weight:600;">Oui (email utilisateurs)</span>'
                    : '<span style="color:#9a8f80;">Non (document interne)</span>' ?></dd>
        </dl>

        <form method="POST"
              action="/admin/dpo/pages-legales/<?= rawurlencode($document['slug']) ?>/modifier">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div style="margin-bottom:1rem;">
                <label for="version" style="display:block;font-size:0.85rem;color:#6b5f50;
                                            font-weight:600;margin-bottom:0.35rem;">
                    Nouveau numéro de version <span style="color:#c62828;">*</span>
                    <span style="font-weight:400;color:#9a8f80;">(format MAJEUR.MINEUR — ex. 1.2)</span>
                </label>
                <input type="text" id="version" name="version"
                       value="<?= htmlspecialchars($document['version']) ?>"
                       pattern="^\d+\.\d+$" required
                       style="width:100%;padding:0.5rem 0.75rem;border:1px solid #d4c9b0;
                              border-radius:4px;font-size:0.9rem;">
            </div>

            <div style="margin-bottom:1rem;">
                <label for="updated_at" style="display:block;font-size:0.85rem;color:#6b5f50;
                                               font-weight:600;margin-bottom:0.35rem;">
                    Date de mise à jour <span style="color:#c62828;">*</span>
                </label>
                <input type="date" id="updated_at" name="updated_at"
                       value="<?= htmlspecialchars($document['updated_at']) ?>"
                       required
                       style="width:100%;padding:0.5rem 0.75rem;border:1px solid #d4c9b0;
                              border-radius:4px;font-size:0.9rem;">
            </div>

            <?php if ((int) $document['requires_notification'] === 1) : ?>
            <div style="margin-bottom:1rem;">
                <label for="change_note" style="display:block;font-size:0.85rem;color:#6b5f50;
                                                font-weight:600;margin-bottom:0.35rem;">
                    Résumé des modifications
                    <span style="font-weight:400;color:#9a8f80;">(optionnel — inclus dans l'email de notification)</span>
                </label>
                <textarea id="change_note" name="change_note" rows="3"
                          placeholder="Ex. : Ajout de la clause relative aux cookies de mesure d'audience…"
                          style="width:100%;padding:0.5rem 0.75rem;border:1px solid #d4c9b0;
                                 border-radius:4px;font-size:0.9rem;resize:vertical;"></textarea>
            </div>

            <div style="margin-bottom:1.5rem;padding:0.75rem 1rem;background:#f5f0e8;
                        border-left:3px solid #c1a14b;border-radius:0 4px 4px 0;">
                <label style="display:flex;align-items:flex-start;gap:0.6rem;cursor:pointer;font-size:0.875rem;color:#5a4030;">
                    <input type="checkbox" name="send_notification" value="1"
                           style="margin-top:2px;flex-shrink:0;">
                    <span>
                        <strong>Envoyer une notification email aux utilisateurs concernés</strong><br>
                        <span style="color:#8a7a60;font-size:0.8rem;">
                            Une page de confirmation indiquera le nombre d'utilisateurs qui vont recevoir l'email.
                        </span>
                    </span>
                </label>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:0.75rem;align-items:center;">
                <button type="submit" class="btn btn--primary btn--sm">
                    Enregistrer la version
                </button>
                <a href="/admin/dpo/pages-legales"
                   style="font-size:0.85rem;color:#8a7a60;text-decoration:underline;">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
