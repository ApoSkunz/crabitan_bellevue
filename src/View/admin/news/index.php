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
    <h1>Actualités <small class="u-text-2xs admin-subcount-alt">(<?= $total ?>)</small></h1>
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
                <tr><td colspan="6" class="text-center u-text-soft u-p-8">Aucun article</td></tr>
            <?php else : ?>
                <?php foreach ($articles as $article) : ?>
                    <?php
                    $titleData = json_decode($article['title'] ?? '{}', true) ?? [];
                    $titleFr   = $titleData['fr'] ?? '—';
                    ?>
                    <tr>
                        <td data-label="ID" class="u-text-soft"><?= (int) $article['id'] ?></td>
                        <td data-label="Titre"><strong><?= htmlspecialchars($titleFr) ?></strong></td>
                        <td data-label="Slug" class="u-text-78 u-text-soft"><?= htmlspecialchars($article['slug']) ?></td>
                        <td data-label="Image">
                            <?php if (!empty($article['image_path'])) : ?>
                                <img src="/assets/images/news/<?= htmlspecialchars($article['image_path']) ?>"
                                     alt="" class="admin-news-thumb">
                            <?php else : ?>
                                <span class="u-text-soft u-text-2xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Date" class="u-nowrap u-text-sm">
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
