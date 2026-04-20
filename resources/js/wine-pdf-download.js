/**
 * wine-pdf-download.js
 *
 * Télécharge la fiche technique PDF avec une barre de progression.
 * Utilise fetch + ReadableStream pour un vrai pourcentage basé sur Content-Length.
 * Fallback animé si Content-Length absent.
 */

/**
 * @param {HTMLElement} bar
 * @param {HTMLElement} label
 * @param {number} pct 0–100
 */
function setProgress(bar, label, pct) {
    bar.style.width = pct + '%';
    bar.setAttribute('aria-valuenow', String(pct));
    label.textContent = pct + '%';
}

/**
 * Anime la barre de 0 → 90 % sur `duration` ms, puis s'arrête.
 * Retourne un cancel() pour stopper l'animation.
 *
 * @param {HTMLElement} bar
 * @param {HTMLElement} label
 * @param {number} duration ms
 * @return {{ cancel: () => void }}
 */
function startFakeProgress(bar, label, duration) {
    const steps    = 40;
    const interval = duration / steps;
    let   step     = 0;

    const timer = setInterval(() => {
        step++;
        // Courbe ease-out : ralentit en approchant 90 %
        const pct = Math.round(90 * (1 - Math.pow(1 - step / steps, 2)));
        setProgress(bar, label, Math.min(pct, 90));
        if (step >= steps) clearInterval(timer);
    }, interval);

    return { cancel: () => clearInterval(timer) };
}

/**
 * @param {string} url
 * @return {Promise<void>}
 */
async function fetchPdfWithProgress(url) {
    const overlay = document.getElementById('pdf-progress-overlay');
    const bar     = document.getElementById('pdf-progress-bar');
    const label   = document.getElementById('pdf-progress-label');
    const msg     = document.getElementById('pdf-progress-msg');

    if (!overlay || !bar || !label) return;

    setProgress(bar, label, 0);
    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');

    // Animation smooth démarre immédiatement — dure ~2.5 s jusqu'à 90 %
    const anim = startFakeProgress(bar, label, 2500);

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok || !response.body) {
            throw new Error('fetch_error');
        }

        const reader = response.body.getReader();
        const chunks = [];

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
        }

        anim.cancel();
        setProgress(bar, label, 100);

        const blob    = new Blob(chunks, { type: 'application/pdf' });
        const blobUrl = URL.createObjectURL(blob);
        window.open(blobUrl, '_blank', 'noopener,noreferrer');
        setTimeout(() => URL.revokeObjectURL(blobUrl), 30_000);
    } catch {
        anim.cancel();
        if (msg) msg.textContent = msg.dataset.error ?? 'Erreur — veuillez réessayer.';
    } finally {
        setTimeout(() => {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            setProgress(bar, label, 0);
            if (msg) msg.textContent = msg.dataset.default ?? '';
        }, 700);
    }
}

/**
 * Initialise le bouton de téléchargement PDF fiche technique.
 *
 * @return {void}
 */
export function initWinePdfDownload() {
    const btn = document.querySelector('.js-pdf-download');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const url = btn.dataset.href;
        if (url) fetchPdfWithProgress(url);
    });
}
