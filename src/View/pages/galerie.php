<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('gallery.title');
$metaDescription = $navLang === 'en'
    ? 'Photo gallery of Château Crabitan Bellevue — vineyard, cellar and estate life in pictures.'
    : 'Galerie photos du Château Crabitan Bellevue — vignoble, chai et vie du domaine en images.';
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

/** @var array<int, array{src: string, alt: string}> $photos */
$photos = $photos ?? [];
$isEn   = isset($lang) && $lang === 'en';
?>

<main class="page-gallery" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('gallery.title')) ?></h1>
            <p class="page-hero__intro"><?= htmlspecialchars(__('gallery.intro')) ?></p>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <div class="gallery-toolbar container">
        <div class="gallery-toolbar__views" role="group"
             aria-label="<?= $isEn ? 'View mode' : 'Mode d\'affichage' ?>">
            <button type="button" id="gallery-view-mosaic"
                    class="gallery-toolbar__btn gallery-toolbar__btn--active"
                    aria-pressed="true"
                    title="<?= $isEn ? 'Mosaic' : 'Mosaïque' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                    <rect x="1" y="1" width="7" height="7" rx="1" fill="currentColor"/>
                    <rect x="10" y="1" width="7" height="7" rx="1" fill="currentColor"/>
                    <rect x="1" y="10" width="7" height="7" rx="1" fill="currentColor"/>
                    <rect x="10" y="10" width="7" height="7" rx="1" fill="currentColor"/>
                </svg>
                <?= $isEn ? 'Mosaic' : 'Mosaïque' ?>
            </button>
            <button type="button" id="gallery-view-linear"
                    class="gallery-toolbar__btn"
                    aria-pressed="false"
                    title="<?= $isEn ? 'Linear' : 'Linéaire' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                    <rect x="1" y="2" width="16" height="4" rx="1" fill="currentColor"/>
                    <rect x="1" y="8" width="16" height="4" rx="1" fill="currentColor"/>
                    <rect x="1" y="14" width="16" height="4" rx="1" fill="currentColor"/>
                </svg>
                <?= $isEn ? 'Linear' : 'Linéaire' ?>
            </button>
        </div>
    </div>

    <section id="gallery-container" class="gallery-grid container"
             aria-label="<?= htmlspecialchars(__('gallery.title')) ?>">
        <?php foreach ($photos as $item) : ?>
            <button type="button"
                    class="gallery-item js-gallery-open"
                    data-src="<?= htmlspecialchars($item['src'], ENT_QUOTES) ?>"
                    data-alt="<?= htmlspecialchars($item['alt'], ENT_QUOTES) ?>"
                    aria-label="<?= htmlspecialchars(__('gallery.enlarge') . ' : ' . $item['alt'], ENT_QUOTES) ?>">
                <img src="<?= htmlspecialchars($item['src'], ENT_QUOTES) ?>"
                     alt="<?= htmlspecialchars($item['alt'], ENT_QUOTES) ?>"
                     loading="lazy"
                     class="gallery-item__img">
            </button>
        <?php endforeach; ?>
    </section>
</main>

<!-- Lightbox -->
<div id="gallery-lightbox" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(__('gallery.lightbox_label')) ?>"
     style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,0.92);
            align-items:center;justify-content:center;">
    <button type="button" id="gallery-lightbox-close"
            aria-label="<?= htmlspecialchars(__('gallery.close')) ?>"
            style="position:absolute;top:1rem;right:1.5rem;background:none;border:none;
                   color:#fff;font-size:2rem;cursor:pointer;line-height:1;">✕</button>
    <button type="button" id="gallery-lightbox-prev"
            aria-label="<?= $isEn ? 'Previous' : 'Précédente' ?>"
            style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);
                   background:none;border:none;color:#fff;font-size:2.5rem;cursor:pointer;">‹</button>
    <img id="gallery-lightbox-img" src="" alt=""
         style="max-width:90vw;max-height:88vh;object-fit:contain;border-radius:4px;">
    <button type="button" id="gallery-lightbox-next"
            aria-label="<?= $isEn ? 'Next' : 'Suivante' ?>"
            style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);
                   background:none;border:none;color:#fff;font-size:2.5rem;cursor:pointer;">›</button>
</div>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
(function () {
    var photos = <?= json_encode(array_values($photos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var current = 0;
    var lb  = document.getElementById('gallery-lightbox');
    var img = document.getElementById('gallery-lightbox-img');
    var container = document.getElementById('gallery-container');
    var btnMosaic = document.getElementById('gallery-view-mosaic');
    var btnLinear = document.getElementById('gallery-view-linear');

    // ---- Vue toggle ------------------------------------------------

    function setView(mode) {
        if (mode === 'linear') {
            container.classList.add('gallery-grid--linear');
            btnMosaic.classList.remove('gallery-toolbar__btn--active');
            btnLinear.classList.add('gallery-toolbar__btn--active');
            btnMosaic.setAttribute('aria-pressed', 'false');
            btnLinear.setAttribute('aria-pressed', 'true');
        } else {
            container.classList.remove('gallery-grid--linear');
            btnLinear.classList.remove('gallery-toolbar__btn--active');
            btnMosaic.classList.add('gallery-toolbar__btn--active');
            btnLinear.setAttribute('aria-pressed', 'false');
            btnMosaic.setAttribute('aria-pressed', 'true');
        }
    }

    btnMosaic.addEventListener('click', function () { setView('mosaic'); });
    btnLinear.addEventListener('click', function () { setView('linear'); });

    // ---- Lightbox --------------------------------------------------

    function open(idx) {
        current = idx;
        img.src = photos[idx].src;
        img.alt = photos[idx].alt;
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lb.style.display = 'none';
        document.body.style.overflow = '';
    }

    function nav(dir) {
        current = (current + dir + photos.length) % photos.length;
        img.src = photos[current].src;
        img.alt = photos[current].alt;
    }

    document.querySelectorAll('.js-gallery-open').forEach(function (btn, idx) {
        btn.addEventListener('click', function () { open(idx); });
    });

    document.getElementById('gallery-lightbox-close').addEventListener('click', close);
    document.getElementById('gallery-lightbox-prev').addEventListener('click', function () { nav(-1); });
    document.getElementById('gallery-lightbox-next').addEventListener('click', function () { nav(1); });

    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });

    document.addEventListener('keydown', function (e) {
        if (lb.style.display === 'none') return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') nav(-1);
        if (e.key === 'ArrowRight') nav(1);
    });
}());
</script>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
