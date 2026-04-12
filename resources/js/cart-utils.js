/**
 * cart-utils.js — Fonctions utilitaires pures pour le panier hors-connexion.
 *
 * Ce module exporte uniquement des fonctions sans dépendance DOM ni cookie,
 * afin de permettre les tests unitaires et le mutation testing.
 */

export const CART_KEY     = 'cb-cart';
export const CART_MAX_AGE = 7 * 24 * 3600; // 7 jours

/**
 * Analyse une chaîne de cookies et retourne le tableau des items du panier.
 *
 * @param {string} cookieString - Valeur de document.cookie
 * @returns {Array<{id: number|string, qty: number}>}
 */
export function parseCartFromCookie(cookieString) {
    const match = cookieString.split('; ').find((c) => c.startsWith(CART_KEY + '='));
    if (!match) return [];
    try {
        return JSON.parse(decodeURIComponent(match.slice(CART_KEY.length + 1)));
    } catch {
        return [];
    }
}

/**
 * Sérialise le panier en chaîne cookie.
 *
 * @param {Array<{id: number|string, qty: number}>} cart
 * @param {string} [path='/']
 * @param {number} [maxAge=CART_MAX_AGE]
 * @returns {string} Valeur à assigner à document.cookie
 */
export function serializeCartCookie(cart, path = '/', maxAge = CART_MAX_AGE) {
    const val = encodeURIComponent(JSON.stringify(cart));
    return `${CART_KEY}=${val}; path=${path}; max-age=${maxAge}; SameSite=Lax`;
}

/**
 * Ajoute ou met à jour un item dans le panier.
 * Retourne un nouveau tableau (immutable).
 *
 * @param {Array<{id: number|string, qty: number}>} cart - Panier existant
 * @param {{id: number|string, qty: number}} item - Item à ajouter
 * @returns {Array<{id: number|string, qty: number}>}
 */
export function mergeCartItem(cart, item) {
    const existing = cart.find((i) => i.id === item.id);
    if (existing) {
        return cart.map((i) =>
            i.id === item.id ? { ...i, qty: i.qty + item.qty } : i
        );
    }
    return [...cart, { id: item.id, qty: item.qty }];
}

/**
 * Calcule le nombre total d'articles dans le panier.
 *
 * @param {Array<{id: number|string, qty: number}>} cart
 * @returns {number}
 */
export function getCartCount(cart) {
    return cart.reduce((sum, i) => sum + (i.qty || 1), 0);
}

/**
 * Parse une chaîne de prix formatée en français vers un float.
 * Exemples : "15,50 €" → 15.5 | "12.00" → 12 | null → 0
 *
 * @param {string|null|undefined} str
 * @returns {number}
 */
export function parsePrice(str) {
    return (
        Number.parseFloat(
            (str || '0')
                .replaceAll(/\s/g, '')
                .replaceAll(',', '.')
                .replaceAll('€', '')
        ) || 0
    );
}

/**
 * Calcule le total d'une ligne panier.
 *
 * @param {number} unitPrice
 * @param {number} qty
 * @returns {string} Montant formaté "15,50"
 */
export function calcLineTotal(unitPrice, qty) {
    return (unitPrice * qty).toFixed(2).replace('.', ',');
}
