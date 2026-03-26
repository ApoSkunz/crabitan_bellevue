<?php
$pageTitle = __('jeux.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$pairCount = count($wines ?? []);
?>

<main class="page-jeux" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('jeux.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section
        class="memo-game container"
        id="memo-game"
        aria-labelledby="memo-title"
        data-pair-count="<?= $pairCount ?>"
        data-win-msg="<?= htmlspecialchars(__('jeux.win')) ?>"
        data-lose-msg="<?= htmlspecialchars(__('jeux.lose')) ?>"
    >
        <h2 id="memo-title" class="memo-game__title"><?= htmlspecialchars(__('jeux.memo_title')) ?></h2>
        <p class="memo-game__desc"><?= htmlspecialchars(__('jeux.memo_desc')) ?></p>

        <div class="memo-game__timer" id="memo-timer" aria-live="polite" aria-atomic="true">
            <div class="memo-game__timer-block">
                <span class="memo-game__timer-value" id="memo-hours">00</span>
                <span class="memo-game__timer-label"><?= htmlspecialchars(__('jeux.hours')) ?></span>
            </div>
            <div class="memo-game__timer-sep" aria-hidden="true">:</div>
            <div class="memo-game__timer-block">
                <span class="memo-game__timer-value" id="memo-minutes">02</span>
                <span class="memo-game__timer-label"><?= htmlspecialchars(__('jeux.minutes')) ?></span>
            </div>
            <div class="memo-game__timer-sep" aria-hidden="true">:</div>
            <div class="memo-game__timer-block">
                <span class="memo-game__timer-value" id="memo-seconds">00</span>
                <span class="memo-game__timer-label"><?= htmlspecialchars(__('jeux.seconds')) ?></span>
            </div>
        </div>

        <div class="memo-game__status" aria-live="polite">
            <span id="memo-pairs-found">0</span> / <?= $pairCount ?> <?= htmlspecialchars(__('jeux.pairs_found')) ?>
        </div>

        <ul class="memo-game__grid" id="memo-grid" hidden>
            <?php
            // Dupliquer + mélanger les paires
            $cards = array_merge($wines ?? [], $wines ?? []);
            shuffle($cards);
            foreach ($cards as $i => $wine) :
                ?>
                <li>
                    <button
                        class="memo-card"
                        type="button"
                        data-slug="<?= htmlspecialchars($wine['slug']) ?>"
                        aria-label="Carte <?= $i + 1 ?>"
                    >
                        <span class="memo-card__back" aria-hidden="true"></span>
                        <span class="memo-card__front">
                            <img
                                src="/assets/images/wines/<?= htmlspecialchars($wine['image_path']) ?>"
                                alt="<?= htmlspecialchars($wine['label_name']) ?>"
                                loading="lazy"
                                width="120"
                                height="180"
                            >
                        </span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="memo-game__message" id="memo-message" aria-live="assertive" hidden></div>

        <div class="memo-game__actions">
            <button type="button" class="btn btn--gold" id="memo-start">
                <?= htmlspecialchars(__('jeux.start')) ?>
            </button>
            <button type="button" class="btn btn--gold" id="memo-restart" hidden>
                <?= htmlspecialchars(__('jeux.restart')) ?>
            </button>
        </div>
    </section>
</main>


<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
