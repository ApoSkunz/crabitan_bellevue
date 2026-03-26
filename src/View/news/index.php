<?php
$pageTitle = __('nav.news');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-news" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('home.news_tag')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('nav.news')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="news-list" aria-label="<?= htmlspecialchars(__('nav.news')) ?>">
        <div class="container">
            <div class="home-news__grid">
                <?php foreach ($news as $item) :
                    $titleData = json_decode($item['title'], true) ?? [];
                    $introData = json_decode($item['text_content'], true) ?? [];
                    $newsTitle = $titleData[$navLang] ?? ($titleData['fr'] ?? '');
                    $newsIntro = $introData[$navLang] ?? ($introData['fr'] ?? '');
                    $newsDate  = (new \DateTimeImmutable($item['created_at']))->format('d/m/Y');
                    $newsSlug  = $item['slug'] ?? '';
                    ?>
                    <article class="news-card">
                        <div class="news-card__body">
                            <time class="news-card__date"><?= htmlspecialchars($newsDate) ?></time>
                            <h2 class="news-card__title"><?= htmlspecialchars($newsTitle) ?></h2>
                            <p class="news-card__intro"><?= htmlspecialchars($newsIntro) ?></p>
                        </div>
                        <div class="news-card__footer">
                            <a
                                href="/<?= htmlspecialchars($navLang) ?>/actualites<?= $newsSlug !== '' ? '/' . htmlspecialchars($newsSlug) : '' ?>"
                                class="news-card__link"
                                aria-label="<?= htmlspecialchars(__('news.read_more') . ' : ' . $newsTitle) ?>"
                            ><?= htmlspecialchars(__('news.read_more')) ?> &#8594;</a>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($news)) : ?>
                    <p class="news-list__empty"><?= htmlspecialchars(__('news.empty')) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1) :
                $buildUrl = static function (int $p) use ($navLang): string {
                    $qs = array_filter(['page' => $p > 1 ? (string) $p : '']);
                    $query = http_build_query($qs);
                    return '/' . $navLang . '/actualites' . ($query !== '' ? '?' . $query : '');
                };
            ?>
                <nav class="wines-pagination" aria-label="Pagination">
                    <?php if ($page > 1) : ?>
                        <a href="<?= htmlspecialchars($buildUrl($page - 1)) ?>" class="wines-pagination__btn" rel="prev">&larr;</a>
                    <?php else : ?>
                        <span class="wines-pagination__btn wines-pagination__btn--disabled">&larr;</span>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
                        <?php if (abs($p - $page) <= 2 || $p === 1 || $p === $totalPages) : ?>
                            <?php if (abs($p - $page) === 3) : ?>
                                <span class="wines-pagination__ellipsis">&hellip;</span>
                            <?php endif; ?>
                            <a
                                href="<?= htmlspecialchars($buildUrl($p)) ?>"
                                class="wines-pagination__btn<?= $p === $page ? ' wines-pagination__btn--active' : '' ?>"
                                <?= $p === $page ? 'aria-current="page"' : '' ?>
                            ><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages) : ?>
                        <a href="<?= htmlspecialchars($buildUrl($page + 1)) ?>" class="wines-pagination__btn" rel="next">&rarr;</a>
                    <?php else : ?>
                        <span class="wines-pagination__btn wines-pagination__btn--disabled">&rarr;</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
