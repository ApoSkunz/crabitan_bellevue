<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array<string, array<string, mixed>> $documents */
$labelRequires = [1 => 'Oui', 0 => 'Non'];
?>

<?php if (!empty($flash)) : ?>
<div class="admin-flash admin-flash--success" role="alert"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if (!empty($flashError)) : ?>
<div class="admin-flash admin-flash--error" role="alert"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Documents légaux — Versioning</h1>
</div>

<div class="admin-card">
    <div class="admin-card__body">
        <div class="admin-table-wrap">
            <table class="admin-legal-table">
                <thead>
                    <tr class="admin-legal-table__head-row">
                        <th class="admin-legal-table__th admin-legal-table__th--first">Document</th>
                        <th class="admin-legal-table__th">Version</th>
                        <th class="admin-legal-table__th">Mise à jour</th>
                        <th class="admin-legal-table__th">Notification</th>
                        <th class="admin-legal-table__th admin-legal-table__th--last">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $slug => $doc) : ?>
                    <tr class="admin-legal-table__row">
                        <td class="admin-legal-table__td admin-legal-table__td--first u-font-medium">
                            <?= htmlspecialchars($doc['label']) ?>
                            <span class="admin-legal-table__slug"><?= htmlspecialchars($slug) ?></span>
                        </td>
                        <td class="admin-legal-table__td admin-legal-table__td--version">
                            v<?= htmlspecialchars($doc['version']) ?>
                        </td>
                        <td class="admin-legal-table__td admin-legal-table__td--date">
                            <?= htmlspecialchars(date('d/m/Y', strtotime($doc['updated_at']))) ?>
                        </td>
                        <td class="admin-legal-table__td">
                            <?php if ((int) $doc['requires_notification'] === 1) : ?>
                                <span class="admin-legal-table__notif-yes">Oui</span>
                            <?php else : ?>
                                <span class="admin-legal-table__notif-no">Non</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-legal-table__td admin-legal-table__td--last">
                            <a href="/admin/dpo/pages-legales/<?= rawurlencode($slug) ?>/modifier"
                               class="admin-legal-table__link">
                                Modifier
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
