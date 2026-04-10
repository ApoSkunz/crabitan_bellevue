<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array<string, mixed>              $document */
/** @var array<int, array<string, mixed>>  $history  */
?>

<div class="admin-page-header">
    <h1>Historique — <?= htmlspecialchars($document['label']) ?></h1>
    <a href="/admin/dpo/pages-legales" class="admin-btn admin-btn--secondary">← Retour</a>
</div>

<div class="admin-card">
    <div class="admin-card__body">
        <?php if (empty($history)) : ?>
            <p style="color:#9a8f80;font-style:italic;">Aucune version archivée pour ce document.</p>
        <?php else : ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                <thead>
                    <tr style="border-bottom:2px solid #e8e0d0;color:#6b5f50;font-weight:600;text-align:left;">
                        <th style="padding:0.5rem 0.75rem 0.5rem 0;">Version</th>
                        <th style="padding:0.5rem 0.75rem;">Date effective</th>
                        <th style="padding:0.5rem 0.75rem;">Archivé le</th>
                        <th style="padding:0.5rem 0;">Modifié par</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $entry) : ?>
                    <tr style="border-bottom:1px solid #f0ece4;">
                        <td style="padding:0.5rem 0.75rem 0.5rem 0;font-weight:600;color:#8a6a2e;">
                            v<?= htmlspecialchars($entry['version']) ?>
                        </td>
                        <td style="padding:0.5rem 0.75rem;color:#5a5040;">
                            <?= htmlspecialchars(date('d/m/Y', strtotime($entry['updated_at']))) ?>
                        </td>
                        <td style="padding:0.5rem 0.75rem;color:#9a8f80;font-size:0.82rem;">
                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($entry['created_at']))) ?>
                        </td>
                        <td style="padding:0.5rem 0;color:#5a5040;">
                            <?= htmlspecialchars($entry['updated_by_name'] ?? '—') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
