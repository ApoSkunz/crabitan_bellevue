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

    let fallbackTimer = null;

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok || !response.body) {
            throw new Error('fetch_error');
        }

        const contentLength = response.headers.get('Content-Length');
        const total         = contentLength ? parseInt(contentLength, 10) : 0;
        const reader        = response.body.getReader();
        const chunks        = [];
        let received        = 0;

        if (total === 0) {
            // Pas de Content-Length → progression estimée sur ~3 s
            let fake = 0;
            fallbackTimer = setInterval(() => {
                fake = Math.min(fake + 4, 90);
                setProgress(bar, label, fake);
            }, 120);
        }

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
            received += value.length;
            if (total > 0) {
                setProgress(bar, label, Math.round((received / total) * 100));
            }
        }

        if (fallbackTimer !== null) clearInterval(fallbackTimer);
        setProgress(bar, label, 100);

        const blob    = new Blob(chunks, { type: 'application/pdf' });
        const blobUrl = URL.createObjectURL(blob);
        window.open(blobUrl, '_blank', 'noopener,noreferrer');
        setTimeout(() => URL.revokeObjectURL(blobUrl), 30_000);
    } catch {
        if (fallbackTimer !== null) clearInterval(fallbackTimer);
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
