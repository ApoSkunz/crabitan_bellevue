/**
 * Jeu MÉMO — logique côté client.
 * Les données de configuration sont lues depuis les data-attributes
 * de l'élément #memo-game pour éviter toute injection PHP dans le JS.
 */

const DURATION = 2 * 60; // secondes

export function initMemoGame() {
    const section = document.getElementById('memo-game');
    if (!section) return;

    const PAIR_COUNT = parseInt(section.dataset.pairCount, 10);
    const WIN_MSG    = section.dataset.winMsg;
    const LOSE_MSG   = section.dataset.loseMsg;
    let worldRecord  = parseInt(section.dataset.worldRecord, 10) || 0;

    // Affichage du record mondial
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
            .then((d) => {
                if (d.record !== undefined) {
                    worldRecord = d.record;
                    updateWRDisplay();
                }
            })
            .catch(() => { /* non-bloquant */ });
    }

    let timer, secondsLeft, flipped, locked, matched, timerStarted;

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
        document.getElementById('memo-grid').hidden = true;
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
            a.classList.add('is-matched');
            b.classList.add('is-matched');
            matched++;
            document.getElementById('memo-pairs-found').textContent = matched;
            flipped = [];
            locked  = false;
            if (matched === PAIR_COUNT) endGame(true);
        } else {
            setTimeout(() => {
                a.classList.remove('is-flipped');
                b.classList.remove('is-flipped');
                flipped = [];
                locked  = false;
            }, 900);
        }
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

        // Réinitialiser l'état des cartes
        const grid  = document.getElementById('memo-grid');
        grid.querySelectorAll('.memo-card').forEach((c) => c.classList.remove('is-flipped', 'is-matched'));

        // Mélanger les <li> (enfants directs de la grille, pas les boutons)
        const items = Array.from(grid.children);
        for (let i = items.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            grid.appendChild(items[j]);
            items.splice(j, 1);
        }
    }

    function startGame() {
        init();
        document.getElementById('memo-grid').hidden = false;
        document.getElementById('memo-start').hidden = true;
        document.getElementById('memo-restart').hidden = true;
    }

    function resetToStart() {
        init();
        document.getElementById('memo-grid').hidden = true;
        document.getElementById('memo-start').hidden = false;
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
