<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/**
 * @var array<string, mixed> $pending  Données de la notification en attente (slug, version, doc_label…)
 * @var int                  $count    Nombre d'utilisateurs éligibles
 * @var string               $csrfToken
 */
?>

<div class="admin-page-header">
    <h1>Confirmer la notification — <?= htmlspecialchars($pending['doc_label']) ?></h1>
</div>

<div class="admin-card" style="max-width:560px;">
    <div class="admin-card__body">

        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.35rem 1.5rem;
                   font-size:0.875rem;margin-bottom:1.5rem;">
            <dt style="color:#8a7a60;">Document</dt>
            <dd style="font-weight:600;"><?= htmlspecialchars($pending['doc_label']) ?></dd>

            <dt style="color:#8a7a60;">Version</dt>
            <dd style="color:#8a6a2e;font-weight:600;">v<?= htmlspecialchars($pending['version']) ?></dd>

            <dt style="color:#8a7a60;">Date de MàJ</dt>
            <dd><?= htmlspecialchars(date('d/m/Y', strtotime($pending['updated_at']))) ?></dd>

            <?php if ($pending['change_note'] !== '') : ?>
            <dt style="color:#8a7a60;">Résumé</dt>
            <dd style="font-style:italic;"><?= htmlspecialchars($pending['change_note']) ?></dd>
            <?php endif; ?>
        </dl>

        <div style="padding:1rem 1.25rem;margin-bottom:1.5rem;
                    background:#e8f5e9;border-left:4px solid #2e7d32;border-radius:0 4px 4px 0;">
            <p style="margin:0;font-size:1rem;color:#1b5e20;">
                <strong><?= $count ?> utilisateur<?= $count > 1 ? 's' : '' ?></strong>
                <?= $count > 1 ? 'vont' : 'va' ?> recevoir un email de notification.
            </p>
            <?php if ($count === 0) : ?>
            <p style="margin:0.5rem 0 0;font-size:0.85rem;color:#4a7a50;">
                Aucun utilisateur éligible trouvé. L'envoi ne produira aucun email.
            </p>
            <?php endif; ?>
        </div>

        <form method="POST"
              action="/admin/dpo/pages-legales/<?= rawurlencode($pending['slug']) ?>/confirmer-notification">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div style="display:flex;gap:0.75rem;align-items:center;">
                <button type="submit" class="btn btn--primary btn--sm"
                        <?= $count === 0 ? 'disabled' : '' ?>>
                    Confirmer l'envoi
                </button>
                <a href="/admin/dpo/pages-legales"
                   style="font-size:0.85rem;color:#8a7a60;text-decoration:underline;">
                    Annuler (ne pas notifier)
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
