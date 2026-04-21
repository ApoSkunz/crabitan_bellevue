<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('nav.news');
$metaDescription = $navLang === 'en'
    ? 'Latest news from Château Crabitan Bellevue — harvests, events and estate life.'
    : 'Actualités du Château Crabitan Bellevue — vendanges, événements et vie du domaine.';
$appUrl = APP_BASE_URL;
$canonical     = $appUrl . '/' . $navLang . '/actualites';
$hreflangFr    = $appUrl . '/fr/actualites';
$hreflangEn    = $appUrl . '/en/actualites';
$ogTitle       = __('nav.news') . ' — ' . APP_NAME;
$ogDescription = $metaDescription;
$ogImage       = $appUrl . '/assets/images/logo/crabitan-bellevue-logo.png';
$ogUrl         = $canonical;
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
                    $newsDate    = (new \DateTimeImmutable($item['created_at']))->format('d/m/Y');
                    $newsDateIso = (new \DateTimeImmutable($item['created_at']))->format('Y-m-d');
                    $newsSlug  = $item['slug'] ?? '';
                    ?>
                    <article class="news-card">
                        <div class="news-card__body">
                            <time class="news-card__date" datetime="<?= htmlspecialchars($newsDateIso) ?>"><?= htmlspecialchars($newsDate) ?></time>
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

        </div>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
