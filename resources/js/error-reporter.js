/**
 * error-reporter.js — Capture des erreurs JavaScript non gérées.
 *
 * Écoute window.onerror et window.onunhandledrejection.
 * En mode debug (window.__appDebug === true) : console.error uniquement.
 * Sinon : POST vers /api/log/client-error.
 *
 * Les erreurs d'extensions navigateur (source vide ou cross-origin sans stack)
 * sont ignorées silencieusement.
 */

const isDebug = window.__appDebug === true;
const ENDPOINT = '/api/log/client-error';

/**
 * Détermine si l'erreur doit être ignorée.
 *
 * @param {string} source - URL du script source
 * @param {string} stack  - Stack trace
 * @returns {boolean}
 */
function shouldIgnore(source, stack) {
    // Pas de source ni de stack → erreur cross-origin ou extension
    if (!source && !stack) return true;
    // Source présente mais hors domaine → extension navigateur
    if (source && !source.includes(window.location.hostname)) return true;
    return false;
}

/**
 * Envoie le rapport d'erreur au serveur.
 *
 * @param {object} payload
 * @returns {void}
 */
function sendReport(payload) {
    if (isDebug) {
        // eslint-disable-next-line no-console
        console.error('[error-reporter]', payload);
        return;
    }

    try {
        navigator.sendBeacon
            ? navigator.sendBeacon(ENDPOINT, new Blob([JSON.stringify(payload)], { type: 'application/json' }))
            : fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: true,
            });
    } catch {
        // Erreur réseau — ne pas bloquer l'exécution
    }
}

window.onerror = function (message, source, lineno, colno, error) {
    const stack = error?.stack ?? '';

    if (shouldIgnore(source ?? '', stack)) return false;

    sendReport({
        message:   String(message),
        source:    String(source ?? ''),
        lineno:    Number(lineno) || 0,
        colno:     Number(colno) || 0,
        stack:     stack,
        url:       window.location.href,
        userAgent: navigator.userAgent,
    });

    return false; // Ne pas supprimer l'affichage natif du navigateur
};

window.addEventListener('unhandledrejection', function (event) {
    const reason = event.reason;
    const message = reason instanceof Error ? reason.message : String(reason ?? 'Unhandled Promise Rejection');
    const stack   = reason instanceof Error ? (reason.stack ?? '') : '';

    if (shouldIgnore('', stack)) return;

    sendReport({
        message:   message,
        source:    '',
        lineno:    0,
        colno:     0,
        stack:     stack,
        url:       window.location.href,
        userAgent: navigator.userAgent,
    });
});
