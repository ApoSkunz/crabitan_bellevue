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
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                <thead>
                    <tr style="border-bottom:2px solid #e8e0d0;color:#6b5f50;font-weight:600;text-align:left;">
                        <th style="padding:0.5rem 0.75rem 0.5rem 0;">Document</th>
                        <th style="padding:0.5rem 0.75rem;">Version</th>
                        <th style="padding:0.5rem 0.75rem;">Mise à jour</th>
                        <th style="padding:0.5rem 0.75rem;">Notification</th>
                        <th style="padding:0.5rem 0;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $slug => $doc) : ?>
                    <tr style="border-bottom:1px solid #f0ece4;">
                        <td style="padding:0.5rem 0.75rem 0.5rem 0;font-weight:500;">
                            <?= htmlspecialchars($doc['label']) ?>
                            <span style="display:block;font-size:0.75rem;color:#9a8f80;"><?= htmlspecialchars($slug) ?></span>
                        </td>
                        <td style="padding:0.5rem 0.75rem;font-weight:600;color:#8a6a2e;">
                            v<?= htmlspecialchars($doc['version']) ?>
                        </td>
                        <td style="padding:0.5rem 0.75rem;color:#5a5040;">
                            <?= htmlspecialchars(date('d/m/Y', strtotime($doc['updated_at']))) ?>
                        </td>
                        <td style="padding:0.5rem 0.75rem;">
                            <?php if ((int) $doc['requires_notification'] === 1) : ?>
                                <span style="color:#2e7d32;font-weight:600;">Oui</span>
                            <?php else : ?>
                                <span style="color:#9a8f80;">Non</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:0.5rem 0;">
                            <a href="/admin/dpo/pages-legales/<?= rawurlencode($slug) ?>/modifier"
                               style="font-size:0.85rem;color:#8a6a2e;text-decoration:underline;">
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
