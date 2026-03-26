/**
 * Jeu Labour Chrono — vue de dessus, mode survie.
 * ↑↓ = changer de rangée. Récoltez raisins (20 pts), barriques (100 pts).
 * Ramassez le bouclier pour 5 s d'immunité contre les cailloux.
 * Score = mètres parcourus + collectibles. Game over = caillou sans bouclier.
 */

export function initLabourChronoGame() {
    const section = document.getElementById('labour-chrono-game');
    if (!section) return;
    const canvas = /** @type {HTMLCanvasElement} */ (document.getElementById('labour-chrono-canvas'));
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
        lane:        '#8BAE3A',
        laneDark:    '#6A9020',
        vine:        '#3A6A10',
        vinePost:    '#2A5A08',
        ground:      '#C8B46A',
        tractor:     '#C8391A',
        tractorB:    '#9A2810',
        cab:         '#E05030',
        wheel:       '#2C2010',
        rock:        '#8C7A5A',
        rockDk:      '#5A4A30',
        grape:       '#7A2A9C',
        grapeHi:     '#AC5ACA',
        leaf:        '#3A7A20',
        barrelTop:   '#C07030',
        barrelHi:    '#E09050',
        barrelHoop:  '#5A3010',
        shield:      '#30A8E0',
        shieldHi:    '#80D8FF',
        shieldGlow:  'rgba(48,168,224,0.35)',
        gold:        '#C9A96E',
        goldDk:      '#A07820',
        txt:         '#3A2A1A',
        overlay:     'rgba(42,28,14,0.62)',
    };

    // ── Layout ───────────────────────────────────────────────
    const N_LANES   = 3;
    const LANE_H    = H / N_LANES;
    const DIVIDER_H = 14;
    const TRACTOR_X = 130;
    const TRACTOR_W = 44;
    const TRACTOR_H = 28;
    const PAUSE_BTN = { x: 8, y: 8, w: 30, h: 24 };
    const SHIELD_DUR = 5 * 60;   // 5 secondes × 60 fps

    // ── État ─────────────────────────────────────────────────
    let state        = 'idle';
    let lane         = 1;
    let targetLane   = 1;
    let laneT        = 1.0;
    let scrollX      = 0;
    let speed        = 3.2;
    let score        = 0;
    let bonus        = 0;   // raisins + barriques
    let hiScore      = 0;
    let worldRecord  = parseInt(canvas.dataset.worldRecord, 10) || 0;
    let frame        = 0;
    let animId;             // eslint-disable-line no-unused-vars
    let obstacles    = [];  // { x, lane }
    let grapes       = [];  // { x, lane }
    let barrels      = [];  // { x, lane }   — barriques 100 pts
    let powerups     = [];  // { x, lane }   — boucliers
    let posts        = [];
    let stunFrames   = 0;
    let shieldFrames   = 0;   // frames restantes de bouclier actif
    let wasWorldRecord = false;

    function laneCenterY(l) { return LANE_H * l + LANE_H / 2; }
    function tractorY() {
        return laneCenterY(lane) + (laneCenterY(targetLane) - laneCenterY(lane)) * laneT;
    }

    function spawnAt(arr, extra = {}) {
        arr.push({ x: canvas.width + 60 + Math.random() * 100, lane: Math.floor(Math.random() * N_LANES), ...extra });
    }

    function generatePosts() {
        posts = [];
        for (let x = 0; x <= canvas.width + 200; x += 72) posts.push({ x });
    }

    function reset() {
        lane = 1; targetLane = 1; laneT = 1.0;
        scrollX = 0; speed = 3.2; score = 0; bonus = 0;
        frame = 0; stunFrames = 0; shieldFrames = 0; wasWorldRecord = false;
        obstacles = []; grapes = []; barrels = []; powerups = [];
        generatePosts();
        for (let i = 0; i < 5; i++) spawnAt(grapes, { x: 280 + i * 160 });
    }

    // ── Input ─────────────────────────────────────────────────
    function startGame() {
        if (state === 'idle' || state === 'dead') { reset(); state = 'running'; canvas.focus(); }
        else if (state === 'paused') state = 'running';
    }
    function changeLane(dir) {
        if (state !== 'running') return;
        const nl = targetLane + dir;
        if (nl < 0 || nl >= N_LANES) return;
        targetLane = nl;
        laneT = 0;
        lane = targetLane - dir;
    }

    function handlePointer(clientX, clientY) {
        const rect = canvas.getBoundingClientRect();
        const cx = (clientX - rect.left) * (canvas.width  / rect.width);
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
            case 'ArrowUp':    e.preventDefault(); changeLane(-1); break;
            case 'ArrowDown':  e.preventDefault(); changeLane(1);  break;
            case 'Space': case 'ArrowRight': e.preventDefault(); startGame(); break;
            case 'Escape': case 'KeyP':
                if (state === 'running') state = 'paused';
                else if (state === 'paused') state = 'running';
                break;
            default: break;
        }
    });

    // ── Score API ─────────────────────────────────────────────
    function submitScore(s) {
        fetch('/api/jeux/score', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game: 'labour', score: s }),
        }).then((r) => r.json())
          .then((d) => { if (d.record !== undefined) worldRecord = d.record; })
          .catch(() => {});
    }

    // ── Collision helper ──────────────────────────────────────
    function hitsTractor(obj) {
        return Math.abs(obj.x - TRACTOR_X) < TRACTOR_W * 0.55
            && Math.abs(laneCenterY(obj.lane) - tractorY()) < TRACTOR_H * 0.55;
    }

    // ── Update ────────────────────────────────────────────────
    function update() {
        if (state !== 'running') return;
        frame++;

        if (stunFrames > 0) { stunFrames--; return; }
        if (shieldFrames > 0) shieldFrames--;

        // Avancement (vitesse croissante)
        scrollX += speed;
        speed    = Math.min(9.0, 3.2 + scrollX / 2500);
        score    = Math.floor(scrollX / 50);

        // Transition de rangée
        if (laneT < 1.0) {
            laneT = Math.min(1.0, laneT + 0.12);
            if (laneT >= 1.0) lane = targetLane;
        }

        // Spawn — difficultés progressives
        const rockInterval = Math.max(45, 130 - Math.floor(scrollX / 400));
        if (frame % rockInterval === 0) spawnAt(obstacles);
        if (frame % 85 === 0) spawnAt(grapes);
        if (frame % 320 === 0) spawnAt(barrels);       // barrique rare
        if (frame % 480 === 0) spawnAt(powerups);      // bouclier très rare

        // Obstacles (cailloux)
        obstacles = obstacles.filter((o) => {
            o.x -= speed;
            if (o.x < -40) return false;
            if (hitsTractor(o)) {
                if (shieldFrames > 0) {
                    // Bouclier actif : absorbe le choc, courte pause
                    stunFrames = 18;
                    return false;
                }
                // Game over
                const finalScore = score + bonus;
                if (finalScore > hiScore) hiScore = finalScore;
                wasWorldRecord = finalScore > worldRecord;
                if (wasWorldRecord) submitScore(finalScore);
                state = 'dead';
                return false;
            }
            return true;
        });

        // Raisins (20 pts)
        grapes = grapes.filter((g) => {
            g.x -= speed;
            if (g.x < -30) return false;
            if (hitsTractor(g)) { bonus += 20; return false; }
            return true;
        });

        // Barriques (100 pts)
        barrels = barrels.filter((b) => {
            b.x -= speed;
            if (b.x < -30) return false;
            if (hitsTractor(b)) { bonus += 100; return false; }
            return true;
        });

        // Boucliers (power-up)
        powerups = powerups.filter((p) => {
            p.x -= speed;
            if (p.x < -30) return false;
            if (hitsTractor(p)) { shieldFrames = SHIELD_DUR; return false; }
            return true;
        });

        // Décaler les piquets décoratifs
        posts.forEach((p) => {
            p.x -= speed;
            if (p.x < -10) p.x += canvas.width + 200;
        });
    }

    // ── Draw ──────────────────────────────────────────────────
    function drawLanes() {
        ctx.fillStyle = C.ground;
        ctx.fillRect(0, 0, canvas.width, H);

        for (let l = 0; l < N_LANES; l++) {
            const y = LANE_H * l + DIVIDER_H / 2;
            const h = LANE_H - DIVIDER_H;
            ctx.fillStyle = (l % 2 === 0) ? C.lane : C.laneDark;
            ctx.fillRect(0, y, canvas.width, h);
        }

        for (let l = 1; l < N_LANES; l++) {
            const dy = LANE_H * l - DIVIDER_H / 2;
            ctx.fillStyle = C.vine;
            ctx.fillRect(0, dy, canvas.width, DIVIDER_H);
            posts.forEach((p) => {
                ctx.fillStyle = C.vinePost;
                ctx.fillRect(p.x - 3, dy + 1, 6, DIVIDER_H - 2);
                ctx.strokeStyle = 'rgba(74,122,40,0.4)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(p.x - 36, dy + DIVIDER_H / 2);
                ctx.lineTo(p.x + 36, dy + DIVIDER_H / 2);
                ctx.stroke();
            });
        }
    }

    function drawObstacles() {
        obstacles.forEach((o) => {
            const cy = laneCenterY(o.lane);
            // Caillou avec légère animation de tremblement si bouclier actif
            const wobble = shieldFrames > 0 ? Math.sin(frame * 0.4) * 2 : 0;
            ctx.fillStyle = C.rockDk;
            ctx.beginPath(); ctx.ellipse(o.x + 3, cy + 4, 14, 10, 0.3, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = C.rock;
            ctx.beginPath(); ctx.ellipse(o.x + wobble, cy, 14, 10, 0.3, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = C.rockDk;
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(o.x - 4, cy - 3); ctx.lineTo(o.x + 2, cy - 5); ctx.stroke();
        });
    }

    function drawGrapes() {
        grapes.forEach((g) => {
            const cy = laneCenterY(g.lane);
            ctx.fillStyle = C.leaf;
            ctx.beginPath(); ctx.ellipse(g.x + 6, cy - 10, 7, 4, -0.5, 0, Math.PI * 2); ctx.fill();
            [[0,0],[5,0],[-5,0],[2.5,5],[-2.5,5],[0,10],[5,10]].forEach(([dx, dy]) => {
                ctx.fillStyle = C.grapeHi;
                ctx.beginPath(); ctx.arc(g.x + dx, cy + dy - 4, 4.5, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = C.grape;
                ctx.beginPath(); ctx.arc(g.x + dx - 1, cy + dy - 5, 4, 0, Math.PI * 2); ctx.fill();
            });
        });
    }

    function drawBarrels() {
        barrels.forEach((b) => {
            const cy = laneCenterY(b.lane);
            // Barrique vue de dessus
            ctx.fillStyle = C.barrelTop;
            ctx.beginPath(); ctx.ellipse(b.x, cy, 14, 14, 0, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = C.barrelHi;
            ctx.beginPath(); ctx.ellipse(b.x - 3, cy - 3, 9, 9, 0, 0, Math.PI * 2); ctx.fill();
            // Cerclages
            ctx.strokeStyle = C.barrelHoop;
            ctx.lineWidth   = 2;
            [0, 5, -5].forEach((dr) => {
                ctx.beginPath(); ctx.ellipse(b.x, cy, 14 - Math.abs(dr) * 0.3, 4 + dr * 0.5, 0, 0, Math.PI * 2); ctx.stroke();
            });
            // Label "100"
            ctx.fillStyle   = C.barrelHoop;
            ctx.font        = 'bold 9px Georgia, serif';
            ctx.textAlign   = 'center';
            ctx.fillText('100', b.x, cy + 3.5);
            ctx.textAlign   = 'left';
        });
    }

    function drawPowerups() {
        powerups.forEach((p) => {
            const cy  = laneCenterY(p.lane);
            const glow = shieldFrames === 0 ? 1 : 0.5;
            // Halo pulsant
            ctx.fillStyle = `rgba(48,168,224,${0.2 + Math.sin(frame * 0.15) * 0.1 * glow})`;
            ctx.beginPath(); ctx.arc(p.x, cy, 20, 0, Math.PI * 2); ctx.fill();
            // Bouclier (hexagone simplifié)
            ctx.fillStyle = C.shield;
            ctx.beginPath();
            for (let i = 0; i < 6; i++) {
                const a = (i / 6) * Math.PI * 2 - Math.PI / 2;
                const r = 13;
                if (i === 0) ctx.moveTo(p.x + Math.cos(a) * r, cy + Math.sin(a) * r);
                else         ctx.lineTo(p.x + Math.cos(a) * r, cy + Math.sin(a) * r);
            }
            ctx.closePath(); ctx.fill();
            ctx.fillStyle = C.shieldHi;
            ctx.beginPath();
            for (let i = 0; i < 6; i++) {
                const a = (i / 6) * Math.PI * 2 - Math.PI / 2;
                const r = 8;
                if (i === 0) ctx.moveTo(p.x + Math.cos(a) * r, cy + Math.sin(a) * r);
                else         ctx.lineTo(p.x + Math.cos(a) * r, cy + Math.sin(a) * r);
            }
            ctx.closePath(); ctx.fill();
            // Éclair
            ctx.fillStyle = '#FFFFFF';
            ctx.font      = 'bold 11px Georgia, serif';
            ctx.textAlign = 'center';
            ctx.fillText('⚡', p.x, cy + 4);
            ctx.textAlign = 'left';
        });
    }

    function drawTractor(cx, cy) {
        ctx.save();
        ctx.translate(cx, cy);

        // Halo bouclier actif
        if (shieldFrames > 0) {
            const pulse = 0.5 + Math.sin(frame * 0.25) * 0.3;
            ctx.fillStyle = `rgba(48,168,224,${pulse * 0.4})`;
            ctx.beginPath(); ctx.ellipse(0, 0, TRACTOR_W * 0.8, TRACTOR_H * 0.9, 0, 0, Math.PI * 2); ctx.fill();
        }

        // Ombre
        ctx.fillStyle = 'rgba(0,0,0,0.18)';
        ctx.fillRect(-TRACTOR_W / 2 + 4, -TRACTOR_H / 2 + 4, TRACTOR_W, TRACTOR_H);

        // Corps
        const bodyColor = stunFrames > 0 ? '#E08040' : (shieldFrames > 0 ? '#50A8E0' : C.tractor);
        ctx.fillStyle = bodyColor;
        ctx.fillRect(-TRACTOR_W / 2, -TRACTOR_H / 2, TRACTOR_W, TRACTOR_H);

        // Cabine
        ctx.fillStyle = C.cab;
        ctx.fillRect(TRACTOR_W / 2 - 14, -TRACTOR_H / 2, 14, TRACTOR_H);
        ctx.fillStyle = 'rgba(180,220,255,0.5)';
        ctx.fillRect(TRACTOR_W / 2 - 12, -TRACTOR_H / 2 + 4, 10, TRACTOR_H - 8);
        ctx.fillStyle = C.tractorB;
        ctx.fillRect(-TRACTOR_W / 2 + 2, -TRACTOR_H / 2 + 4, 20, TRACTOR_H - 8);

        // Roues
        ctx.fillStyle = C.wheel;
        [
            [-TRACTOR_W / 2 - 5, -TRACTOR_H / 2 - 3, 8, 10],
            [-TRACTOR_W / 2 - 5,  TRACTOR_H / 2 - 7, 8, 10],
            [ TRACTOR_W / 2 - 3, -TRACTOR_H / 2 - 2, 6,  9],
            [ TRACTOR_W / 2 - 3,  TRACTOR_H / 2 - 7, 6,  9],
        ].forEach(([wx, wy, ww, wh]) => ctx.fillRect(wx, wy, ww, wh));

        ctx.restore();
    }

    function drawHUD() {
        const totalScore = score + bonus;

        // Score principal
        ctx.textAlign = 'right';
        ctx.fillStyle = C.gold;
        ctx.font      = 'bold 16px Georgia, serif';
        ctx.fillText(`${String(totalScore).padStart(6, '0')} pts`, canvas.width - 14, 28);
        ctx.fillStyle = C.goldDk;
        ctx.font      = '12px Georgia, serif';
        if (hiScore > 0) ctx.fillText(`HI  ${String(hiScore).padStart(6, '0')}`, canvas.width - 14, 46);
        if (worldRecord > 0) ctx.fillText(`WR  ${String(worldRecord).padStart(6, '0')}`, canvas.width - 14, 62);

        // Distance + bonus
        ctx.textAlign = 'left';
        ctx.fillStyle = C.goldDk;
        ctx.font      = '12px Georgia, serif';
        ctx.fillText(`${score} m`, 14, 26);
        if (bonus > 0) {
            ctx.fillStyle = C.grape;
            ctx.fillText(`+${bonus}`, 14, 42);
        }

        // Bouclier actif
        if (shieldFrames > 0) {
            const secs = Math.ceil(shieldFrames / 60);
            ctx.fillStyle = C.shield;
            ctx.font      = 'bold 13px Georgia, serif';
            ctx.fillText(`⚡ ${secs} s`, 14, H - 12);
        }

        // Stun feedback
        if (stunFrames > 0 && shieldFrames > 0) {
            ctx.fillStyle = C.shield;
            ctx.font      = 'bold 12px Georgia, serif';
            ctx.fillText('⚡ PROTÉGÉ !', 14, H - 30);
        }
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
        const bw = 150, bh = 40;
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
        drawLanes();
        drawObstacles();
        drawGrapes();
        drawBarrels();
        drawPowerups();

        if (state !== 'idle') drawTractor(TRACTOR_X, tractorY());

        drawHUD();
        drawPauseBtn();

        if (state === 'idle') {
            drawOverlay([
                { text: '↑ ↓  Changer de rangée',                 size: 15, color: '#F5F0E8', dy: -32 },
                { text: '🍇 Raisins +20   🛢 Barriques +100   ⚡ Bouclier 5 s', size: 11, color: '#F5F0E8', dy:  -8 },
                { text: '— Survivez le plus longtemps possible ! —', size: 13, color: C.gold,    dy:  18 },
            ]);
            drawPlayBtn();
        }

        if (state === 'paused') {
            drawOverlay([
                { text: '⏸  Pause',                size: 22, color: '#F5F0E8', dy: -14 },
                { text: 'P ou ▶ pour reprendre',   size: 13, color: C.gold,    dy:  14 },
            ]);
            drawPlayBtn();
        }

        if (state === 'dead') {
            const totalScore       = score + bonus;
            const isPersonalRecord = totalScore >= hiScore && totalScore > 0;
            const recordMsg        = wasWorldRecord
                ? '✦ Record Mondial ! ✦'
                : isPersonalRecord
                    ? '✦ Nouveau record personnel ! ✦'
                    : `Meilleur : ${hiScore} pts`;
            drawOverlay([
                { text: `Score : ${totalScore} pts`, size: 22, color: '#F5F0E8', dy: -22 },
                { text: recordMsg,                   size: 14, color: C.gold,    dy:   6 },
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
