<?php
$exitIntentVariant = 'newsletter';
$pageTitle = null; // Pas de titre dans <title> sur la home (château seul suffit)
$brandName       = 'Château Crabitan Bellevue';
$appUrl = APP_BASE_URL;
$metaDescription = $navLang === 'en'
    ? "{$brandName} — Sainte-Croix-du-Mont AOC wines. Discover our estate and shop online."
    : "{$brandName} — Vins de Sainte-Croix-du-Mont AOC. Découvrez notre domaine et achetez en ligne.";
$canonical   = $appUrl . '/' . $navLang;
$hreflangFr  = $appUrl . '/fr';
$hreflangEn  = $appUrl . '/en';
$ogTitle     = $brandName;
$ogDescription = $metaDescription;
$ogUrl       = $canonical;
$ogType      = 'website';
$ogImage     = $appUrl . '/assets/images/carousel/vignoble-ete-1600w.jpg';
$jsonLd      = json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type' => 'Organization',
            'name'  => $brandName,
            'url'   => $appUrl,
            'logo'  => $appUrl . '/assets/images/logo/crabitan-bellevue-logo.png',
        ],
        [
            '@type'       => ['Winery', 'LocalBusiness'],
            'name'        => $brandName,
            '@id'         => $appUrl,
            'url'         => $appUrl,
            'description' => $metaDescription,
            'telephone'   => '+33556620153',
            'hasMap'      => 'https://maps.google.com/maps?q=Château+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Crabitan',
                'addressLocality' => 'Sainte-Croix-du-Mont',
                'postalCode'      => '33410',
                'addressCountry'  => 'FR',
            ],
            'geo' => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => 44.5764,
                'longitude' => -0.3139,
            ],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
$preloadCarousel = true;
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';


$carouselSlides = [
    ['base' => 'vignoble-ete',    'suffix' => 'été'],
    ['base' => 'vignoble-automne','suffix' => 'automne'],
    ['base' => 'raisins-recolte', 'suffix' => 'récolte'],
    ['base' => 'vignoble-allee',  'suffix' => 'allée'],
    ['base' => 'vignoble-hiver',  'suffix' => 'hiver'],
];
?>

