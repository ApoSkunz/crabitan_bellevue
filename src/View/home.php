<?php
$pageTitle = null; // Pas de titre dans <title> sur la home (château seul suffit)
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';


$carouselSlides = [
    [
        'image' => '/assets/images/carousel/vignoble-ete.jpg',
        'alt'   => __('home.carousel_alt') . ' — été',
    ],
    [
        'image' => '/assets/images/carousel/vignoble-automne.jpg',
        'alt'   => __('home.carousel_alt') . ' — automne',
    ],
    [
        'image' => '/assets/images/carousel/raisins-recolte.jpg',
        'alt'   => __('home.carousel_alt') . ' — récolte',
    ],
    [
        'image' => '/assets/images/carousel/vignoble-allee.jpg',
        'alt'   => __('home.carousel_alt') . ' — allée',
    ],
    [
        'image' => '/assets/images/carousel/vignoble-hiver.jpg',
        'alt'   => __('home.carousel_alt') . ' — hiver',
    ],
];
?>

<main class="home-page" id="main-content">

    <!-- ============================================================ -->
    <!-- HERO CAROUSEL                                                 -->
    <!-- ============================================================ -->
    <section class="hero-carousel" aria-label="<?= htmlspecialchars(__('home.carousel_title')) ?>">
        <div class="carousel" id="hero-carousel">
            <div class="carousel__track" aria-live="polite">
                <?php foreach ($carouselSlides as $i => $slide) : ?>
                    <div
                        class="carousel__slide<?= $i === 0 ? ' is-active' : '' ?>"
                        aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
                        role="img" <?php // NOSONAR — background-image CSS, <img> impossible ici ?>
                        aria-label="<?= htmlspecialchars($slide['alt']) ?>"
                        style="background-image:url('<?= htmlspecialchars($slide['image']) ?>')"
                    ></div>
                <?php endforeach; ?>
            </div>

            <div id="weather-widget" class="carousel__weather" aria-label="Météo locale" hidden></div>
            <a href="https://open-meteo.com/" class="carousel__weather-credit" target="_blank" rel="noopener noreferrer">Weather data by Open-Meteo.com</a>

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
                aria-label="Diapositive précédente"
            >&#8249;</button>
            <button
                class="carousel__btn carousel__btn--next"
                id="carousel-next"
                type="button"
                aria-label="Diapositive suivante"
            >&#8250;</button>

            <div class="carousel__dots" role="tablist" aria-label="Diapositives">
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
    <!-- SECTION MILLÉSIME — Nos vins                                 -->
    <!-- ============================================================ -->
    <section class="home-section home-section--wines" id="nos-vins">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <img
                    src="/assets/images/gallery/nos-vins.jpg"
                    alt="<?= htmlspecialchars(__('home.img_wines_alt')) ?>"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
                <img
                    src="/assets/images/gallery/vendanges-cheval.jpg"
                    alt="<?= htmlspecialchars(__('home.img_harvest_alt')) ?>"
                    loading="lazy"
                    width="600"
                    height="400"
                >
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- SECTION SAVOIR-FAIRE                                         -->
    <!-- ============================================================ -->
    <section class="home-section home-section--savoir" id="savoir-faire">
        <div class="home-section__inner container">
            <div class="home-section__visual">
                <img
                    src="/assets/images/gallery/chai-barriques.jpg"
                    alt="<?= htmlspecialchars(__('home.img_cellar_alt')) ?>"
                    loading="lazy"
                    width="600"
                    height="400"
                >
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
                <!-- NOSONAR Web:S4084 — sous-titres non disponibles pour cette vidéo de présentation -->
                <video
                    class="home-video__element"
                    controls
                    preload="metadata"
                    poster="/assets/images/gallery/proprietaire.jpeg"
                    aria-label="<?= htmlspecialchars(__('home.video_title')) ?>"
                >
                    <source src="/assets/videos/chateau-crabitan-bellevue-1.mp4" type="video/mp4">
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
