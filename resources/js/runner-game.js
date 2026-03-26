/**
 * Jeu runner — La Vendangeuse.
 * Canvas 2D inspiré du T-Rex Chrome.
 * La vendangeuse (panier de raisins) saute par-dessus des vignes.
 * Tracteurs décoratifs dans le fond. Vitesse croissante.
 */

export function initRunnerGame() {
    const section = document.getElementById('runner-game');
    if (!section) return;

    const canvas = /** @type {HTMLCanvasElement} */ (document.getElementById('runner-canvas'));
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // ── Palette ───────────────────────────────────────────────────
    const C = {
        sky:       '#F5F0E8',
        hill:      '#DDD4A8',
        hillDark:  '#C8BA88',
        ground:    '#6B4F2A',
        turf:      '#8B6B1A',
        vine:      '#2D5A1B',
        leaf:      '#3A7A25',
        grape:     '#6B3FA0',
        grapeHi:   '#8B5FC0',
        skin:      '#D4956A',
        hair:      '#8B4513',
        dress:     '#D64B8A',
        dressHem:  '#C03878',
        apron:     '#F0D090',
        basket:    '#A0723A',
        basketRim: '#7A5220',
        hat:       '#C8A84B',
        hatRib:    '#D64B8A',
        hatFlower: '#FF85A1',
        shoe:      '#5C3318',
        gold:      '#C9A96E',
        goldDark:  '#A07820',
        textDark:  '#3A2A1A',
        cloud:     '#E8E0D0',
        overlay:   'rgba(42,28,14,0.58)',
        // Tracteur
        tractor:   '#C8391A',
        tractorB:  '#9A2810',
        wheel:     '#2C2010',
        wheelRim:  '#5C4820',
        tractorCab:'#E05030',
        exhaust:   '#5C5C5C',
    };

    // ── Dimensions ────────────────────────────────────────────────
    const H        = 260;
    const GROUND_Y = H - 55;
    const CHAR_W   = 30;
    const CHAR_H   = 58;
    const CHAR_X   = 90;
    const GRAVITY   = 0.64;
    const JUMP_V    = -13.5;
    const PAUSE_BTN = { x: 8, y: 8, w: 30, h: 24 };

    function resizeCanvas() {
        canvas.width  = Math.min(section.clientWidth, 900);
        canvas.height = H;
    }
    resizeCanvas();
    window.addEventListener('resize', () => { resizeCanvas(); if (state !== 'running') draw(); });

    // ── État ──────────────────────────────────────────────────────
    let state       = 'idle';
    let score       = 0;
    let hiScore     = 0;
    let worldRecord = parseInt(canvas.dataset.worldRecord, 10) || 0;
    let speed       = 5;
    let frame       = 0;
    let animId;     // eslint-disable-line no-unused-vars
    let hillOff     = 0;

    const char = { y: GROUND_Y - CHAR_H, vy: 0, grounded: true };

    let obstacles  = [];
    let nextSpawn  = 90;
    let clouds     = [];
    let tractors   = [];
    let nextTractor = 300 + Math.floor(Math.random() * 200);

    // ── Reset ─────────────────────────────────────────────────────
    function reset() {
        score        = 0;
        speed        = 5;
        frame        = 0;
        obstacles    = [];
        tractors     = [];
        nextSpawn    = 90;
        nextTractor  = 300 + Math.floor(Math.random() * 200);
        hillOff      = 0;
        char.y       = GROUND_Y - CHAR_H;
        char.vy      = 0;
        char.grounded = true;
        clouds = [
            { x: canvas.width * 0.25, y: 30, r: 26 },
            { x: canvas.width * 0.65, y: 18, r: 20 },
        ];
    }

    // ── Input ─────────────────────────────────────────────────────
    function handleInput() {
        if (state === 'idle' || state === 'dead') {
            reset(); state = 'running'; canvas.focus();
        } else if (state === 'paused') {
            state = 'running';
        } else if (state === 'running' && char.grounded) {
            char.vy       = JUMP_V;
            char.grounded = false;
        }
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
        handleInput();
    }

    canvas.setAttribute('tabindex', '0');
    canvas.addEventListener('click', (e) => handlePointer(e.clientX, e.clientY));
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); handlePointer(e.touches[0].clientX, e.touches[0].clientY); }, { passive: false });
    canvas.addEventListener('keydown', (e) => {
        if (e.code === 'Space' || e.code === 'ArrowUp') { e.preventDefault(); handleInput(); }
        if (e.code === 'Escape' || e.code === 'KeyP') {
            if (state === 'running') state = 'paused';
            else if (state === 'paused') state = 'running';
        }
    });

    // ── Spawn ─────────────────────────────────────────────────────
    function spawnObstacle() {
        const types = [
            { w: 18, h: 36 },
            { w: 22, h: 50 },
            { w: 28, h: 64 },
            { w: 42, h: 34 },
        ];
        const t = types[Math.floor(Math.random() * types.length)];
        obstacles.push({ x: canvas.width + 10, w: t.w, h: t.h });
    }

    function spawnTractor() {
        tractors.push({ x: canvas.width + 20, speed: speed * 0.28 });
    }

    // ── Fond ──────────────────────────────────────────────────────
    function drawBackground() {
        ctx.fillStyle = C.sky;
        ctx.fillRect(0, 0, canvas.width, H);

        // Nuages
        ctx.fillStyle = C.cloud;
        clouds.forEach((c) => {
            ctx.beginPath();
            ctx.arc(c.x, c.y, c.r, 0, Math.PI * 2);
            ctx.arc(c.x + c.r * 0.8, c.y + 6, c.r * 0.65, 0, Math.PI * 2);
            ctx.arc(c.x - c.r * 0.8, c.y + 6, c.r * 0.6, 0, Math.PI * 2);
            ctx.fill();
        });

        // Collines
        const hw = canvas.width / 2;
        ctx.fillStyle = C.hill;
        ctx.beginPath();
        ctx.moveTo(0, GROUND_Y + 10);
        for (let i = -1; i <= 3; i++) {
            const ox = i * hw - hillOff % hw;
            ctx.quadraticCurveTo(ox + hw * 0.5, GROUND_Y - 38, ox + hw, GROUND_Y + 10);
        }
        ctx.lineTo(canvas.width, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();

        // Sol
        ctx.fillStyle = C.turf;
        ctx.fillRect(0, GROUND_Y, canvas.width, 7);
        ctx.fillStyle = C.ground;
        ctx.fillRect(0, GROUND_Y + 7, canvas.width, H - GROUND_Y);
    }

    // ── Tracteur (décor) ──────────────────────────────────────────
    function drawTractor(tr) {
        const tx = tr.x;
        const ty = GROUND_Y - 34; // positionné sur le sol, fond colline
        const s  = 0.72;          // légèrement réduit = effet de profondeur

        ctx.save();
        ctx.translate(tx, ty);
        ctx.scale(s, s);

        // Carrosserie principale
        ctx.fillStyle = C.tractor;
        ctx.fillRect(0, 10, 60, 24);

        // Capot moteur (avant)
        ctx.fillStyle = C.tractorCab;
        ctx.fillRect(42, 4, 22, 30);

        // Cabine conducteur
        ctx.fillStyle = C.tractorB;
        ctx.fillRect(10, 0, 28, 14);
        // Vitre
        ctx.fillStyle = 'rgba(180,220,255,0.5)';
        ctx.fillRect(13, 2, 22, 10);

        // Silhouette conducteur
        ctx.fillStyle = C.textDark;
        ctx.beginPath();
        ctx.arc(20, -2, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillRect(17, 2, 6, 8);

        // Tuyau d'échappement
        ctx.fillStyle = C.exhaust;
        ctx.fillRect(60, -4, 5, 16);
        // Fumée
        if (frame % 10 < 5) {
            ctx.fillStyle = 'rgba(150,150,150,0.35)';
            ctx.beginPath();
            ctx.arc(62, -8, 5, 0, Math.PI * 2);
            ctx.arc(65, -13, 4, 0, Math.PI * 2);
            ctx.fill();
        }

        // Grande roue arrière
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(18, 34, 18, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.wheelRim;
        ctx.beginPath(); ctx.arc(18, 34, 12, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(18, 34, 6, 0, Math.PI * 2); ctx.fill();

        // Petite roue avant
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(54, 36, 11, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.wheelRim;
        ctx.beginPath(); ctx.arc(54, 36, 7, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(54, 36, 3, 0, Math.PI * 2); ctx.fill();

        ctx.restore();
    }

    // ── Vigne ────────────────────────────────────────────────────
    function drawVigne(obs) {
        const oy = GROUND_Y - obs.h;
        const cx = obs.x + obs.w / 2;

        ctx.fillStyle = C.vine;
        ctx.fillRect(cx - 3, oy + Math.floor(obs.h * 0.35), 6, Math.ceil(obs.h * 0.65));

        ctx.fillStyle = C.leaf;
        const rows = Math.max(2, Math.floor(obs.h / 18));
        for (let i = 0; i < rows; i++) {
            const ly = oy + i * (obs.h / rows);
            ctx.beginPath();
            ctx.moveTo(cx - 2, ly + 8); ctx.lineTo(obs.x, ly); ctx.lineTo(obs.x + 5, ly + 15);
            ctx.closePath(); ctx.fill();
            ctx.beginPath();
            ctx.moveTo(cx + 2, ly + 8); ctx.lineTo(obs.x + obs.w, ly); ctx.lineTo(obs.x + obs.w - 5, ly + 15);
            ctx.closePath(); ctx.fill();
        }

        ctx.fillStyle = C.grape;
        [[cx - 5, oy], [cx + 5, oy], [cx, oy + 9], [cx - 5, oy + 9], [cx + 5, oy + 9]].forEach(([gx, gy]) => {
            ctx.beginPath(); ctx.arc(gx, gy, 4.5, 0, Math.PI * 2); ctx.fill();
        });
        ctx.fillStyle = C.grapeHi;
        ctx.beginPath(); ctx.arc(cx - 5, oy - 1, 2, 0, Math.PI * 2); ctx.fill();
    }

    // ── Panier de raisins ─────────────────────────────────────────
    function drawBasket(bx, by) {
        // Corps du panier
        ctx.fillStyle = C.basket;
        ctx.beginPath();
        ctx.ellipse(bx, by + 8, 11, 8, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillRect(bx - 11, by, 22, 9);

        // Bord supérieur
        ctx.fillStyle = C.basketRim;
        ctx.beginPath();
        ctx.ellipse(bx, by, 11, 4, 0, 0, Math.PI * 2);
        ctx.fill();

        // Raisins dépassant du panier
        ctx.fillStyle = C.grape;
        [[bx - 4, by - 3], [bx + 2, by - 5], [bx + 6, by - 2], [bx - 1, by - 7]].forEach(([gx, gy]) => {
            ctx.beginPath(); ctx.arc(gx, gy, 4, 0, Math.PI * 2); ctx.fill();
        });
        ctx.fillStyle = C.grapeHi;
        ctx.beginPath(); ctx.arc(bx - 4, by - 4, 1.5, 0, Math.PI * 2); ctx.fill();

        // Anse
        ctx.strokeStyle = C.basketRim;
        ctx.lineWidth   = 2.5;
        ctx.beginPath();
        ctx.arc(bx, by - 4, 10, Math.PI, 0);
        ctx.stroke();
    }

    // ── Vendangeuse ───────────────────────────────────────────────
    function drawVendangeuse() {
        const x   = CHAR_X;
        const y   = char.y;
        const run = char.grounded ? Math.sin(frame * 0.28) : 0;
        const air = !char.grounded;

        // Ombre
        ctx.fillStyle = 'rgba(0,0,0,0.10)';
        ctx.beginPath();
        ctx.ellipse(x + CHAR_W / 2, GROUND_Y + 5, 14, 4, 0, 0, Math.PI * 2);
        ctx.fill();

        // Jambes (robe mi-longue — on voit juste le bas)
        [[x + 8, -1], [x + 20, 1]].forEach(([lx, side]) => {
            ctx.save();
            ctx.translate(lx, y + 42);
            ctx.rotate(run * 0.28 * side);
            ctx.fillStyle = C.skin;
            ctx.fillRect(-4, 0, 8, 14);
            ctx.fillStyle = C.shoe;
            ctx.beginPath();
            ctx.ellipse(0, 14, 6, 4, run * 0.1 * side, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        });

        // Robe — jupe (avec mouvement)
        const skirtWave = Math.sin(frame * 0.22) * 3;
        ctx.fillStyle = C.dress;
        ctx.beginPath();
        ctx.moveTo(x + 2, y + 22);
        ctx.lineTo(x + CHAR_W - 2, y + 22);
        ctx.lineTo(x + CHAR_W + 4 + skirtWave, y + 50);
        ctx.lineTo(x - 4 - skirtWave, y + 50);
        ctx.closePath();
        ctx.fill();

        // Ourlet de robe
        ctx.fillStyle = C.dressHem;
        ctx.beginPath();
        ctx.moveTo(x + CHAR_W + 4 + skirtWave, y + 50);
        ctx.lineTo(x - 4 - skirtWave, y + 50);
        ctx.lineTo(x - 5 - skirtWave, y + 54);
        ctx.lineTo(x + CHAR_W + 5 + skirtWave, y + 54);
        ctx.closePath();
        ctx.fill();

        // Corps (corsage)
        ctx.fillStyle = C.dress;
        ctx.fillRect(x + 5, y + 14, CHAR_W - 10, 14);

        // Tablier
        ctx.fillStyle = C.apron;
        ctx.fillRect(x + 7, y + 16, CHAR_W - 14, 10);

        // Bras gauche (balance)
        ctx.save();
        ctx.translate(x + 4, y + 17);
        ctx.rotate(-run * 0.25 - 0.3);
        ctx.fillStyle = C.dress;
        ctx.fillRect(-4, 0, 8, 14);
        ctx.fillStyle = C.skin;
        ctx.fillRect(-3, 12, 7, 7);
        ctx.restore();

        // Bras droit (tient le panier)
        ctx.save();
        const basketArmAngle = air ? -0.6 : run * 0.15 - 0.2;
        ctx.translate(x + CHAR_W - 4, y + 17);
        ctx.rotate(basketArmAngle);
        ctx.fillStyle = C.dress;
        ctx.fillRect(-4, 0, 8, 14);
        ctx.fillStyle = C.skin;
        ctx.fillRect(-3, 12, 7, 7);
        ctx.restore();

        // Panier (position calculée depuis le bras droit)
        const armEndX = x + CHAR_W - 4 + Math.sin(basketArmAngle) * 22;
        const armEndY = y + 17 + Math.cos(basketArmAngle) * 22;
        drawBasket(armEndX, armEndY);

        // Cou
        ctx.fillStyle = C.skin;
        ctx.fillRect(x + CHAR_W / 2 - 3, y + 8, 7, 8);

        // Tête
        ctx.fillStyle = C.skin;
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2, y + 5, 11, 0, Math.PI * 2);
        ctx.fill();

        // Cheveux longs qui flottent
        ctx.fillStyle = C.hair;
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2, y + 4, 11, Math.PI, 0);
        ctx.fill();
        // Mèches qui volent vers l'arrière
        const hairWave = run * 4 + (air ? -8 : 0);
        ctx.beginPath();
        ctx.moveTo(x + 3, y + 2);
        ctx.quadraticCurveTo(x - 10 + hairWave, y + 10, x - 16 + hairWave, y + 22);
        ctx.quadraticCurveTo(x - 12 + hairWave, y + 28, x - 8 + hairWave, y + 18);
        ctx.quadraticCurveTo(x - 4 + hairWave, y + 8, x + 3, y + 5);
        ctx.closePath();
        ctx.fill();

        // Chapeau de soleil
        ctx.fillStyle = C.hat;
        ctx.beginPath();
        ctx.ellipse(x + CHAR_W / 2, y - 7, 18, 4, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2, y - 9, 10, Math.PI, 0);
        ctx.fill();
        // Ruban
        ctx.fillStyle = C.hatRib;
        ctx.fillRect(x + CHAR_W / 2 - 10, y - 11, 20, 3);
        // Fleur sur le chapeau
        ctx.fillStyle = C.hatFlower;
        const fx = x + CHAR_W / 2 + 6;
        const fy = y - 14;
        for (let p = 0; p < 5; p++) {
            const angle = (p / 5) * Math.PI * 2;
            ctx.beginPath();
            ctx.arc(fx + Math.cos(angle) * 3.5, fy + Math.sin(angle) * 3.5, 2.5, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.fillStyle = '#FFE566';
        ctx.beginPath(); ctx.arc(fx, fy, 2.5, 0, Math.PI * 2); ctx.fill();

        // Visage
        ctx.fillStyle = C.textDark;
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2 - 3.5, y + 5, 1.6, 0, Math.PI * 2);
        ctx.arc(x + CHAR_W / 2 + 3.5, y + 5, 1.6, 0, Math.PI * 2);
        ctx.fill();

        // Joues roses
        ctx.fillStyle = 'rgba(220,100,100,0.3)';
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2 - 6, y + 7, 3.5, 0, Math.PI * 2);
        ctx.arc(x + CHAR_W / 2 + 6, y + 7, 3.5, 0, Math.PI * 2);
        ctx.fill();

        // Sourire / grimace
        ctx.strokeStyle = C.textDark;
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        if (state === 'dead') {
            ctx.arc(x + CHAR_W / 2, y + 10, 3.5, Math.PI, 0, true);
        } else {
            ctx.arc(x + CHAR_W / 2, y + 8, 3.5, 0.1, Math.PI - 0.1);
        }
        ctx.stroke();
    }

    // ── Envoi score au serveur ────────────────────────────────────
    function submitScore(s) {
        fetch('/api/jeux/score', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ game: 'vendangeuse', score: s }),
        })
            .then((r) => r.json())
            .then((data) => { if (data.record !== undefined) worldRecord = data.record; })
            .catch(() => { /* score non envoyé — pas bloquant */ });
    }

    // ── HUD ───────────────────────────────────────────────────────
    function drawHUD() {
        ctx.textAlign = 'right';
        ctx.fillStyle = C.gold;
        ctx.font      = 'bold 16px Georgia, serif';
        ctx.fillText(String(score).padStart(5, '0'), canvas.width - 18, 28);
        ctx.fillStyle = C.goldDark;
        ctx.font      = '12px Georgia, serif';
        if (hiScore > 0) {
            ctx.fillText(`HI  ${String(hiScore).padStart(5, '0')}`, canvas.width - 18, 46);
        }
        if (worldRecord > 0) {
            ctx.fillText(`WR  ${String(worldRecord).padStart(5, '0')}`, canvas.width - 18, 62);
        }
        ctx.textAlign = 'left';
    }

    // ── Bouton pause (⏸/▶) ───────────────────────────────────────
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

    // ── Bouton ▶ Jouer / ↺ Rejouer — overlay idle/dead/pause ────
    function drawPlayBtn(label = '▶  Jouer') {
        const bw = 140, bh = 40;
        const bx = canvas.width / 2 - bw / 2;
        const by = H / 2 + 52;
        ctx.fillStyle = C.gold;
        ctx.beginPath();
        if (ctx.roundRect) ctx.roundRect(bx, by, bw, bh, 6); else ctx.rect(bx, by, bw, bh);
        ctx.fill();
        ctx.fillStyle = C.textDark;
        ctx.font      = 'bold 17px Georgia, serif';
        ctx.textAlign = 'center';
        ctx.fillText(label, canvas.width / 2, by + 27);
        ctx.textAlign = 'left';
    }

    // ── Overlay ───────────────────────────────────────────────────
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

    // ── Update ────────────────────────────────────────────────────
    function update() {
        if (state !== 'running') return;

        frame++;
        score   = Math.floor(frame / 7);
        speed   = 5 + score * 0.005;
        hillOff += speed * 0.4;

        // Physique
        char.vy += GRAVITY;
        char.y  += char.vy;
        if (char.y >= GROUND_Y - CHAR_H) {
            char.y        = GROUND_Y - CHAR_H;
            char.vy       = 0;
            char.grounded = true;
        }

        // Nuages
        clouds.forEach((c) => {
            c.x -= speed * 0.22;
            if (c.x + c.r * 2 < 0) c.x = canvas.width + c.r * 2;
        });

        // Tracteurs
        nextTractor--;
        if (nextTractor <= 0) {
            spawnTractor();
            nextTractor = 400 + Math.floor(Math.random() * 300);
        }
        tractors.forEach((t) => { t.x -= t.speed; });
        tractors = tractors.filter((t) => t.x + 80 > 0);

        // Obstacles
        nextSpawn--;
        if (nextSpawn <= 0) {
            spawnObstacle();
            const base = Math.max(42, 100 - Math.floor(score / 80) * 8);
            nextSpawn  = base + Math.floor(Math.random() * 48);
        }
        obstacles.forEach((o) => { o.x -= speed; });
        obstacles = obstacles.filter((o) => o.x + o.w > 0);

        // Collision
        const hb = { x: CHAR_X + 8, y: char.y + 8, w: CHAR_W - 14, h: CHAR_H - 14 };
        for (const o of obstacles) {
            const oy = GROUND_Y - o.h;
            if (hb.x < o.x + o.w && hb.x + hb.w > o.x && hb.y < oy + o.h && hb.y + hb.h > oy) {
                if (score > hiScore) hiScore = score;
                if (score > worldRecord) submitScore(score);
                state = 'dead';
                return;
            }
        }
    }

    // ── Draw ──────────────────────────────────────────────────────
    function draw() {
        ctx.clearRect(0, 0, canvas.width, H);
        drawBackground();
        tractors.forEach(drawTractor);   // tracteurs dans le fond
        obstacles.forEach(drawVigne);
        drawVendangeuse();
        drawHUD();
        drawPauseBtn();

        if (state === 'idle') {
            drawOverlay([
                { text: '— Sautez par-dessus les vignes ! —',  size: 14, color: '#F5F0E8', dy: -20 },
                { text: 'Espace / clic pour sauter',           size: 13, color: C.gold,    dy:   4 },
            ]);
            drawPlayBtn();
        }
        if (state === 'paused') {
            drawOverlay([
                { text: '⏸  Pause',                        size: 22, color: '#F5F0E8', dy: -14 },
                { text: 'Cliquez ▶ ou P pour reprendre',   size: 13, color: C.gold,    dy:  14 },
            ]);
            drawPlayBtn();
        }
        if (state === 'dead') {
            const newRecord = score >= hiScore && score > 0;
            drawOverlay([
                { text: `Score : ${score}`,                                             size: 22, color: '#F5F0E8', dy: -22 },
                { text: newRecord ? '✦ Nouveau record ! ✦' : `Meilleur : ${hiScore}`, size: 14, color: C.gold,    dy:   6 },
            ]);
            drawPlayBtn('↺  Rejouer');
        }
    }

    // ── Boucle ────────────────────────────────────────────────────
    function loop() {
        update();
        draw();
        animId = requestAnimationFrame(loop);
    }

    reset();
    loop();
}
