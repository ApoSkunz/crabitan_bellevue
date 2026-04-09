<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array<string, mixed>                $campaign */
/** @var array<int, array<string, mixed>>    $linkStats */
$attachments       = $campaign['attachments'] ?? [];
$sentCount         = (int) ($campaign['sent_count']          ?? 0);
$opensCount        = (int) ($campaign['opens_count']         ?? 0);
$clicksCount       = (int) ($campaign['clicks_count']        ?? 0);
$uniqueClicksCount = (int) ($campaign['unique_clicks_count'] ?? 0);
$openRate          = $sentCount > 0 ? round($opensCount  / $sentCount * 100, 1) : 0.0;
$clickRate         = $sentCount > 0 ? round($uniqueClicksCount / $sentCount * 100, 1) : 0.0;
?>

<div class="admin-page-header">
    <h1><?= htmlspecialchars($campaign['subject']) ?></h1>
</div>

<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__body">
        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.4rem 1.5rem;font-size:0.9rem;">
            <dt style="color:#8a7a60;">Date d'envoi</dt>
            <dd><?= date('d/m/Y à H:i', strtotime($campaign['sent_at'])) ?></dd>

            <dt style="color:#8a7a60;">Segment</dt>
            <dd><?= $campaign['segment_label'] !== null
                    ? htmlspecialchars((string) $campaign['segment_label'])
                    : 'Tous les abonnés' ?></dd>

            <dt style="color:#8a7a60;">Envoyés</dt>
            <dd style="color:#2e7d32;font-weight:600;"><?= (int) $campaign['sent_count'] ?></dd>

            <dt style="color:#8a7a60;">Échecs</dt>
            <dd style="color:<?= (int) $campaign['failed_count'] > 0 ? '#c62828' : '#8a7a60' ?>;font-weight:600;">
                <?= (int) $campaign['failed_count'] ?>
            </dd>

            <?php if (!empty($campaign['image_url'])) : ?>
                <dt style="color:#8a7a60;">Image</dt>
                <dd>
                    <img src="<?= htmlspecialchars($campaign['image_url']) ?>"
                         alt="Aperçu de la campagne"
                         style="max-height:100px;max-width:260px;object-fit:cover;
                                border:1px solid rgba(0,0,0,0.1);border-radius:4px;">
                </dd>
            <?php endif; ?>

            <?php if (!empty($attachments)) : ?>
                <dt style="color:#8a7a60;">Pièce(s) jointe(s)</dt>
                <dd>
                    <?php foreach ($attachments as $att) : ?>
                        <a href="/admin/newsletter/<?= (int) $campaign['id'] ?>/attachment/<?= (int) $att['id'] ?>"
                           style="font-size:0.85rem;">
                            <?= htmlspecialchars($att['original_name']) ?>
                        </a><br>
                    <?php endforeach; ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__body">
        <h2 style="font-size:0.85rem;letter-spacing:0.12em;text-transform:uppercase;color:#6b5f50;margin-bottom:1rem;">
            Statistiques de tracking
        </h2>
        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.4rem 1.5rem;font-size:0.9rem;margin-bottom:1.25rem;">
            <dt style="color:#8a7a60;">Ouvertures</dt>
            <dd><?= $opensCount ?> / <?= $sentCount ?>
                &nbsp;&middot;&nbsp;
                <span style="font-weight:600;"><?= $openRate ?>%</span>
            </dd>

            <dt style="color:#8a7a60;">Clics uniques</dt>
            <dd><?= $uniqueClicksCount ?> / <?= $sentCount ?>
                &nbsp;&middot;&nbsp;
                <span style="font-weight:600;"><?= $clickRate ?>%</span>
            </dd>

            <dt style="color:#8a7a60;">Clics totaux</dt>
            <dd><?= $clicksCount ?></dd>
        </dl>

        <?php if (!empty($linkStats)) : ?>
        <h3 style="font-size:0.8rem;letter-spacing:0.1em;text-transform:uppercase;color:#6b5f50;margin-bottom:0.6rem;">
            Détail par lien
        </h3>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="border-bottom:1px solid #e8e0d0;color:#6b5f50;font-weight:600;">
                        <th style="text-align:left;padding:0.35rem 0.75rem 0.35rem 0;">URL</th>
                        <th style="text-align:right;padding:0.35rem 0.75rem;">Clics totaux</th>
                        <th style="text-align:right;padding:0.35rem 0;">Clics uniques</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linkStats as $row) : ?>
                    <tr style="border-bottom:1px solid #f0ece4;">
                        <td style="padding:0.4rem 0.75rem 0.4rem 0;word-break:break-all;">
                            <a href="<?= htmlspecialchars($row['url'], ENT_QUOTES) ?>"
                               target="_blank" rel="noopener noreferrer"
                               style="color:#8a6a2e;font-size:0.8rem;">
                                <?= htmlspecialchars($row['url'], ENT_QUOTES) ?>
                            </a>
                        </td>
                        <td style="text-align:right;padding:0.4rem 0.75rem;font-weight:600;">
                            <?= (int) $row['total_clicks'] ?>
                        </td>
                        <td style="text-align:right;padding:0.4rem 0;color:#5a5040;">
                            <?= (int) $row['unique_clicks'] ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card__body">
        <h2 style="font-size:0.85rem;letter-spacing:0.12em;text-transform:uppercase;color:#6b5f50;margin-bottom:1rem;">
            Corps de la newsletter
        </h2>
        <div style="background:#faf8f4;border:1px solid #e8e0d0;border-radius:4px;padding:1.25rem;
                    font-size:0.9rem;line-height:1.65;color:#3d3425;white-space:pre-wrap;">
            <?= nl2br(htmlspecialchars($campaign['body'])) ?>
        </div>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
