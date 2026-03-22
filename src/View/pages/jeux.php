<?php
$pageTitle = __('jeux.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
$navLang = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');

// 14 paires — images des vins du catalogue (slug utilisé comme identifiant)
$memoPairs = [
    'bordeaux-blanc-sec-2023',
    'sainte-croix-du-mont-blanc-doux-2021',
    'sainte-croix-du-mont-blanc-doux-2019',
    'sainte-croix-du-mont-blanc-doux-2018',
    'sainte-croix-du-mont-blanc-doux-2017',
    'bordeaux-rouge-2020',
    'bordeaux-rouge-2019',
    'bordeaux-rouge-2018',
    'bordeaux-rouge-2017',
    'bordeaux-rouge-2016',
    'bordeaux-rose-2023',
    'bordeaux-rouge-2015',
    'bordeaux-blanc-sec-2022',
    'sainte-croix-du-mont-blanc-doux-2016',
];
?>

<main class="page-jeux" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('jeux.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="memo-game container" aria-labelledby="memo-title">
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
            <span id="memo-pairs-found">0</span> / 14 <?= htmlspecialchars(__('jeux.pairs_found')) ?>
        </div>

        <div class="memo-game__grid" id="memo-grid" role="list">
            <?php
            // Dupliquer + mélanger les paires
            $cards = array_merge($memoPairs, $memoPairs);
            shuffle($cards);
            foreach ($cards as $i => $slug) :
                ?>
                <button
                    class="memo-card"
                    type="button"
                    data-slug="<?= htmlspecialchars($slug) ?>"
                    aria-label="Carte <?= $i + 1 ?>"
                    role="listitem"
                >
                    <span class="memo-card__back" aria-hidden="true"></span>
                    <span class="memo-card__front">
                        <img
                            src="/assets/images/wines/<?= htmlspecialchars($slug) ?>.png"
                            alt=""
                            loading="lazy"
                            width="120"
                            height="180"
                        >
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="memo-game__message" id="memo-message" aria-live="assertive" hidden></div>

        <div class="memo-game__actions">
            <button
                type="button"
                class="btn btn--gold"
                id="memo-restart"
            >
                <?= htmlspecialchars(__('jeux.restart')) ?>
            </button>
        </div>
    </section>
</main>

<script>
(function () {
    'use strict';

    const DURATION = 2 * 60; // 2 minutes en secondes
    const WIN_MSG  = <?= json_encode(__('jeux.win')) ?>;
    const LOSE_MSG = <?= json_encode(__('jeux.lose')) ?>;

    let timer, secondsLeft, flipped, locked, matched;

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function updateTimer() {
        const h = Math.floor(secondsLeft / 3600);
        const m = Math.floor((secondsLeft % 3600) / 60);
        const s = secondsLeft % 60;
        document.getElementById('memo-hours').textContent   = pad(h);
        document.getElementById('memo-minutes').textContent = pad(m);
        document.getElementById('memo-seconds').textContent = pad(s);
    }

    function showMessage(msg, win) {
        const el = document.getElementById('memo-message');
        el.textContent = msg;
        el.className   = 'memo-game__message memo-game__message--' + (win ? 'win' : 'lose');
        el.hidden      = false;
    }

    function endGame(win) {
        clearInterval(timer);
        locked = true;
        showMessage(win ? WIN_MSG : LOSE_MSG, win);
    }

    function flipCard(card) {
        if (locked || card.classList.contains('is-flipped') || card.classList.contains('is-matched')) return;

        card.classList.add('is-flipped');
        flipped.push(card);

        if (flipped.length < 2) return;

        locked = true;
        const [a, b] = flipped;

        if (a.dataset.slug === b.dataset.slug) {
            a.classList.add('is-matched');
            b.classList.add('is-matched');
            matched++;
            document.getElementById('memo-pairs-found').textContent = matched;
            flipped = [];
            locked  = false;
            if (matched === 14) endGame(true);
        } else {
            setTimeout(function () {
                a.classList.remove('is-flipped');
                b.classList.remove('is-flipped');
                flipped = [];
                locked  = false;
            }, 900);
        }
    }

    function init() {
        clearInterval(timer);
        secondsLeft = DURATION;
        flipped     = [];
        locked      = false;
        matched     = 0;

        updateTimer();

        document.getElementById('memo-pairs-found').textContent = 0;

        const msg = document.getElementById('memo-message');
        msg.hidden    = true;
        msg.textContent = '';

        // Mélanger les cartes dans le DOM
        const grid  = document.getElementById('memo-grid');
        const cards = Array.from(grid.querySelectorAll('.memo-card'));
        cards.forEach(function (c) {
            c.classList.remove('is-flipped', 'is-matched');
        });
        // Shuffle DOM order
        for (let i = cards.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            grid.appendChild(cards[j]);
            cards.splice(j, 1);
        }

        timer = setInterval(function () {
            secondsLeft--;
            updateTimer();
            if (secondsLeft <= 0) endGame(false);
        }, 1000);
    }

    document.getElementById('memo-restart').addEventListener('click', init);

    document.getElementById('memo-grid').addEventListener('click', function (e) {
        const card = e.target.closest('.memo-card');
        if (card) flipCard(card);
    });

    init();
}());
</script>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
