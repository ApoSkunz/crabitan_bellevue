<?php
$navLang   = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$titleData = json_decode($item['title'], true) ?? [];
$bodyData  = json_decode($item['text_content'], true) ?? [];
$newsTitle = $titleData[$navLang] ?? ($titleData['fr'] ?? '');
$newsBody  = $bodyData[$navLang] ?? ($bodyData['fr'] ?? '');
$newsDate  = (new \DateTimeImmutable($item['created_at']))->format('d/m/Y');

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
            <time class="news-card__date"><?= htmlspecialchars($newsDate) ?></time>
            <h1 class="home-section__title"><?= htmlspecialchars($newsTitle) ?></h1>
            <div class="home-section__divider"></div>
        </header>

        <div class="news-article__body">
            <?= nl2br(htmlspecialchars($newsBody)) ?>
        </div>
    </article>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