<main class="home-page" id="main-content">

    <!-- ============================================================ -->
    <!-- HERO CAROUSEL                                                 -->
    <!-- ============================================================ -->
    <section class="hero-carousel" aria-label="<?= htmlspecialchars(__('home.carousel_title')) ?>">
        <div class="carousel" id="hero-carousel">
            <div class="carousel__track" aria-live="polite">
                <?php foreach ($carouselSlides as $i => $slide) :
                    $alt  = htmlspecialchars(__('home.carousel_alt') . ' — ' . $slide['suffix']);
                    $base = '/assets/images/carousel/' . $slide['base'];
                    ?>
                    <div
                        class="carousel__slide<?= $i === 0 ? ' is-active' : '' ?>"
                        aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
                    >
                        <picture>
                            <source
                                type="image/webp"
                                srcset="<?= $base ?>-800w.webp 800w, <?= $base ?>-1600w.webp 1600w"
                                sizes="100vw"
                            >
                            <img
                                src="<?= $base ?>-1600w.jpg"
                                srcset="<?= $base ?>-800w.jpg 800w, <?= $base ?>-1600w.jpg 1600w"
                                sizes="100vw"
                                alt="<?= $alt ?>"
                                class="carousel__slide-img"
                                <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                                width="1600"
                                height="1067"
                            >
                        </picture>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="weather-widget" class="carousel__weather" aria-label="Météo locale" hidden></div>

            <div class="carousel__caption">
                <p class="carousel__eyebrow">Sainte-Croix-du-Mont</p>
                <h1 class="carousel__title"><?= htmlspecialchars(__('home.carousel_title')) ?></h1>
                <p class="carousel__subtitle"><?= htmlspecialchars(__('home.carousel_sub')) ?></p>
                <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="btn btn--gold carousel__cta">
                    <?= htmlspecialchars(__('home.wines_cta')) ?>
                </a>
            </div>

            <button
                class="carousel__btn carousel__btn--prev"
                id="carousel-prev"
                type="button"
                aria-label="<?= htmlspecialchars(__('home.carousel_prev')) ?>"
            >&#8249;</button>
            <button
                class="carousel__btn carousel__btn--next"
                id="carousel-next"
                type="button"
                aria-label="<?= htmlspecialchars(__('home.carousel_next')) ?>"
            >&#8250;</button>

            <div class="carousel__dots" role="tablist" aria-label="<?= htmlspecialchars(__('home.carousel_dots')) ?>">
                <?php foreach ($carouselSlides as $i => $slide) : ?>
                    <button
                        class="carousel__dot<?= $i === 0 ? ' is-active' : '' ?>"
                        type="button"
                        role="tab"
                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                        aria-label="Diapositive <?= $i + 1 ?>"
                        data-slide="<?= $i ?>"
                    ></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION VIN DU MOMENT                                       -->
    <!-- ============================================================ -->
    <?php if (!empty($featuredWine)) : ?>
    <section class="home-section home-section--featured home-section--dark" id="vin-du-moment">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <?php
                $fwFile  = (string) ($featuredWine['image_path'] ?? '');
                $fwNoExt = (string) preg_replace('/\.[^.]+$/', '', $fwFile);
                $fwExt   = pathinfo($fwFile, PATHINFO_EXTENSION);
                $fwBase  = '/assets/images/wines/';
                ?>
                <picture>
                    <source
                        srcset="<?= $fwBase . rawurlencode($fwNoExt . '-400w.webp') ?> 400w, <?= $fwBase . rawurlencode($fwNoExt . '-800w.webp') ?> 800w"
                        sizes="(max-width: 640px) 400px, 400px"
                        type="image/webp">
                    <img
                        src="<?= $fwBase . rawurlencode($fwFile) ?>"
                        srcset="<?= $fwBase . rawurlencode($fwNoExt . '-400w.' . $fwExt) ?> 400w, <?= $fwBase . rawurlencode($fwNoExt . '-800w.' . $fwExt) ?> 800w"
                        sizes="(max-width: 640px) 400px, 400px"
                        alt="<?= htmlspecialchars($featuredWine['label_name']) ?>"
                        loading="lazy"
                        width="400"
                        height="600"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.featured_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.featured_title')) ?></h2>
                <div class="home-section__divider"></div>
                <?php if ($featuredWine['is_cuvee_speciale']) : ?>
                    <span class="home-featured__badge"><?= htmlspecialchars(__('home.featured_badge')) ?></span>
                <?php endif; ?>
                <p class="home-featured__name">
                    <?php
                        $certLine = !empty($featuredWine['certification_label'])
                            ? htmlspecialchars($featuredWine['certification_label']) . ' — ' . htmlspecialchars($featuredWine['label_name'])
                            : htmlspecialchars($featuredWine['label_name']);
                    ?>
                    <?= $certLine ?>
                </p>
                <p class="home-featured__meta">
                    <?= htmlspecialchars(__('home.featured_vintage')) ?> <?= (int) $featuredWine['vintage'] ?>
                    &nbsp;&mdash;&nbsp;
                    <?= number_format((float) $featuredWine['price'], 2, ',', '&nbsp;') ?>&nbsp;€
                </p>
                <div class="home-featured__actions">
                    <a href="/<?= htmlspecialchars($navLang) ?>/vins/<?= htmlspecialchars($featuredWine['slug']) ?>"
                       class="btn btn--gold">
                        <?= htmlspecialchars(__('home.featured_cta')) ?>
                    </a>
                    <?php if ($featuredWine['available']) : ?>
                    <button
                        type="button"
                        class="btn btn--gold js-add-to-cart"
                        data-wine-id="<?= (int) $featuredWine['id'] ?>"
                        data-wine-name="<?= htmlspecialchars($featuredWine['label_name'] . ' ' . $featuredWine['vintage']) ?>"
                        data-wine-price="<?= htmlspecialchars(number_format((float) $featuredWine['price'], 2, ',', ' ') . ' €') ?>"
                        data-wine-image="/assets/images/wines/<?= rawurlencode($featuredWine['image_path']) ?>"
                        data-wine-cuvee="<?= !empty($featuredWine['is_cuvee_speciale']) ? htmlspecialchars(__('wine.cuvee_speciale')) : '' ?>"
                    >
                        <svg width="14" height="14" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 1.5h2l1.5 7.5h7l1.5-5H4.5"/><circle cx="6.5" cy="12.5" r="1" fill="currentColor" stroke="none"/><circle cx="11.5" cy="12.5" r="1" fill="currentColor" stroke="none"/></svg>
                        <?= htmlspecialchars(__('home.featured_cart_cta')) ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- SECTION MILLÉSIME — Nos vins                                 -->
    <!-- ============================================================ -->
    <section class="home-section home-section--wines" id="nos-vins">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <picture>
                    <source srcset="/assets/images/gallery/apercu-vins-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/apercu-vins-600w.jpg"
                        alt="<?= htmlspecialchars(__('home.img_wines_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.wines_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.wines_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('home.wines_text')) ?></p>
                <a href="/<?= htmlspecialchars($navLang) ?>/vins" class="btn btn--gold">
                    <?= htmlspecialchars(__('home.wines_cta')) ?>
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION NOTRE HISTOIRE                                        -->
    <!-- ============================================================ -->
    <section class="home-section home-section--history home-section--dark" id="histoire">
        <div class="home-section__inner container home-section__inner--reverse">
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.history_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.history_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('home.history_text')) ?></p>
                <a href="/<?= htmlspecialchars($navLang) ?>/le-chateau" class="btn btn--gold">
                    <?= htmlspecialchars(__('home.history_cta')) ?>
                </a>
            </div>
            <div class="home-section__visual home-section__visual--vintage">
                <picture>
                    <source srcset="/assets/images/gallery/vendanges-cheval-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/vendanges-cheval-600w.jpg"
                        alt="<?= htmlspecialchars(__('home.img_harvest_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION SAVOIR-FAIRE                                         -->
    <!-- ============================================================ -->
    <section class="home-section home-section--savoir" id="savoir-faire">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <picture>
                    <source srcset="/assets/images/gallery/chai-barriques-600w.webp" type="image/webp">
                    <img
                        src="/assets/images/gallery/chai-barriques-600w.jpg"
                        alt="<?= htmlspecialchars(__('home.img_cellar_alt')) ?>"
                        loading="lazy"
                        width="600"
                        height="400"
                    >
                </picture>
            </div>
            <div class="home-section__content">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.savoir_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.savoir_title')) ?></h2>
                <div class="home-section__divider"></div>
                <p class="home-section__text"><?= htmlspecialchars(__('home.savoir_text')) ?></p>
                <a href="/<?= htmlspecialchars($navLang) ?>/savoir-faire" class="btn btn--gold">
                    <?= htmlspecialchars(__('home.savoir_cta')) ?>
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION VIDÉO — Le propriétaire                             -->
    <!-- ============================================================ -->
    <section class="home-video home-section--dark" id="video-domaine">
        <div class="container">
            <div class="home-video__header">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.video_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.video_title')) ?></h2>
                <div class="home-section__divider home-section__divider--center"></div>
            </div>

            <div class="home-video__player">
                <?php
                // Déposer le fichier vidéo dans /assets/videos/domaine.mp4
                // (format MP4 H.264 recommandé, et optionnellement .webm pour Firefox)
                ?>
                <video
                    class="home-video__element"
                    controls
                    preload="metadata"
                    poster="/assets/images/gallery/proprietaire.jpeg"
                    aria-label="<?= htmlspecialchars(__('home.video_title')) ?>"
                >
                    <source src="/assets/videos/chateau-crabitan-bellevue-1.webm" type="video/webm">
                    <source src="/assets/videos/chateau-crabitan-bellevue-1.mp4" type="video/mp4">
                    <track kind="captions" src="" label="<?= htmlspecialchars(__('home.video_captions_label')) ?>" default>
                </video>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION ACTUALITÉS                                           -->
    <!-- ============================================================ -->
    <section class="home-news" id="actualites">
        <div class="container">
            <div class="home-news__header">
                <span class="home-section__tag"><?= htmlspecialchars(__('home.news_tag')) ?></span>
                <h2 class="home-section__title"><?= htmlspecialchars(__('home.news_title')) ?></h2>
                <div class="home-section__divider home-section__divider--center"></div>
            </div>

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
                            <h3 class="news-card__title"><?= htmlspecialchars($newsTitle) ?></h3>
                            <p class="news-card__intro"><?= htmlspecialchars($newsIntro) ?></p>
                        </div>
                        <div class="news-card__footer">
                            <a
                                href="/<?= htmlspecialchars($navLang) ?>/actualites<?= $newsSlug !== '' ? '/' . htmlspecialchars($newsSlug) : '' ?>"
                                class="news-card__link"
                            >Lire la suite &#8594;<span class="sr-only"> : <?= htmlspecialchars($newsTitle) ?></span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($news)) : ?>
                    <p class="home-news__empty"><!-- Aucune actualité disponible pour le moment --></p>
                <?php endif; ?>
            </div>

            <div class="home-news__cta">
                <a href="/<?= htmlspecialchars($navLang) ?>/actualites" class="btn btn--gold">
                    <?= htmlspecialchars(__('home.news_cta')) ?>
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION TÉMOIGNAGES                                          -->
    <!-- ============================================================ -->
    <?php if (!empty($reviews)) : ?>
    <section class="home-testimonials" aria-label="<?= htmlspecialchars(__('home.testimonials_title')) ?>">
        <div class="container">
            <span class="home-section__tag home-section__tag--center"><?= htmlspecialchars(__('home.testimonials_tag')) ?></span>
            <h2 class="home-section__title text-center"><?= htmlspecialchars(__('home.testimonials_title')) ?></h2>
            <div class="home-section__divider home-section__divider--center"></div>
            <?php if (!empty($reviewStats)) : ?>
            <p class="home-testimonials__avg">
                <span class="home-testimonials__avg-score"><?= number_format((float) $reviewStats['avg'], 1) ?> / 5</span>
                <span class="home-testimonials__avg-stars" aria-hidden="true"><?= str_repeat('★', (int) round((float) $reviewStats['avg'])) . str_repeat('☆', 5 - (int) round((float) $reviewStats['avg'])) ?></span>
                <span class="home-testimonials__avg-count">(<?= (int) $reviewStats['count'] ?> <?= htmlspecialchars(__('home.testimonials_reviews')) ?>)</span>
            </p>
            <?php endif; ?>
            <div class="home-testimonials__grid">
                <?php foreach ($reviews as $review) :
                    $stars = str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']);
                    ?>
                <blockquote class="home-testimonials__card">
                    <div class="home-testimonials__stars" aria-label="<?= (int) $review['rating'] ?>/5" aria-hidden="true"><?= $stars ?></div>
                    <p class="home-testimonials__quote"><?= htmlspecialchars((string) $review['comment']) ?></p>
                    <footer class="home-testimonials__author">
                        <strong><?= htmlspecialchars((string) $review['display_name']) ?></strong>
                        <?php if (!empty($review['location'])) : ?>
                        <span class="home-testimonials__location"><?= htmlspecialchars((string) $review['location']) ?></span>
                        <?php endif; ?>
                    </footer>
                </blockquote>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- SECTION LOCALISATION                                         -->
    <!-- ============================================================ -->
    <section class="home-location home-section--dark" id="localisation">
        <div class="container">
            <h2 class="home-section__title text-center">
                <?= htmlspecialchars(__('home.location_title')) ?>
            </h2>
            <div class="home-section__divider home-section__divider--center"></div>

            <div class="home-location__inner">
                <div class="home-location__map">
                    <?php
                    // Remplacer l'attribut src par votre URL Google Maps Embed
                    // Exemple : https://www.google.com/maps/embed?pb=...
                    // Obtenir l'URL depuis Google Maps > Partager > Intégrer une carte
                    ?>
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d362595.44420850335!2d-0.5876012943361956!3d44.76496434602363!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd556ceb75abc90d%3A0x56c6cb9d5cb560f4!2sCh%C3%A2teau%20Crabitan%20Bellevue!5e0!3m2!1sfr!2sfr!4v1586246850971!5m2!1sfr!2sfr"
                        width="100%"
                        height="400"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Localisation du Château Crabitan Bellevue"
                    ></iframe>
                </div>

                <div class="home-location__info">
                    <div class="home-location__address">
                        <p class="home-location__name"><?= htmlspecialchars(APP_NAME) ?></p>
                        <p><?= htmlspecialchars(__('home.location_address')) ?></p>
                        <p>France</p>
                    </div>

                    <div class="home-location__contact">
                        <h3 class="home-location__contact-title">
                            <?= htmlspecialchars(__('home.location_contact')) ?>
                        </h3>
                        <p>
                            <?php $phoneRaw = preg_replace('/\s/', '', __('home.location_phone')) ?? ''; ?>
                        <a href="tel:<?= htmlspecialchars($phoneRaw) ?>">
                                <?= htmlspecialchars(__('home.location_phone')) ?>
                            </a>
                        </p>
                        <a href="/<?= htmlspecialchars($navLang) ?>/contact" class="btn btn--gold">
                            <?= htmlspecialchars(__('home.location_cta')) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
