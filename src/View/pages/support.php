<?php
$pageTitle = __('support.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$faqs = [];
for ($i = 1; $i <= 11; $i++) {
    $faqs[] = ['q' => __('support.q' . $i), 'a' => __('support.a' . $i)];
}
?>

<main class="page-support" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('support.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

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
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
