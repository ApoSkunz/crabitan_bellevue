/**
 * Jeu Trial — Le Tracteur des Vignes.
 * Canvas 2D inspiré de Trials / Hill Climb Racing.
 * Espace/→ = gaz  |  ← = frein  |  ↑ = pencher avant  |  ↓ = pencher arrière
 * Maintenez l'équilibre du tracteur sur un terrain vallonné.
 */

export function initHillClimbGame() {
    const section = document.getElementById('hill-climb-game');
    if (!section) return;

    const canvas = /** @type {HTMLCanvasElement} */ (document.getElementById('hill-climb-canvas'));
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // ── Constantes ────────────────────────────────────────────
    const H          = 280;
    const WHEEL_BASE = 56;   // distance entre centres des roues
    const R_BACK     = 22;   // rayon grande roue arrière
    const R_FRONT    = 14;   // rayon petite roue avant
    const TIP_LEAN   = 1.05; // rad max avant renversement
    const STIFFNESS  = 0.018;
    const DAMPING    = 0.80;
    const GAS_TORQUE = 0.006;
    const GRAV_TORQUE= 0.065;
    const LEAN_TORQUE= 0.014;

    const C = {
        sky:     '#EFF4E8',
        ground:  '#6B4F2A',
        grass:   '#5A8A1A',
        hill:    '#C8D49C',
        tractor: '#C8391A',
        tractorB:'#9A2810',
        cab:     '#E05030',
        wheel:   '#2C2010',
        rim:     '#5C4820',
        exhaust: '#5C5C5C',
        skin:    '#D4956A',
        hat:     '#3A6B1A',
        cloud:   '#E0D8C8',
        gold:    '#C9A96E',
        goldDk:  '#A07820',
        txt:     '#3A2A1A',
        vine:    '#4A7A28',
        overlay: 'rgba(42,28,14,0.62)',
    };

    function resizeCanvas() {
        canvas.width  = Math.min(section.clientWidth, 900);
        canvas.height = H;
    }
    resizeCanvas();
    window.addEventListener('resize', () => {
        resizeCanvas();
        if (state !== 'running') draw();
    });

    // ── Terrain ───────────────────────────────────────────────
    function terrainY(wx) {
        const e = Math.min(1, wx / 700);
        return 188
            + Math.sin(wx * 0.012) * 32 * e
            + Math.sin(wx * 0.036 + 1.1) * 16 * e
            + Math.sin(wx * 0.007 + 0.4) * 46 * e
            + Math.sin(wx * 0.075 + 2.0) * 9  * e;
    }

    function terrainSlope(wx) {
        const d = 3;
        return Math.atan2(terrainY(wx + d) - terrainY(wx - d), d * 2);
    }

    // ── État ──────────────────────────────────────────────────
    let state       = 'idle';
    let worldX      = 0;
    let velocity    = 0;
    let bodyLean    = 0;  // >0 = bascule arrière, <0 = avant
    let angVel      = 0;
    let gas         = false;
    let brake       = false;
    let leanFwd     = false;
    let leanBck     = false;
    let score       = 0;
    let hiScore     = 0;
    let worldRecord = parseInt(canvas.dataset.worldRecord, 10) || 0;
    let frame       = 0;
    let animId;     // eslint-disable-line no-unused-vars
    let clouds      = [];

    function resetClouds() {
        clouds = [
            { offX: 120, y: 26, r: 26 },
            { offX: 380, y: 17, r: 20 },
            { offX: 660, y: 34, r: 16 },
        ];
    }

    function reset() {
        worldX   = 0; velocity = 0; bodyLean = 0; angVel = 0;
        score    = 0; frame    = 0;
        gas = false; brake = false; leanFwd = false; leanBck = false;
        resetClouds();
    }

    // ── Input ─────────────────────────────────────────────────
    let sectionVisible = false;
    new IntersectionObserver(
        ([entry]) => { sectionVisible = entry.isIntersecting; },
        { threshold: 0.2 }
    ).observe(section);

    function startOrGas() {
        if (state !== 'running') { reset(); state = 'running'; }
        gas = true;
    }

    canvas.setAttribute('tabindex', '0');
    canvas.addEventListener('mousedown',   () => startOrGas());
    canvas.addEventListener('mouseup',     () => { gas = false; });
    canvas.addEventListener('mouseleave',  () => { gas = false; });
    canvas.addEventListener('touchstart',  (e) => { e.preventDefault(); startOrGas(); }, { passive: false });
    canvas.addEventListener('touchend',    () => { gas = false; });

    document.addEventListener('keydown', (e) => {
        if (!sectionVisible && state === 'idle') return;
        switch (e.code) {
            case 'Space':
            case 'ArrowRight': e.preventDefault(); startOrGas(); break;
            case 'ArrowLeft':  e.preventDefault(); brake   = true; gas = false; break;
            case 'ArrowUp':    e.preventDefault(); leanFwd = true;  break;
            case 'ArrowDown':  e.preventDefault(); leanBck = true;  break;
            default: break;
        }
    });
    document.addEventListener('keyup', (e) => {
        switch (e.code) {
            case 'Space':
            case 'ArrowRight': gas    = false; break;
            case 'ArrowLeft':  brake  = false; break;
            case 'ArrowUp':    leanFwd = false; break;
            case 'ArrowDown':  leanBck = false; break;
            default: break;
        }
    });

    // ── Soumission du score ────────────────────────────────────
    function submitScore(s) {
        fetch('/api/jeux/score', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ game: 'tracteur', score: s }),
        })
            .then((r) => r.json())
            .then((d) => { if (d.record !== undefined) worldRecord = d.record; })
            .catch(() => {});
    }

    // ── Fond ──────────────────────────────────────────────────
    function drawBackground(camX) {
        ctx.fillStyle = C.sky;
        ctx.fillRect(0, 0, canvas.width, H);

        ctx.fillStyle = C.cloud;
        clouds.forEach((c) => {
            const wrap = canvas.width + c.r * 5;
            const cx   = ((c.offX - camX * 0.13) % wrap + wrap) % wrap - c.r * 2;
            ctx.beginPath();
            ctx.arc(cx, c.y, c.r, 0, Math.PI * 2);
            ctx.arc(cx + c.r * 0.8,  c.y + 6, c.r * 0.65, 0, Math.PI * 2);
            ctx.arc(cx - c.r * 0.65, c.y + 5, c.r * 0.55, 0, Math.PI * 2);
            ctx.fill();
        });

        ctx.fillStyle = C.hill;
        ctx.beginPath();
        ctx.moveTo(-4, H);
        for (let cx = -4; cx <= canvas.width + 4; cx += 10) {
            const hx = cx + camX * 0.35;
            ctx.lineTo(cx, 150 + Math.sin(hx * 0.009) * 28 + Math.sin(hx * 0.022) * 16);
        }
        ctx.lineTo(canvas.width + 4, H);
        ctx.closePath();
        ctx.fill();
    }

    // ── Terrain ────────────────────────────────────────────────
    function drawTerrain(camX) {
        ctx.beginPath();
        let first = true;
        for (let cx = -4; cx <= canvas.width + 4; cx += 5) {
            const ty = terrainY(cx + camX);
            if (first) { ctx.moveTo(cx, ty); first = false; }
            else ctx.lineTo(cx, ty);
        }
        ctx.lineTo(canvas.width + 4, H);
        ctx.lineTo(-4, H);
        ctx.closePath();
        ctx.fillStyle = C.ground;
        ctx.fill();

        ctx.beginPath();
        first = true;
        for (let cx = -4; cx <= canvas.width + 4; cx += 5) {
            const ty = terrainY(cx + camX);
            if (first) { ctx.moveTo(cx, ty); first = false; }
            else ctx.lineTo(cx, ty);
        }
        ctx.strokeStyle = C.grass;
        ctx.lineWidth   = 5;
        ctx.stroke();

        // Piquets de vigne
        const SPACING    = 88;
        const firstStake = Math.floor((camX - 20) / SPACING) * SPACING;
        for (let wx = firstStake; wx <= camX + canvas.width + 20; wx += SPACING) {
            const scx = wx - camX;
            const sty = terrainY(wx);
            ctx.strokeStyle = C.vine;
            ctx.lineWidth   = 2.5;
            ctx.beginPath(); ctx.moveTo(scx, sty - 2); ctx.lineTo(scx, sty - 24); ctx.stroke();
            ctx.strokeStyle = 'rgba(74,122,40,0.28)';
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(scx - 44, sty - 20); ctx.lineTo(scx + 44, sty - 20); ctx.stroke();
        }
    }

    // ── Tracteur ──────────────────────────────────────────────
    function drawTractor(tractorCX, backWY, frontWY, visualAngle) {
        const frontCX = tractorCX + WHEEL_BASE;
        const midCX   = tractorCX + WHEEL_BASE / 2;
        const midCY   = (backWY + frontWY) / 2 - 8;

        ctx.save();
        ctx.translate(midCX, midCY);
        ctx.rotate(visualAngle);

        const bw = WHEEL_BASE * 0.9;
        const bh = 18;

        ctx.fillStyle = C.tractor;
        ctx.fillRect(-bw / 2, -bh / 2, bw, bh);

        // Capot moteur (avant = droite)
        ctx.fillStyle = C.cab;
        ctx.fillRect(bw / 2 - 18, -bh / 2 - 10, 17, bh + 6);

        // Tuyau d'échappement
        ctx.fillStyle = C.exhaust;
        ctx.fillRect(bw / 2 - 8, -bh / 2 - 22, 4, 14);
        if (frame % 12 < 6 && gas) {
            ctx.fillStyle = 'rgba(140,140,140,0.28)';
            ctx.beginPath(); ctx.arc(bw / 2 - 6, -bh / 2 - 26, 5, 0, Math.PI * 2); ctx.fill();
        }

        // Cabine (arrière = gauche)
        ctx.fillStyle = C.tractorB;
        ctx.fillRect(-bw / 2 + 3, -bh / 2 - 20, 26, 20);
        ctx.fillStyle = 'rgba(180,220,255,0.4)';
        ctx.fillRect(-bw / 2 + 5, -bh / 2 - 18, 22, 14);

        // Conducteur — tête
        ctx.fillStyle = C.skin;
        ctx.beginPath(); ctx.arc(-bw / 2 + 15, -bh / 2 - 28, 7, 0, Math.PI * 2); ctx.fill();
        // Chapeau
        ctx.fillStyle = C.hat;
        ctx.beginPath(); ctx.ellipse(-bw / 2 + 15, -bh / 2 - 35, 9, 2.5, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillRect(-bw / 2 + 9, -bh / 2 - 40, 12, 6);
        // Yeux
        ctx.fillStyle = C.txt;
        ctx.beginPath();
        ctx.arc(-bw / 2 + 12, -bh / 2 - 29, 1.4, 0, Math.PI * 2);
        ctx.arc(-bw / 2 + 17, -bh / 2 - 29, 1.4, 0, Math.PI * 2);
        ctx.fill();

        ctx.restore();

        // Roue arrière (grande)
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(tractorCX, backWY, R_BACK, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.rim;
        ctx.beginPath(); ctx.arc(tractorCX, backWY, R_BACK * 0.62, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(tractorCX, backWY, R_BACK * 0.28, 0, Math.PI * 2); ctx.fill();

        // Roue avant (petite)
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(frontCX, frontWY, R_FRONT, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.rim;
        ctx.beginPath(); ctx.arc(frontCX, frontWY, R_FRONT * 0.60, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.wheel;
        ctx.beginPath(); ctx.arc(frontCX, frontWY, R_FRONT * 0.28, 0, Math.PI * 2); ctx.fill();
    }

    // ── HUD ───────────────────────────────────────────────────
    function drawHUD() {
        ctx.textAlign = 'right';
        ctx.fillStyle = C.gold;
        ctx.font      = 'bold 16px Georgia, serif';
        ctx.fillText(`${String(score).padStart(5, '0')} m`, canvas.width - 18, 28);
        ctx.fillStyle = C.goldDk;
        ctx.font      = '12px Georgia, serif';
        if (hiScore > 0) ctx.fillText(`HI  ${String(hiScore).padStart(5, '0')} m`, canvas.width - 18, 46);
        if (worldRecord > 0) ctx.fillText(`WR  ${String(worldRecord).padStart(5, '0')} m`, canvas.width - 18, 62);

        if (state === 'running') {
            ctx.textAlign = 'left';
            ctx.fillStyle = C.goldDk;
            ctx.font      = '11px Georgia, serif';
            const parts = [];
            if (gas)     parts.push('⛽ GAZ');
            if (brake)   parts.push('🛑 FREIN');
            if (leanFwd) parts.push('↑ AVANT');
            if (leanBck) parts.push('↓ ARRIÈRE');
            if (parts.length) {
                ctx.fillStyle = C.gold;
                ctx.fillText(parts.join('  '), 14, 26);
            }
        }
        ctx.textAlign = 'left';
    }

    // ── Overlay ───────────────────────────────────────────────
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

    // ── Update ────────────────────────────────────────────────
    function update() {
        if (state !== 'running') return;
        frame++;

        const slope = terrainSlope(worldX + WHEEL_BASE / 2);

        // Vitesse
        if (gas)   velocity += 0.085 * (1 - Math.max(0, -slope) * 0.4);
        if (brake) velocity -= 0.14;
        if (!gas && !brake) velocity *= 0.97;
        velocity -= Math.sin(slope) * 0.16;
        velocity  = Math.max(-1.0, Math.min(velocity, 6.5));
        worldX   += velocity;
        if (worldX < 0) { worldX = 0; velocity = 0; }

        score = Math.max(score, Math.floor(worldX / 10));

        // Physique d'inclinaison
        let torque = 0;
        torque -= slope * GRAV_TORQUE;                           // gravité sur la pente
        if (gas)     torque += GAS_TORQUE * Math.max(velocity, 0); // couple moteur
        if (leanFwd) torque -= LEAN_TORQUE;
        if (leanBck) torque += LEAN_TORQUE;
        torque -= bodyLean * STIFFNESS;                          // rappel élastique

        angVel   += torque;
        angVel   *= DAMPING;
        bodyLean += angVel;

        // Renversement
        if (Math.abs(bodyLean) > TIP_LEAN) {
            if (score > hiScore) hiScore = score;
            if (score > worldRecord) submitScore(score);
            state = 'dead';
        }
    }

    // ── Draw ──────────────────────────────────────────────────
    function draw() {
        ctx.clearRect(0, 0, canvas.width, H);

        const tractorCX = Math.floor(canvas.width * 0.22);
        const camX      = worldX - tractorCX;

        drawBackground(camX);
        drawTerrain(camX);

        if (state !== 'idle') {
            const backWY     = terrainY(worldX) - R_BACK;
            const frontWY    = terrainY(worldX + WHEEL_BASE) - R_FRONT;
            const slopeMid   = terrainSlope(worldX + WHEEL_BASE / 2);
            const visualAngle = slopeMid - bodyLean; // bodyLean>0 = bascule arrière
            drawTractor(tractorCX, backWY, frontWY, visualAngle);
        }

        drawHUD();

        if (state === 'idle') {
            drawOverlay([
                { text: 'Espace/→ = Gaz   ←  = Frein',          size: 15, color: '#F5F0E8', dy: -26 },
                { text: '↑ = Pencher avant   ↓ = Pencher arrière', size: 14, color: '#F5F0E8', dy:  -2 },
                { text: '— Ne renversez pas le tracteur ! —',      size: 13, color: C.gold,    dy:  24 },
            ]);
        }

        if (state === 'dead') {
            const newRecord = score >= hiScore && score > 0;
            drawOverlay([
                { text: `Distance : ${score} m`,                                          size: 22, color: '#F5F0E8', dy: -22 },
                { text: newRecord ? '✦ Nouveau record ! ✦' : `Meilleur : ${hiScore} m`,  size: 14, color: C.gold,    dy:   6 },
                { text: 'Cliquez ou Espace pour rejouer',                                 size: 13, color: '#D4C89A', dy:  34 },
            ]);
        }
    }

    // ── Boucle ────────────────────────────────────────────────
    function loop() {
        update();
        draw();
        animId = requestAnimationFrame(loop);
    }

    reset();
    loop();
}
