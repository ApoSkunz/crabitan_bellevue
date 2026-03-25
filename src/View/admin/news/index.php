<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
?>

<?php if ($flash) : ?>
    <div class="admin-flash admin-flash--success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>
<?php if ($flashError) : ?>
    <div class="admin-flash admin-flash--error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <h1>Actualités <small style="font-size:0.75rem;font-variant:normal;letter-spacing:0;color:#8a7a60;">(<?= $total ?>)</small></h1>
    <a href="/admin/actualites/ajouter" class="admin-btn admin-btn--gold">+ Ajouter un article</a>
</div>

<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titre (FR)</th>
                    <th>Slug</th>
                    <th>Image</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($articles)) : ?>
                <tr><td colspan="6" style="text-align:center;color:#8a7a60;padding:2rem;">Aucun article</td></tr>
            <?php else : ?>
                <?php foreach ($articles as $article) : ?>
                    <?php
                    $titleData = json_decode($article['title'] ?? '{}', true) ?? [];
                    $titleFr   = $titleData['fr'] ?? '—';
                    ?>
                    <tr>
                        <td style="color:#8a7a60;"><?= (int) $article['id'] ?></td>
                        <td><strong><?= htmlspecialchars($titleFr) ?></strong></td>
                        <td style="font-size:0.78rem;color:#8a7a60;"><?= htmlspecialchars($article['slug']) ?></td>
                        <td>
                            <?php if (!empty($article['image_path'])) : ?>
                                <img src="/assets/images/news/<?= htmlspecialchars($article['image_path']) ?>"
                                     alt="" style="height:36px;width:54px;object-fit:cover;border-radius:3px;">
                            <?php else : ?>
                                <span style="color:#8a7a60;font-size:0.75rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;font-size:0.8rem;">
                            <?= date('d/m/Y', strtotime($article['created_at'])) ?>
                        </td>
                        <td>
                            <div class="admin-actions">
                                <a href="/admin/actualites/<?= (int) $article['id'] ?>/modifier"
                                   class="admin-btn admin-btn--outline admin-btn--sm">Modifier</a>
                                <a href="/fr/actualites/<?= htmlspecialchars($article['slug']) ?>"
                                   class="admin-btn admin-btn--outline admin-btn--sm"
                                   target="_blank" rel="noopener">↗</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-pagination">
            <a href="?page=<?= max(1, $page - 1) ?>"
               class="admin-pagination__item<?= $page <= 1 ? ' disabled' : '' ?>">‹</a>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) : ?>
                <a href="?page=<?= $i ?>"
                   class="admin-pagination__item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?page=<?= min($totalPages, $page + 1) ?>"
               class="admin-pagination__item<?= $page >= $totalPages ? ' disabled' : '' ?>">›</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
