/**
 * Jeu Vendange Express.
 * Des grappes et bouteilles tombent des vignes. Attrape-les avec ta caisse !
 * ← → pour déplacer. 3 vies. Score = grappes ×10, bouteilles ×30, grappe dorée ×50.
 */

export function initVendangeExpressGame() {
    const section = document.getElementById('vendange-express-game');
    if (!section) return;
    const canvas = /** @type {HTMLCanvasElement} */ (document.getElementById('vendange-express-canvas'));
    if (!canvas) return;
    if (canvas.dataset.initialized) return;
    canvas.dataset.initialized = 'true';

    const ctx = canvas.getContext('2d');
    const H   = 280;

    function resizeCanvas() {
        canvas.width  = Math.min(section.clientWidth, 900);
        canvas.height = H;
    }
    resizeCanvas();
    window.addEventListener('resize', () => { resizeCanvas(); if (state !== 'running') draw(); });

    // ── Palette ──────────────────────────────────────────────
    const C = {
        sky:       '#D8EEF0',
        skyFar:    '#EAF4D8',
        ground:    '#8AB840',
        groundDk:  '#5A8820',
        vine:      '#2A6A10',
        leaf:      '#4A9A28',
        leafHi:    '#6AB840',
        grape:     '#7A2A9C',
        grapeHi:   '#B060CC',
        bottle:    '#2A5A28',
        bottleLbl: '#C9A96E',
        bottleCap: '#C83018',
        golden:    '#E0A820',
        goldenHi:  '#F8D040',
        crate:     '#A06828',
        crateDk:   '#704010',
        crateLt:   '#C88838',
        wheel:     '#3A2010',
        gold:      '#C9A96E',
        goldDk:    '#A07820',
        txt:       '#3A2A1A',
        overlay:   'rgba(42,28,14,0.62)',
        lifeRed:   '#E03020',
    };

    // ── Layout ───────────────────────────────────────────────
    const N_COLS     = 4;
    const VINE_Y     = 55;       // hauteur de la zone vigne en haut
    const GROUND_Y   = H - 30;
    const CRATE_W    = 78;
    const CRATE_H    = 30;
    const CRATE_Y    = GROUND_Y - CRATE_H;
    const PAUSE_BTN  = { x: 8, y: 8, w: 30, h: 24 };

    function colX(col) {
        return Math.round(canvas.width / (N_COLS + 1) * (col + 1));
    }

    // ── État ─────────────────────────────────────────────────
    let state       = 'idle';
    let crateX      = 0;   // centre du panier
    let moveLeft    = false;
    let moveRight   = false;
    let items       = [];  // { x, y, type, speed }
    let particles   = [];  // { x, y, text, life, color }
    let score       = 0;
    let lives       = 10;
    let hiScore     = 0;
    let worldRecord = parseInt(canvas.dataset.worldRecord, 10) || 0;
    let frame          = 0;
    let baseSpeed      = 2.4;
    let shakeLives     = 0;
    let wasWorldRecord = false;
    let animId;             // eslint-disable-line no-unused-vars

    const VALUES = { grape: 10, bottle: 30, golden: 50 };

    function reset() {
        crateX = canvas.width / 2;
        items  = []; particles = [];
        score  = 0; lives = 10; frame = 0;
        baseSpeed = 2.4;
        shakeLives = 0; wasWorldRecord = false;
        moveLeft = false; moveRight = false;
    }

    function spawnItem() {
        const col  = Math.floor(Math.random() * N_COLS);
        const rand = Math.random();
        const type = rand < 0.62 ? 'grape' : rand < 0.88 ? 'bottle' : 'golden';
        items.push({ x: colX(col), y: -22, type, speed: baseSpeed + Math.random() * 0.6 });
    }

    // ── Input ─────────────────────────────────────────────────
    function startGame() {
        if (state === 'idle' || state === 'dead') { reset(); state = 'running'; canvas.focus(); }
        else if (state === 'paused') state = 'running';
    }

    function handlePointer(clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const cx = (clientX - rect.left) * (canvas.width / rect.width);
        const cy = (clientY - rect.top)  * (canvas.height / rect.height);
        if ((state === 'running' || state === 'paused')
            && cx >= PAUSE_BTN.x && cx <= PAUSE_BTN.x + PAUSE_BTN.w
            && cy >= PAUSE_BTN.y && cy <= PAUSE_BTN.y + PAUSE_BTN.h) {
            state = state === 'running' ? 'paused' : 'running';
            return;
        }
        startGame();
    }

    canvas.setAttribute('tabindex', '0');
    canvas.addEventListener('mousedown',  (e) => handlePointer(e.clientX, e.clientY));
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); handlePointer(e.touches[0].clientX, e.touches[0].clientY); }, { passive: false });

    canvas.addEventListener('keydown', (e) => {
        switch (e.code) {
            case 'ArrowLeft':  case 'KeyA': e.preventDefault(); moveLeft  = true;  break;
            case 'ArrowRight': case 'KeyD': e.preventDefault(); moveRight = true;  break;
            case 'Space': e.preventDefault(); startGame(); break;
            case 'Escape': case 'KeyP':
                if (state === 'running') state = 'paused';
                else if (state === 'paused') state = 'running';
                break;
            default: break;
        }
    });
    canvas.addEventListener('keyup', (e) => {
        switch (e.code) {
            case 'ArrowLeft':  case 'KeyA': moveLeft  = false; break;
            case 'ArrowRight': case 'KeyD': moveRight = false; break;
            default: break;
        }
    });

    // ── Score API ─────────────────────────────────────────────
    function submitScore(s) {
        fetch('/api/jeux/score', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game: 'vendangeexpress', score: s }),
        }).then((r) => r.json())
          .then((d) => { if (d.record !== undefined) worldRecord = d.record; })
          .catch(() => {});
    }

    // ── Update ────────────────────────────────────────────────
    function update() {
        if (state !== 'running') return;
        frame++;

        // Déplacer la caisse
        const crateSpeed = 6.5;
        if (moveLeft)  crateX = Math.max(CRATE_W / 2, crateX - crateSpeed);
        if (moveRight) crateX = Math.min(canvas.width - CRATE_W / 2, crateX + crateSpeed);

        // Vitesse croissante toutes les 150 pts
        baseSpeed = 2.4 + Math.floor(score / 150) * 0.25;

        // Spawn
        const spawnInterval = Math.max(28, 72 - Math.floor(score / 80) * 4);
        if (frame % spawnInterval === 0) spawnItem();

        if (shakeLives > 0) shakeLives--;

        // Items
        items = items.filter((item) => {
            item.y += item.speed;

            // Collision avec la caisse
            const catchZone = CRATE_W / 2 + 10;
            if (item.y + 10 >= CRATE_Y && item.y - 10 <= CRATE_Y + CRATE_H
                && item.x >= crateX - catchZone && item.x <= crateX + catchZone) {
                const val = VALUES[item.type];
                score    += val;
                const col = item.type === 'golden' ? C.goldenHi : item.type === 'bottle' ? C.bottleLbl : C.grapeHi;
                particles.push({ x: item.x, y: CRATE_Y - 10, text: `+${val}`, life: 40, color: col });
                return false;
            }

            // Manqué
            if (item.y > GROUND_Y + 10) {
                lives--;
                shakeLives = 22;
                if (lives <= 0) {
                    if (score > hiScore) hiScore = score;
                    wasWorldRecord = score > worldRecord;
                    if (wasWorldRecord) submitScore(score);
                    state = 'dead';
                }
                return false;
            }

            return true;
        });

        // Particules flottantes
        particles = particles.filter((p) => {
            p.y   -= 1.2;
            p.life--;
            return p.life > 0;
        });
    }

    // ── Draw ──────────────────────────────────────────────────
    function drawBackground() {
        // Ciel
        const grad = ctx.createLinearGradient(0, 0, 0, VINE_Y + 20);
        grad.addColorStop(0, C.sky);
        grad.addColorStop(1, C.skyFar);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, canvas.width, VINE_Y + 20);

        // Sol
        ctx.fillStyle = C.ground;
        ctx.fillRect(0, GROUND_Y, canvas.width, H - GROUND_Y);
        ctx.fillStyle = C.groundDk;
        ctx.fillRect(0, GROUND_Y, canvas.width, 3);

        // Zone intermédiaire (champ)
        ctx.fillStyle = '#9AC848';
        ctx.fillRect(0, VINE_Y + 20, canvas.width, GROUND_Y - VINE_Y - 20);
    }

    function drawVines() {
        for (let col = 0; col < N_COLS; col++) {
            const cx = colX(col);

            // Fil horizontal reliant les vignes (pergola)
            ctx.strokeStyle = C.vine;
            ctx.lineWidth   = 1.5;
            if (col < N_COLS - 1) {
                const nx = colX(col + 1);
                ctx.beginPath();
                ctx.moveTo(cx, 8);
                ctx.lineTo(nx, 8);
                ctx.stroke();
            }

            // Support vertical
            ctx.strokeStyle = C.vine;
            ctx.lineWidth   = 3;
            ctx.beginPath(); ctx.moveTo(cx, 0); ctx.lineTo(cx, VINE_Y); ctx.stroke();

            // Feuilles
            [14, 28, 42].forEach((vy) => {
                ctx.fillStyle = C.leafHi;
                ctx.beginPath(); ctx.ellipse(cx - 10, vy, 9, 5, -0.4, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = C.leaf;
                ctx.beginPath(); ctx.ellipse(cx - 10, vy, 8, 4, -0.4, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = C.leafHi;
                ctx.beginPath(); ctx.ellipse(cx + 10, vy, 9, 5, 0.4, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = C.leaf;
                ctx.beginPath(); ctx.ellipse(cx + 10, vy, 8, 4, 0.4, 0, Math.PI * 2); ctx.fill();
            });
        }

        // Fil supérieur continu
        ctx.strokeStyle = C.vine;
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        ctx.moveTo(colX(0), 8);
        ctx.lineTo(colX(N_COLS - 1), 8);
        ctx.stroke();
    }

    function drawGrapeItem(x, y) {
        [[0,-2],[5,2],[-5,2],[2,7],[-2,7],[0,12]].forEach(([dx, dy]) => {
            ctx.fillStyle = C.grapeHi;
            ctx.beginPath(); ctx.arc(x + dx, y + dy, 5, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = C.grape;
            ctx.beginPath(); ctx.arc(x + dx - 1, y + dy - 1, 4, 0, Math.PI * 2); ctx.fill();
        });
        ctx.strokeStyle = C.vine;
        ctx.lineWidth   = 1.5;
        ctx.beginPath(); ctx.moveTo(x, y - 2); ctx.lineTo(x, y - 8); ctx.stroke();
    }

    function drawBottleItem(x, y) {
        ctx.fillStyle = C.bottle;
        ctx.beginPath(); ctx.roundRect(x - 5, y - 14, 10, 26, 2); ctx.fill();
        ctx.fillStyle = C.bottleLbl;
        ctx.fillRect(x - 4, y - 4, 8, 8);
        ctx.fillStyle = C.bottleCap;
        ctx.fillRect(x - 3, y - 18, 6, 5);
        ctx.fillStyle = C.bottle;
        ctx.fillRect(x - 2, y - 14, 4, 4);
    }

    function drawGoldenItem(x, y) {
        // Grappe dorée avec halo
        ctx.fillStyle = 'rgba(248,208,64,0.22)';
        ctx.beginPath(); ctx.arc(x, y + 6, 18, 0, Math.PI * 2); ctx.fill();
        [[0,-2],[5,2],[-5,2],[2,7],[-2,7],[0,12],[4,11],[-4,11]].forEach(([dx, dy]) => {
            ctx.fillStyle = C.goldenHi;
            ctx.beginPath(); ctx.arc(x + dx, y + dy, 5.5, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = C.golden;
            ctx.beginPath(); ctx.arc(x + dx - 1, y + dy - 1, 4.5, 0, Math.PI * 2); ctx.fill();
        });
        ctx.strokeStyle = C.vine;
        ctx.lineWidth   = 1.5;
        ctx.beginPath(); ctx.moveTo(x, y - 2); ctx.lineTo(x, y - 8); ctx.stroke();
    }

    function drawItems() {
        items.forEach((item) => {
            if (item.type === 'grape')   drawGrapeItem(item.x, item.y);
            else if (item.type === 'bottle') drawBottleItem(item.x, item.y);
            else                         drawGoldenItem(item.x, item.y);
        });
    }

    function drawCrate(cx, shake) {
        const sx = cx + (shake > 0 ? Math.sin(shake * 1.2) * 4 : 0);
        ctx.save();
        ctx.translate(sx, CRATE_Y);

        // Ombre
        ctx.fillStyle = 'rgba(0,0,0,0.15)';
        ctx.fillRect(-CRATE_W / 2 + 4, CRATE_H + 2, CRATE_W - 4, 6);

        // Corps caisse
        ctx.fillStyle = C.crate;
        ctx.fillRect(-CRATE_W / 2, 0, CRATE_W, CRATE_H);

        // Planches (lignes verticales)
        ctx.strokeStyle = C.crateDk;
        ctx.lineWidth   = 1.5;
        [-20, 0, 20].forEach((dx) => {
            ctx.beginPath(); ctx.moveTo(dx, 0); ctx.lineTo(dx, CRATE_H); ctx.stroke();
        });

        // Bordures
        ctx.strokeStyle = C.crateDk;
        ctx.lineWidth   = 2;
        ctx.strokeRect(-CRATE_W / 2, 0, CRATE_W, CRATE_H);
        ctx.fillStyle   = C.crateLt;
        ctx.fillRect(-CRATE_W / 2, 0, CRATE_W, 4);

        // Poignées
        ctx.strokeStyle = C.crateDk;
        ctx.lineWidth   = 3;
        ctx.lineCap     = 'round';
        ctx.beginPath(); ctx.moveTo(-CRATE_W / 2, 8); ctx.lineTo(-CRATE_W / 2 - 8, 16); ctx.lineTo(-CRATE_W / 2, 24); ctx.stroke();
        ctx.beginPath(); ctx.moveTo( CRATE_W / 2, 8); ctx.lineTo( CRATE_W / 2 + 8, 16); ctx.lineTo( CRATE_W / 2, 24); ctx.stroke();
        ctx.lineCap = 'butt';

        // Roues
        [- CRATE_W / 2 + 8, CRATE_W / 2 - 8].forEach((wx) => {
            ctx.fillStyle = C.wheel;
            ctx.beginPath(); ctx.arc(wx, CRATE_H + 2, 7, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = C.crateDk;
            ctx.beginPath(); ctx.arc(wx, CRATE_H + 2, 3.5, 0, Math.PI * 2); ctx.fill();
        });

        ctx.restore();
    }

    function drawParticles() {
        particles.forEach((p) => {
            ctx.globalAlpha = Math.min(1, p.life / 20);
            ctx.fillStyle   = p.color;
            ctx.font        = 'bold 14px Georgia, serif';
            ctx.textAlign   = 'center';
            ctx.fillText(p.text, p.x, p.y);
        });
        ctx.globalAlpha = 1;
        ctx.textAlign   = 'left';
    }

    function drawLives() {
        const shakeX = shakeLives > 0 ? Math.sin(shakeLives * 1.5) * 3 : 0;
        ctx.font      = '14px Georgia, serif';
        ctx.textAlign = 'left';
        for (let i = 0; i < 10; i++) {
            ctx.globalAlpha = i < lives ? 1 : 0.22;
            ctx.fillText('🍇', 14 + i * 20 + shakeX, H - 8);
        }
        ctx.globalAlpha = 1;
    }

    function drawHUD() {
        ctx.textAlign = 'right';
        ctx.fillStyle = C.gold;
        ctx.font      = 'bold 16px Georgia, serif';
        ctx.fillText(`${String(score).padStart(5, '0')} pts`, canvas.width - 14, 28);
        ctx.fillStyle = C.goldDk;
        ctx.font      = '12px Georgia, serif';
        if (hiScore > 0)     ctx.fillText(`HI  ${String(hiScore).padStart(5, '0')}`, canvas.width - 14, 46);
        if (worldRecord > 0) ctx.fillText(`WR  ${String(worldRecord).padStart(5, '0')}`, canvas.width - 14, 62);

        // Vitesse
        ctx.textAlign = 'left';
        ctx.fillStyle = C.goldDk;
        ctx.font      = '11px Georgia, serif';
        const lvl = Math.floor(score / 150) + 1;
        ctx.fillText(`Niveau ${lvl}`, 14, 28);

        drawLives();
        ctx.textAlign = 'left';
    }

    function drawPauseBtn() {
        if (state !== 'running' && state !== 'paused') return;
        const { x, y, w, h } = PAUSE_BTN;
        ctx.fillStyle = 'rgba(42,28,14,0.55)';
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(x, y, w, h, 4); else ctx.rect(x, y, w, h);
        ctx.fill();
        ctx.fillStyle = C.gold;
        ctx.font      = '15px Georgia, serif';
        ctx.textAlign = 'center';
        ctx.fillText(state === 'paused' ? '▶' : '⏸', x + w / 2, y + h / 2 + 6);
        ctx.textAlign = 'left';
    }

    function drawPlayBtn(label = '▶  Jouer') {
        const bw = 160, bh = 40;
        const bx = canvas.width / 2 - bw / 2;
        const by = H / 2 + 52;
        ctx.fillStyle = C.gold;
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(bx, by, bw, bh, 6); else ctx.rect(bx, by, bw, bh);
        ctx.fill();
        ctx.fillStyle = C.txt;
        ctx.font      = 'bold 17px Georgia, serif';
        ctx.textAlign = 'center';
        ctx.fillText(label, canvas.width / 2, by + 27);
        ctx.textAlign = 'left';
    }

    function drawOverlay(lines) {
        ctx.fillStyle = C.overlay;
        ctx.fillRect(0, 0, canvas.width, H);
        ctx.textAlign = 'center';
        lines.forEach(({ text, size, color, dy }) => {
            ctx.font      = `${size}px Georgia, serif`;
            ctx.fillStyle = color;
            ctx.fillText(text, canvas.width / 2, H / 2 + dy);
        });
        ctx.textAlign = 'left';
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, H);
        drawBackground();
        drawVines();
        drawItems();

        if (state !== 'idle') drawCrate(crateX, shakeLives);

        drawParticles();
        drawHUD();
        drawPauseBtn();

        if (state === 'idle') {
            drawOverlay([
                { text: '← →  pour déplacer la caisse',                   size: 14, color: '#F5F0E8', dy: -28 },
                { text: '🍇 +10   🍾 +30   ✨ +50   ·   10 vies',         size: 13, color: '#F5F0E8', dy:  -4 },
                { text: '— Ne laissez rien tomber ! —',                    size: 13, color: C.gold,    dy:  20 },
            ]);
            drawPlayBtn();
        }

        if (state === 'paused') {
            drawOverlay([
                { text: '⏸  Pause',              size: 22, color: '#F5F0E8', dy: -14 },
                { text: 'P ou ▶ pour reprendre', size: 13, color: C.gold,    dy:  14 },
            ]);
            drawPlayBtn();
        }

        if (state === 'dead') {
            const isPersonalRecord = score >= hiScore && score > 0;
            const recordMsg        = wasWorldRecord
                ? '✦ Record Mondial ! ✦'
                : isPersonalRecord
                    ? '✦ Nouveau record personnel ! ✦'
                    : `Meilleur : ${hiScore} pts`;
            drawOverlay([
                { text: `Score : ${score} pts`, size: 22, color: '#F5F0E8', dy: -22 },
                { text: recordMsg,              size: 14, color: C.gold,    dy:   6 },
            ]);
            drawPlayBtn('↺  Rejouer');
        }
    }

    function loop() {
        update();
        draw();
        animId = requestAnimationFrame(loop);
    }

    reset();
    loop();
}
