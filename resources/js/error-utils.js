/**
 * error-utils.js — Fonctions utilitaires pures pour le reporting d'erreurs JS.
 *
 * Module sans dépendance DOM/window, exporté pour les tests unitaires
 * et le mutation testing Stryker.
 */

/**
 * Détermine si une erreur JS doit être ignorée (extension navigateur, cross-origin).
 *
 * @param {string} source   - URL du script source (peut être vide)
 * @param {string} stack    - Stack trace (peut être vide)
 * @param {string} hostname - Nom d'hôte courant (window.location.hostname)
 * @returns {boolean} true si l'erreur doit être ignorée
 */
export function shouldIgnore(source, stack, hostname) {
    // Pas de source ni de stack → erreur cross-origin ou extension
    if (!source && !stack) return true;
    // Source présente mais hors domaine → extension navigateur
    if (source && !source.includes(hostname)) return true;
    return false;
}

/**
 * Construit le payload d'erreur depuis un événement window.onerror.
 *
 * @param {string} message  - Message d'erreur
 * @param {string} source   - URL source
 * @param {number} lineno   - Numéro de ligne
 * @param {number} colno    - Numéro de colonne
 * @param {Error|null} error - Objet erreur (optionnel)
 * @param {string} url      - URL de la page
 * @param {string} userAgent - User-agent
 * @returns {object}
 */
export function buildErrorPayload(message, source, lineno, colno, error, url, userAgent) {
    return {
        message:   String(message),
        source:    String(source ?? ''),
        lineno:    Number(lineno) || 0,
        colno:     Number(colno) || 0,
        stack:     error?.stack ?? '',
        url,
        userAgent,
    };
}

/**
 * Construit le payload depuis un événement unhandledrejection.
 *
 * @param {unknown} reason - Raison du rejet (Error ou valeur primitive)
 * @param {string} url
 * @param {string} userAgent
 * @returns {object}
 */
export function buildRejectionPayload(reason, url, userAgent) {
    let message;
    if (reason instanceof Error) {
        message = reason.message;
    } else if (typeof reason === 'string') {
        message = reason;
    } else if (typeof reason === 'number' || typeof reason === 'boolean') {
        message = String(reason);
    } else {
        message = 'Unhandled Promise Rejection';
    }
    const stack = reason instanceof Error ? (reason.stack ?? '') : '';

    return {
        message,
        source:    '',
        lineno:    0,
        colno:     0,
        stack,
        url,
        userAgent,
    };
}
