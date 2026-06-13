<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array<string, mixed>                $campaign */
/** @var array<int, array<string, mixed>>    $linkStats */
$attachments       = $campaign['attachments'] ?? [];
$sentCount         = (int) ($campaign['sent_count']          ?? 0);
$opensCount        = (int) ($campaign['opens_count']         ?? 0);
$clicksCount       = (int) ($campaign['clicks_count']        ?? 0);
$uniqueClicksCount = (int) ($campaign['unique_clicks_count'] ?? 0);
$unsubscribesCount = (int) ($campaign['unsubscribes_count']  ?? 0);
$openRate          = $sentCount > 0 ? round($opensCount  / $sentCount * 100, 1) : 0.0;
$clickRate         = $sentCount > 0 ? round($uniqueClicksCount / $sentCount * 100, 1) : 0.0;
$hasLinks          = !empty($linkStats);
?>

<div class="admin-page-header">
    <h1><?= htmlspecialchars($campaign['subject']) ?></h1>
</div>

<div class="admin-card u-mb-6">
    <div class="admin-card__body">
        <dl class="admin-nl-dl">
            <?php if (!empty($campaign['title'])) : ?>
                <dt class="admin-nl-dl__label">Titre</dt>
                <dd><?= htmlspecialchars((string) $campaign['title']) ?></dd>
            <?php endif; ?>

            <dt class="admin-nl-dl__label">Date d'envoi</dt>
            <dd><?= date('d/m/Y à H:i', strtotime($campaign['sent_at'])) ?></dd>

            <dt class="admin-nl-dl__label">Segment</dt>
            <dd><?= $campaign['segment_label'] !== null
                    ? htmlspecialchars((string) $campaign['segment_label'])
                    : 'Tous les abonnés' ?></dd>

            <dt class="admin-nl-dl__label">Envoyés</dt>
            <dd class="u-font-semibold admin-text-success"><?= (int) $campaign['sent_count'] ?></dd>

            <dt class="admin-nl-dl__label">Échecs</dt>
            <dd class="u-font-semibold <?= (int) $campaign['failed_count'] > 0 ? 'u-text-error-dk' : 'admin-muted' ?>">
                <?= (int) $campaign['failed_count'] ?>
            </dd>

            <?php if (!empty($campaign['image_url'])) : ?>
                <dt class="admin-nl-dl__label">Image</dt>
                <dd>
                    <img src="<?= htmlspecialchars($campaign['image_url']) ?>"
                         alt="Aperçu de la campagne"
                         class="admin-nl-preview-img">
                </dd>
            <?php endif; ?>

            <?php if (!empty($attachments)) : ?>
                <dt class="admin-nl-dl__label">Pièce(s) jointe(s)</dt>
                <dd>
                    <?php foreach ($attachments as $att) : ?>
                        <a href="/admin/newsletter/<?= (int) $campaign['id'] ?>/attachment/<?= (int) $att['id'] ?>"
                           class="u-text-85">
                            <?= htmlspecialchars($att['original_name']) ?>
                        </a><br>
                    <?php endforeach; ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<div class="admin-card u-mb-6">
    <div class="admin-card__body">
        <h2 class="admin-nl-section-title">
            Statistiques de tracking
        </h2>
        <dl class="admin-nl-dl u-mb-5">
            <dt class="admin-nl-dl__label">Ouvertures</dt>
            <dd><?= $opensCount ?> / <?= $sentCount ?>
                &nbsp;&middot;&nbsp;
                <span class="u-font-semibold"><?= $openRate ?>%</span>
            </dd>

            <?php if ($hasLinks) : ?>
            <dt class="admin-nl-dl__label">Clics uniques</dt>
            <dd><?= $uniqueClicksCount ?> / <?= $sentCount ?>
                &nbsp;&middot;&nbsp;
                <span class="u-font-semibold"><?= $clickRate ?>%</span>
            </dd>

            <dt class="admin-nl-dl__label">Clics totaux</dt>
            <dd><?= $clicksCount ?></dd>
            <?php endif; ?>

            <dt class="admin-nl-dl__label">Désinscriptions</dt>
            <dd><?= $unsubscribesCount ?></dd>
        </dl>

        <?php if (!empty($linkStats)) : ?>
        <h3 class="admin-nl-subsection-title">
            Détail par lien
        </h3>
        <div class="u-overflow-x-auto">
            <table class="admin-nl-link-table">
                <thead>
                    <tr class="admin-nl-link-table__head-row">
                        <th class="admin-nl-link-table__th admin-nl-link-table__th--url">URL</th>
                        <th class="admin-nl-link-table__th admin-nl-link-table__th--right">Clics totaux</th>
                        <th class="admin-nl-link-table__th admin-nl-link-table__th--right-last">Clics uniques</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linkStats as $row) : ?>
                    <tr class="admin-nl-link-table__row">
                        <td class="admin-nl-link-table__td admin-nl-link-table__td--url">
                            <a href="<?= htmlspecialchars($row['url'], ENT_QUOTES) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="admin-nl-link-table__link">
                                <?= htmlspecialchars($row['url'], ENT_QUOTES) ?>
                            </a>
                        </td>
                        <td class="admin-nl-link-table__td admin-nl-link-table__td--right u-font-semibold">
                            <?= (int) $row['total_clicks'] ?>
                        </td>
                        <td class="admin-nl-link-table__td admin-nl-link-table__td--right-last admin-nl-link-muted">
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
        <h2 class="admin-nl-section-title">
            Corps de la newsletter
        </h2>
        <div class="admin-nl-body-wrap">
            <?= nl2br(htmlspecialchars($campaign['body'])) ?>
        </div>
    </div>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
