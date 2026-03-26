/**
 * Jeu runner — La Vigneronne.
 * Canvas 2D inspiré du runner Chrome T-Rex.
 * La vigneronne saute par-dessus des vignes dont la vitesse augmente.
 */

export function initRunnerGame() {
    const section = document.getElementById('runner-game');
    if (!section) return;

    const canvas = /** @type {HTMLCanvasElement} */ (document.getElementById('runner-canvas'));
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // ── Palette (reprend les tokens du site) ──────────────────────
    const C = {
        sky:      '#F5F0E8',
        hill:     '#DDD4A8',
        ground:   '#6B4F2A',
        turf:     '#8B6B1A',
        vine:     '#2D5A1B',
        leaf:     '#3A7A25',
        grape:    '#6B3FA0',
        skin:     '#D4956A',
        hair:     '#5C3318',
        hat:      '#C8A84B',
        hatRib:   '#C64B2D',
        shirt:    '#C64B2D',
        apron:    '#E8C87A',
        pants:    '#4A3728',
        shoe:     '#2C1A0A',
        gold:     '#C9A96E',
        goldDark: '#A07820',
        textDark: '#3A2A1A',
        cloud:    '#E8E0D0',
        overlay:  'rgba(42,28,14,0.58)',
    };

    // ── Dimensions ────────────────────────────────────────────────
    const H          = 260;
    const GROUND_Y   = H - 55;
    const CHAR_W     = 32;
    const CHAR_H     = 54;
    const CHAR_X     = 90;
    const GRAVITY    = 0.64;
    const JUMP_V     = -13.5;

    function resizeCanvas() {
        canvas.width  = Math.min(section.clientWidth, 900);
        canvas.height = H;
    }
    resizeCanvas();
    window.addEventListener('resize', () => { resizeCanvas(); if (state !== 'running') draw(); });

    // ── État ──────────────────────────────────────────────────────
    let state     = 'idle';
    let score     = 0;
    let hiScore   = 0;
    let speed     = 5;
    let frame     = 0;
    let animId;
    let hillOff   = 0;

    const char = { y: GROUND_Y - CHAR_H, vy: 0, grounded: true };

    let obstacles = [];
    let nextSpawn = 90;
    let clouds    = [];

    // ── Reset ─────────────────────────────────────────────────────
    function reset() {
        score      = 0;
        speed      = 5;
        frame      = 0;
        obstacles  = [];
        nextSpawn  = 90;
        hillOff    = 0;
        char.y     = GROUND_Y - CHAR_H;
        char.vy    = 0;
        char.grounded = true;
        clouds = [
            { x: canvas.width * 0.25, y: 32, r: 26 },
            { x: canvas.width * 0.65, y: 20, r: 20 },
        ];
    }

    // ── Inputs ────────────────────────────────────────────────────
    function handleInput() {
        if (state === 'idle' || state === 'dead') {
            reset();
            state = 'running';
        } else if (state === 'running' && char.grounded) {
            char.vy       = JUMP_V;
            char.grounded = false;
        }
    }

    canvas.setAttribute('tabindex', '0');
    canvas.addEventListener('click', handleInput);
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); handleInput(); }, { passive: false });
    canvas.addEventListener('keydown', (e) => {
        if (e.code === 'Space' || e.code === 'ArrowUp') { e.preventDefault(); handleInput(); }
    });

    // ── Spawn obstacles ───────────────────────────────────────────
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

    // ── Dessin fond ───────────────────────────────────────────────
    function drawBackground() {
        // Ciel
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
        ctx.fillStyle = C.hill;
        const hw = canvas.width / 2;
        ctx.beginPath();
        ctx.moveTo(0, GROUND_Y + 10);
        for (let i = -1; i <= 3; i++) {
            const ox = ((i * hw) - hillOff % hw);
            ctx.quadraticCurveTo(ox + hw * 0.5, GROUND_Y - 38, ox + hw, GROUND_Y + 10);
        }
        ctx.lineTo(canvas.width, H);
        ctx.lineTo(0, H);
        ctx.closePath();
        ctx.fill();

        // Sol
        ctx.fillStyle = C.turf;
        ctx.fillRect(0, GROUND_Y, canvas.width, 7);
        ctx.fillStyle = C.ground;
        ctx.fillRect(0, GROUND_Y + 7, canvas.width, H - GROUND_Y);
    }

    // ── Dessin vigne ──────────────────────────────────────────────
    function drawVigne(obs) {
        const oy  = GROUND_Y - obs.h;
        const cx  = obs.x + obs.w / 2;

        // Tige
        ctx.fillStyle = C.vine;
        ctx.fillRect(cx - 3, oy + Math.floor(obs.h * 0.35), 6, Math.ceil(obs.h * 0.65));

        // Feuilles
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

        // Grappes
        ctx.fillStyle = C.grape;
        const gps = [[cx - 5, oy], [cx + 5, oy], [cx, oy + 9], [cx - 5, oy + 9], [cx + 5, oy + 9]];
        gps.forEach(([gx, gy]) => {
            ctx.beginPath(); ctx.arc(gx, gy, 4.5, 0, Math.PI * 2); ctx.fill();
        });
    }

    // ── Dessin vigneronne ─────────────────────────────────────────
    function drawVigneronne() {
        const x   = CHAR_X;
        const y   = char.y;
        const run = char.grounded ? Math.sin(frame * 0.28) : 0;

        // Ombre
        ctx.fillStyle = 'rgba(0,0,0,0.10)';
        ctx.beginPath();
        ctx.ellipse(x + CHAR_W / 2, GROUND_Y + 5, 15, 4, 0, 0, Math.PI * 2);
        ctx.fill();

        // Jambes
        [[x + 9, -1], [x + 21, 1]].forEach(([lx, side], i) => {
            ctx.save();
            ctx.translate(lx, y + 36);
            ctx.rotate(run * 0.32 * side);
            ctx.fillStyle = C.pants;
            ctx.fillRect(-5, 0, 10, 20);
            ctx.fillStyle = C.shoe;
            ctx.fillRect(-6, 17, 14, 6);
            ctx.restore();
        });

        // Tablier / jupe
        ctx.fillStyle = C.apron;
        ctx.beginPath();
        ctx.moveTo(x + 5, y + 22);
        ctx.lineTo(x + CHAR_W - 5, y + 22);
        ctx.lineTo(x + CHAR_W + 2, y + 40);
        ctx.lineTo(x - 2, y + 40);
        ctx.closePath();
        ctx.fill();

        // Corps (chemise)
        ctx.fillStyle = C.shirt;
        ctx.fillRect(x + 6, y + 14, CHAR_W - 12, 20);

        // Bras
        [[x + 6, -1], [x + CHAR_W - 6, 1]].forEach(([ax, side]) => {
            ctx.save();
            ctx.translate(ax, y + 18);
            ctx.rotate((-run * 0.28 * side) - 0.2 * side);
            ctx.fillStyle = C.shirt;
            ctx.fillRect(-4, 0, 8, 15);
            ctx.fillStyle = C.skin;
            ctx.fillRect(-3, 13, 7, 7);
            ctx.restore();
        });

        // Cou
        ctx.fillStyle = C.skin;
        ctx.fillRect(x + CHAR_W / 2 - 4, y + 8, 8, 8);

        // Tête
        ctx.fillStyle = C.skin;
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2, y + 5, 12, 0, Math.PI * 2);
        ctx.fill();

        // Cheveux + chignon
        ctx.fillStyle = C.hair;
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2, y + 4, 12, Math.PI, 0);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2 + 11, y + 1, 6, 0, Math.PI * 2);
        ctx.fill();

        // Chapeau de soleil (bord + calotte)
        ctx.fillStyle = C.hat;
        ctx.beginPath();
        ctx.ellipse(x + CHAR_W / 2, y - 8, 19, 4, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2, y - 10, 11, Math.PI, 0);
        ctx.fill();
        // Ruban
        ctx.fillStyle = C.hatRib;
        ctx.fillRect(x + CHAR_W / 2 - 11, y - 12, 22, 3);

        // Visage
        ctx.fillStyle = C.textDark;
        ctx.beginPath();
        ctx.arc(x + CHAR_W / 2 - 4, y + 5, 1.8, 0, Math.PI * 2);
        ctx.arc(x + CHAR_W / 2 + 4, y + 5, 1.8, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = C.textDark;
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        if (state === 'dead') {
            ctx.arc(x + CHAR_W / 2, y + 11, 4, Math.PI, 0, true);
        } else {
            ctx.arc(x + CHAR_W / 2, y + 8, 4, 0.1, Math.PI - 0.1);
        }
        ctx.stroke();
    }

    // ── HUD ───────────────────────────────────────────────────────
    function drawHUD() {
        ctx.textAlign = 'right';
        ctx.fillStyle = C.gold;
        ctx.font      = 'bold 16px Georgia, serif';
        ctx.fillText(String(score).padStart(5, '0'), canvas.width - 18, 28);
        if (hiScore > 0) {
            ctx.fillStyle = C.goldDark;
            ctx.font      = '12px Georgia, serif';
            ctx.fillText(`HI  ${String(hiScore).padStart(5, '0')}`, canvas.width - 18, 46);
        }
        ctx.textAlign = 'left';
    }

    // ── Overlay texte ─────────────────────────────────────────────
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
        speed   = 5 + score * 0.012;
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

        // Obstacles
        nextSpawn--;
        if (nextSpawn <= 0) {
            spawnObstacle();
            const base = Math.max(42, 100 - Math.floor(score / 80) * 8);
            nextSpawn  = base + Math.floor(Math.random() * 48);
        }
        obstacles.forEach((o) => { o.x -= speed; });
        obstacles = obstacles.filter((o) => o.x + o.w > 0);

        // Collision (hitbox réduite pour indulgence)
        const hb = { x: CHAR_X + 8, y: char.y + 8, w: CHAR_W - 14, h: CHAR_H - 10 };
        for (const o of obstacles) {
            const oy = GROUND_Y - o.h;
            if (hb.x < o.x + o.w && hb.x + hb.w > o.x && hb.y < oy + o.h && hb.y + hb.h > oy) {
                if (score > hiScore) hiScore = score;
                state = 'dead';
                return;
            }
        }
    }

    // ── Draw ──────────────────────────────────────────────────────
    function draw() {
        ctx.clearRect(0, 0, canvas.width, H);
        drawBackground();
        obstacles.forEach(drawVigne);
        drawVigneronne();
        drawHUD();

        if (state === 'idle') {
            drawOverlay([
                { text: 'Cliquez ou appuyez sur Espace',  size: 17, color: '#F5F0E8', dy: -18 },
                { text: 'pour démarrer',                  size: 17, color: '#F5F0E8', dy:   6 },
                { text: '— Sautez par-dessus les vignes ! —', size: 13, color: C.gold, dy: 34 },
            ]);
        }
        if (state === 'dead') {
            const newRecord = score >= hiScore;
            drawOverlay([
                { text: `Score : ${score}`,                                              size: 22, color: '#F5F0E8', dy: -22 },
                { text: newRecord ? '✦ Nouveau record ! ✦' : `Meilleur : ${hiScore}`,  size: 14, color: C.gold,    dy:   6 },
                { text: 'Cliquez ou Espace pour rejouer',                                size: 13, color: '#D4C89A', dy:  34 },
            ]);
        }
    }

    // ── Boucle ────────────────────────────────────────────────────
    function loop() {
        update();
        draw();
        animId = requestAnimationFrame(loop); // eslint-disable-line no-unused-vars
    }

    reset();
    loop();
}
