<?php
$pageTitle = __('support.title');
$navLang   = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$isBare    = $bare ?? false;

$faqs = [];
for ($i = 1; $i <= 13; $i++) {
    $faqs[] = ['q' => __('support.q' . $i), 'a' => __('support.a' . $i)];
}

if (!$isBare) {
    require_once SRC_PATH . '/View/partials/head.php';
    require_once SRC_PATH . '/View/partials/header.php';
}
?>
<?php if ($isBare) : ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($navLang) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/assets/images/logo/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="bare-legal">
    <div class="bare-legal__bar">
        <span><?= htmlspecialchars($pageTitle) ?></span>
        <button type="button" class="bare-legal__close" onclick="window.close()" aria-label="Fermer">&#10005;</button>
    </div>
    <div class="container">
<?php else : ?>
<main class="page-support" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('support.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>
<?php endif; ?>

    <section class="support-faq container" aria-labelledby="faq-title">
        <h2 id="faq-title" class="support-faq__title"><?= htmlspecialchars(__('support.faq_title')) ?></h2>

        <dl class="faq-accordion" id="faq-accordion">
            <?php foreach ($faqs as $idx => $item) : ?>
                <div class="faq-accordion__item">
                    <dt>
                        <button
                            class="faq-accordion__trigger"
                            type="button"
                            aria-expanded="false"
                            aria-controls="faq-panel-<?= $idx ?>"
                            id="faq-btn-<?= $idx ?>"
                        >
                            <?= htmlspecialchars($item['q']) ?>
                            <span class="faq-accordion__icon" aria-hidden="true">+</span>
                        </button>
                    </dt>
                    <dd
                        class="faq-accordion__panel"
                        id="faq-panel-<?= $idx ?>"
                        role="region"
                        aria-labelledby="faq-btn-<?= $idx ?>"
                        hidden
                    >
                        <p><?= htmlspecialchars($item['a']) ?></p>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>

<?php if ($isBare) : ?>
    </div>
    <script src="/assets/js/main.js"></script>
</body>
</html>
<?php else : ?>
</main>
    <?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
<?php endif; ?>
