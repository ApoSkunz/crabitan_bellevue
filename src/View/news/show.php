<?php
$navLang   = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$titleData = json_decode($item['title'], true) ?? [];
$bodyData  = json_decode($item['text_content'], true) ?? [];
$newsTitle = $titleData[$navLang] ?? ($titleData['fr'] ?? '');
$newsBody  = $bodyData[$navLang] ?? ($bodyData['fr'] ?? '');
$newsDate  = (new \DateTimeImmutable($item['created_at']))->format('d/m/Y');
$newsImage = $item['image_path'] ?? '';

$pageTitle = $newsTitle;
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-news-show" id="main-content">
    <article class="news-article container">
        <header class="news-article__header">
            <a href="/<?= htmlspecialchars($navLang) ?>/actualites" class="news-article__back">
                &#8592; <?= htmlspecialchars(__('news.back')) ?>
            </a>
            <time class="news-card__date news-card__date--lg"><?= htmlspecialchars($newsDate) ?></time>
            <h1 class="home-section__title"><?= htmlspecialchars($newsTitle) ?></h1>
            <div class="home-section__divider"></div>
        </header>

        <div class="news-article__content<?= $newsImage !== '' ? ' news-article__content--with-image' : '' ?>">
            <?php if ($newsImage !== '') : ?>
                <figure class="news-article__image">
                    <img
                        src="/assets/images/news/<?= htmlspecialchars($newsImage) ?>"
                        alt="<?= htmlspecialchars($newsTitle) ?>"
                        loading="eager"
                    >
                </figure>
            <?php endif; ?>

            <div class="news-article__body">
                <?= nl2br(htmlspecialchars($newsBody)) ?>
            </div>

            <?php if (!empty($item['link_path'])) : ?>
                <div class="news-article__external-link">
                    <a href="<?= htmlspecialchars($item['link_path']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn--gold btn--sm">
                        <?= htmlspecialchars(__('news.learn_more')) ?> &#8594;
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <nav class="news-article__nav" aria-label="<?= htmlspecialchars(__('news.nav_label')) ?>">
            <?php if ($prev !== null) :
                $prevTitle = json_decode($prev['title'], true)[$navLang] ?? (json_decode($prev['title'], true)['fr'] ?? '');
            ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/actualites/<?= htmlspecialchars($prev['slug']) ?>" class="news-article__nav-btn news-article__nav-btn--prev">
                    <span class="news-article__nav-arrow">&larr;</span>
                    <span class="news-article__nav-label"><?= htmlspecialchars($prevTitle) ?></span>
                </a>
            <?php else : ?>
                <span class="news-article__nav-btn news-article__nav-btn--disabled"></span>
            <?php endif; ?>

            <?php if ($next !== null) :
                $nextTitle = json_decode($next['title'], true)[$navLang] ?? (json_decode($next['title'], true)['fr'] ?? '');
            ?>
                <a href="/<?= htmlspecialchars($navLang) ?>/actualites/<?= htmlspecialchars($next['slug']) ?>" class="news-article__nav-btn news-article__nav-btn--next">
                    <span class="news-article__nav-label"><?= htmlspecialchars($nextTitle) ?></span>
                    <span class="news-article__nav-arrow">&rarr;</span>
                </a>
            <?php else : ?>
                <span class="news-article__nav-btn news-article__nav-btn--disabled"></span>
            <?php endif; ?>
        </nav>
    </article>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
