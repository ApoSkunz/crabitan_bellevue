/**
 * Jeu La Cave aux Secrets — logique côté client.
 * Flash de départ 3 s : mémorisez les cartes avant qu'elles se retournent.
 * Mauvaise paire : les cartes clignotent rouge et −5 s de pénalité.
 * Bonne paire : animation pulse dorée.
 */

const DURATION       = 2 * 60; // secondes
const FLASH_DURATION = 3;       // secondes de flash de départ
const WRONG_PENALTY  = 5;       // secondes retirées sur erreur

export function initMemoGame() {
    const section = document.getElementById('memo-game');
    if (!section) return;

    const PAIR_COUNT = parseInt(section.dataset.pairCount, 10);
    const WIN_MSG    = section.dataset.winMsg;
    const LOSE_MSG   = section.dataset.loseMsg;
    let worldRecord  = parseInt(section.dataset.worldRecord, 10) || 0;

    // Record mondial
    const wrEl = document.createElement('p');
    wrEl.className = 'memo-game__wr';
    section.querySelector('.memo-game__actions').insertAdjacentElement('afterend', wrEl);

    function updateWRDisplay() {
        if (worldRecord > 0) {
            wrEl.textContent = `Record mondial : ${worldRecord} sec restantes`;
            wrEl.hidden = false;
        } else {
            wrEl.hidden = true;
        }
    }
    updateWRDisplay();

    function submitMemoScore(secs) {
        fetch('/api/jeux/score', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ game: 'memo', score: secs }),
        })
            .then((r) => r.json())
            .then((d) => { if (d.record !== undefined) { worldRecord = d.record; updateWRDisplay(); } })
            .catch(() => {});
    }

    let timer, secondsLeft, flipped, locked, matched, timerStarted;

    function pad(n) { return String(n).padStart(2, '0'); }

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
        el.className   = `memo-game__message memo-game__message--${win ? 'win' : 'lose'}`;
        el.hidden      = false;
    }

    function endGame(win) {
        clearInterval(timer);
        locked = true;
        document.getElementById('memo-grid').hidden    = true;
        document.getElementById('memo-restart').hidden = false;
        if (win) submitMemoScore(secondsLeft);
        showMessage(win ? WIN_MSG : LOSE_MSG, win);
    }

    function startTimer() {
        if (timerStarted) return;
        timerStarted = true;
        timer = setInterval(() => {
            secondsLeft--;
            updateTimer();
            if (secondsLeft <= 0) endGame(false);
        }, 1000);
    }

    function flipCard(card) {
        if (locked || card.classList.contains('is-flipped') || card.classList.contains('is-matched')) return;

        startTimer();
        card.classList.add('is-flipped');
        flipped.push(card);

        if (flipped.length < 2) return;

        locked = true;
        const [a, b] = flipped;

        if (a.dataset.slug === b.dataset.slug) {
            // ── Bonne paire : animation pulse ────────────────
            a.classList.add('is-matched', 'is-matched-flash');
            b.classList.add('is-matched', 'is-matched-flash');
            setTimeout(() => {
                a.classList.remove('is-matched-flash');
                b.classList.remove('is-matched-flash');
            }, 500);
            matched++;
            document.getElementById('memo-pairs-found').textContent = matched;
            flipped = [];
            locked  = false;
            if (matched === PAIR_COUNT) endGame(true);
        } else {
            // ── Mauvaise paire : rouge + pénalité −5 s ───────
            a.classList.add('is-wrong');
            b.classList.add('is-wrong');
            secondsLeft = Math.max(0, secondsLeft - WRONG_PENALTY);
            updateTimer();
            setTimeout(() => {
                a.classList.remove('is-flipped', 'is-wrong');
                b.classList.remove('is-flipped', 'is-wrong');
                flipped = [];
                locked  = false;
                if (secondsLeft <= 0) endGame(false);
            }, 950);
        }
    }

    // ── Flash de départ : toutes les cartes visibles N secondes ──
    function startFlash(onDone) {
        const cards = document.querySelectorAll('#memo-grid .memo-card');
        cards.forEach((c) => c.classList.add('is-flipped'));
        locked = true;

        const statusEl    = document.getElementById('memo-pairs-found').closest('.memo-game__status');
        const flashLabel  = document.createElement('span');
        flashLabel.id     = 'memo-flash-label';
        flashLabel.style.cssText = 'margin-left:.75rem;color:var(--color-gold);font-style:italic;';
        let countdown = FLASH_DURATION;
        flashLabel.textContent = ` — Mémorisez ! ${countdown}…`;
        statusEl.appendChild(flashLabel);

        const iv = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                clearInterval(iv);
                flashLabel.remove();
                cards.forEach((c) => c.classList.remove('is-flipped'));
                locked = false;
                onDone();
            } else {
                flashLabel.textContent = ` — Mémorisez ! ${countdown}…`;
            }
        }, 1000);
    }

    function init() {
        clearInterval(timer);
        secondsLeft  = DURATION;
        flipped      = [];
        locked       = false;
        matched      = 0;
        timerStarted = false;

        updateTimer();
        document.getElementById('memo-pairs-found').textContent = 0;

        const msg = document.getElementById('memo-message');
        msg.hidden      = true;
        msg.textContent = '';

        const grid  = document.getElementById('memo-grid');
        grid.querySelectorAll('.memo-card').forEach((c) => c.classList.remove('is-flipped', 'is-matched', 'is-wrong', 'is-matched-flash'));

        // Mélanger les <li>
        const items = Array.from(grid.children);
        for (let i = items.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            grid.appendChild(items[j]);
            items.splice(j, 1);
        }
    }

    function startGame() {
        init();
        document.getElementById('memo-grid').hidden    = false;
        document.getElementById('memo-start').hidden   = true;
        document.getElementById('memo-restart').hidden = true;
        // Flash de départ : cartes visibles 3 s, timer démarre dès qu'elles se retournent
        startFlash(() => { startTimer(); });
    }

    function resetToStart() {
        init();
        document.getElementById('memo-grid').hidden    = true;
        document.getElementById('memo-start').hidden   = false;
        document.getElementById('memo-restart').hidden = true;
    }

    document.getElementById('memo-start').addEventListener('click', startGame);
    document.getElementById('memo-restart').addEventListener('click', resetToStart);
    document.getElementById('memo-grid').addEventListener('click', (e) => {
        const card = e.target.closest('.memo-card');
        if (card) flipCard(card);
    });

    init();
}
