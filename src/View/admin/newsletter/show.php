<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array<string, mixed> $campaign */
$attachments = $campaign['attachments'] ?? [];
?>

<div class="admin-page-header">
    <h1><?= htmlspecialchars($campaign['subject']) ?></h1>
</div>

<div class="admin-card" style="margin-bottom:1.5rem;">
    <div class="admin-card__body">
        <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.4rem 1.5rem;font-size:0.9rem;">
            <dt style="color:#8a7a60;">Date d'envoi</dt>
            <dd><?= date('d/m/Y à H:i', strtotime($campaign['sent_at'])) ?></dd>

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
                         alt="Image newsletter"
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
