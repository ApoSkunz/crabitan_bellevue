/**
 * Jeu Tonneau Catapulte — Lancer de tonneau par le vigneron.
 * 5 lancers par partie. ↑↓ = angle. Espace = charger & lancer.
 * Avant chaque lancer : travelling vers la cible puis retour au vigneron.
 * Score = distance/2 + bonus cible (bullseye 500 / anneau 300 / bord 100).
 */

export function initTonneauCatapulteGame() {
    const section = document.getElementById('tonneau-catapulte-game');
    if (!section) return;
    const canvas = /** @type {HTMLCanvasElement} */ (document.getElementById('tonneau-catapulte-canvas'));
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
    window.addEventListener('resize', () => { resizeCanvas(); if (state !== 'flying') draw(); });

    // ── Palette ──────────────────────────────────────────────
    const C = {
        sky:      '#D0E8F0',
        skyFar:   '#E8F4D8',
        ground:   '#7AB040',
        groundDk: '#5A8820',
        soil:     '#C8A848',
        hill:     '#A0C858',
        vine:     '#4A7A28',
        vinePost: '#2A5A08',
        barrel:   '#8A4820',
        barrelHp: '#C07030',
        barrelHp2:'#E09050',
        hoop:     '#5A3010',
        skin:     '#D4956A',
        shirt:    '#4A7A28',
        overalls: '#C8A040',
        hat:      '#8B4513',
        hatBrim:  '#C8A040',
        boots:    '#3A2010',
        target1:  '#E03020',
        target2:  '#E8A020',
        target3:  '#E0D840',
        gold:     '#C9A96E',
        goldDk:   '#A07820',
        txt:      '#3A2A1A',
        overlay:  'rgba(42,28,14,0.62)',
        red:      '#C83018',
        powerLow: '#6AB040',
        powerMid: '#E0A020',
        powerHi:  '#C83018',
    };

    // ── Positions ─────────────────────────────────────────────
    const GROUND_Y     = H - 60;
    const VIG_X        = 72;
    const SHOULDER_X   = VIG_X + 6;
    const SHOULDER_Y   = GROUND_Y - 44;
    const ARM_LEN      = 26;
    const PIXELS_PER_M = 5;
    const MAX_THROWS   = 5;
    // Durées travelling (frames)
    const PREVIEW_DUR  = 100;  // focus cible
    const PANBACK_DUR  = 80;   // retour vigneron

    // ── État ─────────────────────────────────────────────────
    // idle | preview | panning | aiming | charging | flying | landed | gameover
    let state        = 'idle';
    let angle        = 45;
    let power        = 0;
    let barrel       = null;
    let camX         = 0;
    let throwsLeft   = MAX_THROWS;
    let throwScore   = 0;
    let totalScore   = 0;
    let hiScore      = 0;
    let worldRecord  = parseInt(canvas.dataset.worldRecord, 10) || 0;
    let throwAnim    = 0;
    let landedFrames = 0;
    let travelTimer  = 0;
    let target       = null;  // { worldX, r1, r2, r3 }
    let accuracyMsg  = '';
    let wasWorldRecord = false;
    let animId;               // eslint-disable-line no-unused-vars

    // ── Génération d'un lancer ────────────────────────────────
    function newThrow() {

        // Cible lointaine : 350–900 px monde (70–180 m)
        const dist = 350 + Math.random() * 550;
        target = {
            worldX: VIG_X + dist,
            r1: 22,   // bullseye  (≈4 m)
            r2: 60,   // anneau    (≈12 m)
            r3: 120,  // bord      (≈24 m)
        };

        power      = 0;
        barrel     = null;
        throwAnim  = 0;
        throwScore = 0;
        accuracyMsg = '';
        travelTimer = PREVIEW_DUR;
        state       = 'preview';
        // Caméra part de la position du vigneron
        camX        = 0;
    }

    function startGame() {
        throwsLeft     = MAX_THROWS;
        totalScore     = 0;
        wasWorldRecord = false;
        angle          = 45;
        newThrow();
        canvas.focus();
    }

    // ── Input ─────────────────────────────────────────────────
    canvas.setAttribute('tabindex', '0');

    canvas.addEventListener('keydown', (e) => {
        switch (e.code) {
            case 'ArrowUp':
                e.preventDefault();
                if (state === 'aiming') angle = Math.min(75, angle + 2);
                break;
            case 'ArrowDown':
                e.preventDefault();
                if (state === 'aiming') angle = Math.max(10, angle - 2);
                break;
            case 'Space':
                e.preventDefault();
                if (state === 'idle' || state === 'gameover') { startGame(); break; }
                // Accélérer le travelling si on appuie
                if (state === 'preview') { travelTimer = Math.min(travelTimer, 12); break; }
                if (state === 'panning') { travelTimer = Math.min(travelTimer, 10); break; }
                if (state === 'aiming')  { state = 'charging'; }
                break;
            default: break;
        }
    });

    canvas.addEventListener('keyup', (e) => {
        if (e.code === 'Space' && state === 'charging') {
            e.preventDefault();
            fire();
        }
    });

    function handlePointerDown() {
        if (state === 'idle' || state === 'gameover') { startGame(); return; }
        if (state === 'preview') { travelTimer = Math.min(travelTimer, 12); return; }
        if (state === 'panning') { travelTimer = Math.min(travelTimer, 10); return; }
        if (state === 'aiming')  { state = 'charging'; }
    }
    function handlePointerUp() {
        if (state === 'charging') fire();
    }
    canvas.addEventListener('mousedown',  () => handlePointerDown());
    canvas.addEventListener('mouseup',    () => handlePointerUp());
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); handlePointerDown(); }, { passive: false });
    canvas.addEventListener('touchend',   () => handlePointerUp());

    // ── Score API ─────────────────────────────────────────────
    function submitScore(s) {
        fetch('/api/jeux/score', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ game: 'catapulte', score: s }),
        }).then((r) => r.json())
          .then((d) => { if (d.record !== undefined) worldRecord = d.record; })
          .catch(() => {});
    }

    // ── Bras vigneron ─────────────────────────────────────────
    function armAngleRad() {
        const base = (angle * Math.PI) / 180;
        if (state === 'charging') return base + (power / 100) * 1.0;
        if (throwAnim > 0)        return base - throwAnim * 0.8;
        return base;
    }
    function handPos() {
        const rad = armAngleRad();
        return { x: SHOULDER_X + Math.cos(rad) * ARM_LEN, y: SHOULDER_Y - Math.sin(rad) * ARM_LEN };
    }

    // ── Tir ───────────────────────────────────────────────────
    function fire() {
        if (state !== 'charging') return;
        const rad  = (angle * Math.PI) / 180;
        const spd  = 4.5 + power * 0.135;
        const hand = handPos();
        barrel    = { x: hand.x, y: hand.y, vx: Math.cos(rad) * spd, vy: -Math.sin(rad) * spd };
        camX      = 0;
        throwAnim = 1.0;
        state     = 'flying';
    }

    function calcAccuracy(barrelWorldX) {
        if (!target) return 0;
        const dx = Math.abs(barrelWorldX - target.worldX);
        if (dx <= target.r1) { accuracyMsg = '🎯 BULLSEYE !  +500'; return 500; }
        if (dx <= target.r2) { accuracyMsg = '⭕ Anneau      +300'; return 300; }
        if (dx <= target.r3) { accuracyMsg = '〇 Bord cible  +100'; return 100; }
        accuracyMsg = '';
        return 0;
    }

    function land() {
        const dist     = Math.max(0, Math.round((barrel.x - VIG_X) / PIXELS_PER_M));
        const accuracy = calcAccuracy(barrel.x);
        throwScore     = Math.round(dist / 2) + accuracy;
        totalScore    += throwScore;
        state          = 'landed';
        landedFrames   = 120;
    }

    // ── Update ────────────────────────────────────────────────
    function update() {
        if (state === 'idle' || state === 'gameover') return;

        // ── Travelling : focus sur la cible ──────────────────
        if (state === 'preview') {
            // Caméra glisse vers la cible (centre du canvas au-dessus de la cible)
            const destCamX = Math.max(0, target.worldX - canvas.width * 0.5);
            camX += (destCamX - camX) * 0.07;
            travelTimer--;
            if (travelTimer <= 0) {
                travelTimer = PANBACK_DUR;
                state = 'panning';
            }
            return;
        }

        // ── Travelling : retour au vigneron ──────────────────
        if (state === 'panning') {
            camX += (0 - camX) * 0.10;
            travelTimer--;
            if (travelTimer <= 0 || camX < 1) {
                camX  = 0;
                state = 'aiming';
            }
            return;
        }

        if (state === 'charging') power = Math.min(100, power + 1.5);
        if (throwAnim > 0) throwAnim = Math.max(0, throwAnim - 0.05);

        if (state === 'flying' && barrel) {
            barrel.vy += 0.30;
            barrel.x  += barrel.vx;
            barrel.y  += barrel.vy;

            const screenX = barrel.x - camX;
            if (screenX > canvas.width * 0.4) camX = barrel.x - canvas.width * 0.4;
            if (camX < 0) camX = 0;

            if (barrel.y >= GROUND_Y) {
                barrel.y  = GROUND_Y;
                barrel.vy = -barrel.vy * 0.36;
                barrel.vx *= 0.70;
                if (Math.abs(barrel.vy) < 1.2 && Math.abs(barrel.vx) < 0.8) land();
            }
        }

        if (state === 'landed') {
            landedFrames--;
            if (landedFrames <= 0) {
                throwsLeft--;
                if (throwsLeft <= 0) {
                    if (totalScore > hiScore) hiScore = totalScore;
                    wasWorldRecord = totalScore > worldRecord;
                    if (wasWorldRecord) submitScore(totalScore);
                    state = 'gameover';
                } else {
                    newThrow();
                }
            }
        }
    }

    // ── Draw helpers ──────────────────────────────────────────
    function worldToScreen(wx) { return wx - camX; }

    function drawSky() {
        const grad = ctx.createLinearGradient(0, 0, 0, GROUND_Y);
        grad.addColorStop(0, C.sky);
        grad.addColorStop(1, C.skyFar);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, canvas.width, GROUND_Y);
    }

    function drawGround() {
        ctx.fillStyle = C.ground;
        ctx.fillRect(0, GROUND_Y, canvas.width, H - GROUND_Y);
        ctx.fillStyle = C.groundDk;
        ctx.fillRect(0, GROUND_Y, canvas.width, 4);

        const step = 32;
        const off  = camX % step;
        ctx.strokeStyle = C.soil;
        ctx.lineWidth   = 2;
        for (let x = -off; x < canvas.width + step; x += step) {
            ctx.beginPath(); ctx.moveTo(x, GROUND_Y + 4); ctx.lineTo(x, H); ctx.stroke();
        }

        const vineStep = 80;
        const vineOff  = (camX * 0.6) % vineStep;
        for (let x = -vineOff; x < canvas.width + vineStep; x += vineStep) {
            ctx.strokeStyle = C.vinePost;
            ctx.lineWidth   = 2.5;
            ctx.beginPath(); ctx.moveTo(x, GROUND_Y - 4); ctx.lineTo(x, GROUND_Y - 32); ctx.stroke();
            ctx.strokeStyle = 'rgba(74,122,40,0.3)';
            ctx.lineWidth   = 1;
            ctx.beginPath(); ctx.moveTo(x - 40, GROUND_Y - 22); ctx.lineTo(x + 40, GROUND_Y - 22); ctx.stroke();
        }

        ctx.fillStyle = C.hill;
        ctx.beginPath(); ctx.moveTo(-4, GROUND_Y);
        const hoff = camX * 0.22;
        for (let x = -4; x <= canvas.width + 4; x += 8) {
            ctx.lineTo(x, GROUND_Y - 16 - Math.sin((x + hoff) * 0.016) * 14 - Math.sin((x + hoff) * 0.038) * 8);
        }
        ctx.lineTo(canvas.width + 4, GROUND_Y); ctx.closePath(); ctx.fill();
    }

    function drawTarget() {
        if (!target) return;
        const tx = worldToScreen(target.worldX);
        if (tx < -target.r3 - 20 || tx > canvas.width + target.r3) return;

        // Anneaux au sol (ellipses aplaties = perspective latérale)
        [[target.r3, C.target3], [target.r2, C.target2], [target.r1, C.target1]].forEach(([r, col]) => {
            ctx.fillStyle   = col + '44';
            ctx.strokeStyle = col;
            ctx.lineWidth   = 2;
            ctx.beginPath(); ctx.ellipse(tx, GROUND_Y, r, r * 0.18, 0, 0, Math.PI * 2);
            ctx.fill(); ctx.stroke();
        });

        // Distance indicative
        const mDist = Math.round((target.worldX - VIG_X) / PIXELS_PER_M);
        ctx.fillStyle   = C.gold;
        ctx.font        = 'bold 12px Georgia, serif';
        ctx.textAlign   = 'center';
        ctx.fillText(`${mDist} m`, tx, GROUND_Y - target.r3 - 10);
        ctx.textAlign   = 'left';

        // Flèche pendant travelling
        if (state === 'preview') {
            ctx.fillStyle = C.target1;
            ctx.font      = '20px Georgia, serif';
            ctx.textAlign = 'center';
            ctx.fillText('▼', tx, GROUND_Y - target.r3 - 28);
            ctx.textAlign = 'left';
        }
    }

    function drawBarrel(bx, by) {
        ctx.save();
        ctx.translate(bx, by);
        ctx.fillStyle = C.barrelHp;
        ctx.beginPath(); ctx.ellipse(0, 0, 13, 9, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.barrelHp2;
        ctx.beginPath(); ctx.ellipse(-3, -3, 9, 5.5, 0, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = C.hoop;
        ctx.lineWidth   = 2;
        [-3.5, 0, 3.5].forEach((dy) => {
            ctx.beginPath(); ctx.ellipse(0, dy, 13, 3.5, 0, 0, Math.PI * 2); ctx.stroke();
        });
        ctx.fillStyle = C.barrel;
        ctx.beginPath(); ctx.ellipse(-13, 0, 2.5, 9, 0, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.ellipse(13, 0, 2.5, 9, 0, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    }

    function drawVigneron() {
        // Vigneron non visible pendant le focus cible éloigné
        const fx = worldToScreen(VIG_X);
        if (fx < -60 || fx > canvas.width + 20) return;

        const fy  = GROUND_Y;
        const rad = armAngleRad();

        ctx.fillStyle = 'rgba(0,0,0,0.12)';
        ctx.beginPath(); ctx.ellipse(fx, fy + 2, 14, 4, 0, 0, Math.PI * 2); ctx.fill();

        ctx.fillStyle = C.boots;
        ctx.fillRect(fx - 12, fy - 10, 10, 10);
        ctx.fillRect(fx + 2,  fy - 10, 10, 10);

        ctx.fillStyle = C.overalls;
        ctx.fillRect(fx - 10, fy - 30, 8, 22);
        ctx.fillRect(fx + 2,  fy - 30, 8, 22);

        ctx.strokeStyle = C.overalls;
        ctx.lineWidth   = 3;
        ctx.beginPath(); ctx.moveTo(fx - 6, fy - 30); ctx.lineTo(fx - 2, fy - 46); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(fx + 6, fy - 30); ctx.lineTo(fx + 2, fy - 46); ctx.stroke();

        ctx.fillStyle = C.shirt;
        ctx.fillRect(fx - 12, fy - 50, 24, 22);

        ctx.strokeStyle = C.shirt;
        ctx.lineWidth   = 7;
        ctx.lineCap     = 'round';
        ctx.beginPath(); ctx.moveTo(fx - 10, fy - 46); ctx.lineTo(fx - 16, fy - 28); ctx.stroke();
        ctx.fillStyle = C.skin;
        ctx.beginPath(); ctx.arc(fx - 16, fy - 26, 5, 0, Math.PI * 2); ctx.fill();

        const sx = worldToScreen(SHOULDER_X);
        const hx = sx + Math.cos(rad) * ARM_LEN;
        const hy = SHOULDER_Y - Math.sin(rad) * ARM_LEN;
        ctx.strokeStyle = C.shirt;
        ctx.lineWidth   = 7;
        ctx.beginPath(); ctx.moveTo(sx, SHOULDER_Y); ctx.lineTo(hx, hy); ctx.stroke();
        ctx.fillStyle = C.skin;
        ctx.beginPath(); ctx.arc(hx, hy, 5, 0, Math.PI * 2); ctx.fill();

        if (state === 'aiming' || state === 'charging') drawBarrel(hx, hy);

        ctx.fillStyle = C.skin;
        ctx.beginPath(); ctx.arc(fx + 1, fy - 58, 9, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.txt;
        const eyeDir = (state === 'flying' || state === 'landed' || state === 'gameover') ? 1 : 0;
        ctx.beginPath();
        ctx.arc(fx + 3 + eyeDir, fy - 60, 1.5, 0, Math.PI * 2);
        ctx.arc(fx - 2 + eyeDir, fy - 60, 1.5, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = C.hat;
        ctx.fillRect(fx - 8, fy - 72, 18, 8);
        ctx.fillStyle = C.hatBrim;
        ctx.beginPath(); ctx.ellipse(fx + 1, fy - 67, 14, 3.5, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = C.vine;
        ctx.fillRect(fx - 8, fy - 66, 18, 3);

        if (state === 'aiming' || state === 'charging') {
            const baseRad = (angle * Math.PI) / 180;
            ctx.strokeStyle = 'rgba(200,169,110,0.5)';
            ctx.lineWidth   = 1;
            ctx.setLineDash([4, 4]);
            ctx.beginPath();
            ctx.moveTo(sx, SHOULDER_Y);
            ctx.lineTo(sx + Math.cos(baseRad) * (ARM_LEN + 40), SHOULDER_Y - Math.sin(baseRad) * (ARM_LEN + 40));
            ctx.stroke();
            ctx.setLineDash([]);
        }

        ctx.lineCap = 'butt';
    }

    function drawPowerBar() {
        if (state !== 'charging' && state !== 'aiming') return;
        const bw = 110, bh = 13;
        const bx = worldToScreen(VIG_X) + 22;
        const by = SHOULDER_Y - 20;
        ctx.fillStyle = 'rgba(42,28,14,0.45)';
        ctx.fillRect(bx - 2, by - 2, bw + 4, bh + 4);
        const col = power > 70 ? C.powerHi : power > 40 ? C.powerMid : C.powerLow;
        ctx.fillStyle = col;
        ctx.fillRect(bx, by, bw * power / 100, bh);
        ctx.strokeStyle = C.gold;
        ctx.lineWidth   = 1;
        ctx.strokeRect(bx, by, bw, bh);
        ctx.fillStyle   = C.gold;
        ctx.font        = '11px Georgia, serif';
        ctx.textAlign   = 'left';
        ctx.fillText(state === 'aiming' ? '[ Espace ] Charger' : '', bx, by - 4);
    }

    function drawLandedInfo() {
        if (state !== 'landed' || !barrel) return;
        const sx = worldToScreen(barrel.x);
        ctx.fillStyle = C.red;
        ctx.fillRect(sx - 1.5, GROUND_Y - 28, 3, 28);
        ctx.fillStyle = C.gold;
        ctx.beginPath(); ctx.moveTo(sx, GROUND_Y - 28); ctx.lineTo(sx + 18, GROUND_Y - 20); ctx.lineTo(sx, GROUND_Y - 12); ctx.closePath(); ctx.fill();
        ctx.fillStyle = C.txt;
        ctx.font      = 'bold 13px Georgia, serif';
        ctx.textAlign = 'left';
        ctx.fillText(`+${throwScore} pts`, sx + 6, GROUND_Y - 14);

        if (accuracyMsg) {
            ctx.fillStyle   = C.target1;
            ctx.font        = 'bold 14px Georgia, serif';
            ctx.textAlign   = 'center';
            ctx.fillText(accuracyMsg, canvas.width / 2, GROUND_Y - 46);
            ctx.textAlign   = 'left';
        }
    }

    function drawHUD() {
        ctx.fillStyle = C.gold;
        ctx.font      = '13px Georgia, serif';
        ctx.textAlign = 'left';

        if (state !== 'idle' && state !== 'gameover') {
            const done = MAX_THROWS - throwsLeft;
            ctx.fillText(`Tir ${done + 1} / ${MAX_THROWS}`, 14, 26);
        }
        if (state === 'aiming' || state === 'charging') {
            ctx.fillStyle = C.goldDk;
            ctx.font      = '11px Georgia, serif';
            ctx.fillText(`Angle : ${angle}°   ↑↓`, 14, 42);
        }


        ctx.textAlign = 'right';
        ctx.fillStyle = C.gold;
        ctx.font      = 'bold 16px Georgia, serif';
        ctx.fillText(`${totalScore} pts`, canvas.width - 14, 28);
        ctx.fillStyle = C.goldDk;
        ctx.font      = '12px Georgia, serif';
        if (hiScore > 0) ctx.fillText(`HI  ${hiScore}`, canvas.width - 14, 46);
        if (worldRecord > 0) ctx.fillText(`WR  ${worldRecord}`, canvas.width - 14, 62);
        ctx.textAlign = 'left';

        // Bandeau travelling
        if (state === 'preview') {
            ctx.fillStyle   = 'rgba(42,28,14,0.60)';
            ctx.fillRect(0, H / 2 - 18, canvas.width, 36);
            ctx.fillStyle   = C.gold;
            ctx.font        = 'bold 15px Georgia, serif';
            ctx.textAlign   = 'center';
            ctx.fillText('Prochaine cible — Espace pour passer', canvas.width / 2, H / 2 + 6);
            ctx.textAlign   = 'left';
        }
        if (state === 'panning') {
            ctx.fillStyle   = 'rgba(42,28,14,0.45)';
            ctx.fillRect(0, H / 2 - 16, canvas.width, 32);
            ctx.fillStyle   = C.goldDk;
            ctx.font        = '13px Georgia, serif';
            ctx.textAlign   = 'center';
            ctx.fillText('Prêt à lancer…', canvas.width / 2, H / 2 + 5);
            ctx.textAlign   = 'left';
        }
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

    // ── Draw principal ────────────────────────────────────────
    function draw() {
        ctx.clearRect(0, 0, canvas.width, H);
        drawSky();
        drawGround();
        drawTarget();

        if ((state === 'flying' || state === 'landed') && barrel) {
            drawBarrel(worldToScreen(barrel.x), barrel.y);
            drawLandedInfo();
        }

        drawVigneron();
        drawPowerBar();
        drawHUD();

        if (state === 'idle') {
            drawOverlay([
                { text: '5 lancers  ·  ↑↓ angle  ·  Espace = charger & lancer', size: 13, color: '#F5F0E8', dy: -16 },
                { text: '— Visez la cible pour les bonus ! —',                    size: 13, color: C.gold,    dy:  10 },
            ]);
            drawPlayBtn();
        }

        if (state === 'gameover') {
            const isPersonalRecord = totalScore >= hiScore && totalScore > 0;
            const recordMsg = wasWorldRecord
                ? '✦ Record Mondial ! ✦'
                : isPersonalRecord
                    ? '✦ Nouveau record personnel ! ✦'
                    : `Meilleur : ${hiScore} pts`;
            drawOverlay([
                { text: `Score total : ${totalScore} pts`, size: 20, color: '#F5F0E8', dy: -22 },
                { text: recordMsg,                         size: 14, color: C.gold,    dy:   6 },
            ]);
            drawPlayBtn('↺  Rejouer');
        }
    }

    // ── Boucle ────────────────────────────────────────────────
    function loop() {
        update();
        draw();
        animId = requestAnimationFrame(loop);
    }

    loop();
}
