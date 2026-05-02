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

<div class="admin-card admin-card--form">
    <div class="admin-card__body">

        <dl class="admin-dpo-dl">
            <dt class="admin-dpo-dl__label">Document</dt>
            <dd class="u-font-semibold"><?= htmlspecialchars($pending['doc_label']) ?></dd>

            <dt class="admin-dpo-dl__label">Version</dt>
            <dd class="u-font-semibold admin-text-amber">v<?= htmlspecialchars($pending['version']) ?></dd>

            <dt class="admin-dpo-dl__label">Date de MàJ</dt>
            <dd><?= htmlspecialchars(date('d/m/Y', strtotime($pending['updated_at']))) ?></dd>

            <?php if ($pending['change_note'] !== '') : ?>
            <dt class="admin-dpo-dl__label">Résumé</dt>
            <dd class="u-italic"><?= htmlspecialchars($pending['change_note']) ?></dd>
            <?php endif; ?>
        </dl>

        <div class="admin-dpo-success-box">
            <p class="admin-dpo-success-box__text">
                <strong><?= $count ?> utilisateur<?= $count > 1 ? 's' : '' ?></strong>
                <?= $count > 1 ? 'vont' : 'va' ?> recevoir un email de notification.
            </p>
            <?php if ($count === 0) : ?>
            <p class="admin-dpo-success-box__note">
                Aucun utilisateur éligible trouvé. L'envoi ne produira aucun email.
            </p>
            <?php endif; ?>
        </div>

        <form method="POST"
              action="/admin/dpo/pages-legales/<?= rawurlencode($pending['slug']) ?>/confirmer-notification">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="u-flex u-gap-3 u-items-center">
                <button type="submit" class="btn btn--primary btn--sm"
                        <?= $count === 0 ? 'disabled' : '' ?>>
                    Confirmer l'envoi
                </button>
                <a href="/admin/dpo/pages-legales" class="admin-dpo-confirm-link">
                    Annuler (ne pas notifier)
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
