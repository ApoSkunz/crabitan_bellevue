/**
 * Tests unitaires — cart-utils.js
 * Exécutés avec node:test (Node ≥ 18).
 *
 * @command node --test tests/unit/js/cart-utils.test.mjs
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import {
    CART_KEY,
    CART_MAX_AGE,
    parseCartFromCookie,
    serializeCartCookie,
    mergeCartItem,
    getCartCount,
    parsePrice,
    calcLineTotal,
} from '../../../resources/js/cart-utils.js';

// ─── parseCartFromCookie ────────────────────────────────────────────────────

describe('parseCartFromCookie', () => {
    it('retourne [] quand le cookie est absent', () => {
        assert.deepEqual(parseCartFromCookie(''), []);
        assert.deepEqual(parseCartFromCookie('other=foo'), []);
    });

    it('retourne [] quand la valeur est JSON invalide', () => {
        const cookieStr = `${CART_KEY}=%7Bnot-json%7D`;
        assert.deepEqual(parseCartFromCookie(cookieStr), []);
    });

    it('retourne le panier décodé correctement', () => {
        const cart = [{ id: 1, qty: 2 }, { id: 5, qty: 1 }];
        const encoded = encodeURIComponent(JSON.stringify(cart));
        const cookieStr = `session=abc; ${CART_KEY}=${encoded}; theme=dark`;
        assert.deepEqual(parseCartFromCookie(cookieStr), cart);
    });

    it('retourne le panier quand il est seul', () => {
        const cart = [{ id: 42, qty: 3 }];
        const encoded = encodeURIComponent(JSON.stringify(cart));
        assert.deepEqual(parseCartFromCookie(`${CART_KEY}=${encoded}`), cart);
    });

    it('retourne [] pour un tableau JSON vide', () => {
        const encoded = encodeURIComponent(JSON.stringify([]));
        assert.deepEqual(parseCartFromCookie(`${CART_KEY}=${encoded}`), []);
    });
});

// ─── serializeCartCookie ────────────────────────────────────────────────────

describe('serializeCartCookie', () => {
    it('produit une chaîne cookie valide', () => {
        const cart = [{ id: 1, qty: 2 }];
        const result = serializeCartCookie(cart);
        assert.ok(result.startsWith(`${CART_KEY}=`), 'doit commencer par cb-cart=');
        assert.ok(result.includes('SameSite=Lax'));
        assert.ok(result.includes(`max-age=${CART_MAX_AGE}`));
    });

    it('accepte un max-age personnalisé', () => {
        const cart = [];
        const result = serializeCartCookie(cart, '/', 3600);
        assert.ok(result.includes('max-age=3600'));
    });

    it('le cookie peut être ré-parsé', () => {
        const cart = [{ id: 7, qty: 4 }];
        const cookieStr = serializeCartCookie(cart).split(';')[0]; // "cb-cart=..."
        const reparsed = parseCartFromCookie(cookieStr);
        assert.deepEqual(reparsed, cart);
    });
});

// ─── mergeCartItem ──────────────────────────────────────────────────────────

describe('mergeCartItem', () => {
    it('ajoute un nouvel item absent du panier', () => {
        const result = mergeCartItem([], { id: 1, qty: 2 });
        assert.deepEqual(result, [{ id: 1, qty: 2 }]);
    });

    it('incrémente qty si item déjà présent', () => {
        const cart = [{ id: 1, qty: 2 }];
        const result = mergeCartItem(cart, { id: 1, qty: 3 });
        assert.deepEqual(result, [{ id: 1, qty: 5 }]);
    });

    it('ne modifie pas les autres items', () => {
        const cart = [{ id: 1, qty: 1 }, { id: 2, qty: 2 }];
        const result = mergeCartItem(cart, { id: 1, qty: 1 });
        assert.equal(result.find((i) => i.id === 2).qty, 2);
    });

    it('ne mute pas le tableau original', () => {
        const cart = [{ id: 1, qty: 1 }];
        const copy = JSON.parse(JSON.stringify(cart));
        mergeCartItem(cart, { id: 1, qty: 5 });
        assert.deepEqual(cart, copy, 'le tableau original ne doit pas être muté');
    });

    it('gère des ids de type string', () => {
        const cart = [{ id: '1', qty: 1 }];
        const result = mergeCartItem(cart, { id: '1', qty: 2 });
        assert.deepEqual(result, [{ id: '1', qty: 3 }]);
    });
});

// ─── getCartCount ───────────────────────────────────────────────────────────

describe('getCartCount', () => {
    it('retourne 0 pour un panier vide', () => {
        assert.equal(getCartCount([]), 0);
    });

    it('somme les quantités', () => {
        assert.equal(getCartCount([{ id: 1, qty: 2 }, { id: 2, qty: 3 }]), 5);
    });

    it('utilise 1 si qty est falsy', () => {
        assert.equal(getCartCount([{ id: 1, qty: 0 }, { id: 2, qty: undefined }]), 2);
    });

    it('fonctionne avec un seul item', () => {
        assert.equal(getCartCount([{ id: 1, qty: 10 }]), 10);
    });
});

// ─── parsePrice ─────────────────────────────────────────────────────────────

describe('parsePrice', () => {
    it('parse un prix avec virgule et espace', () => {
        assert.equal(parsePrice('15,50 €'), 15.5);
    });

    it('parse un prix avec point décimal', () => {
        assert.equal(parsePrice('12.00'), 12);
    });

    it('retourne 0 pour null', () => {
        assert.equal(parsePrice(null), 0);
    });

    it('retourne 0 pour undefined', () => {
        assert.equal(parsePrice(undefined), 0);
    });

    it('retourne 0 pour une chaîne vide', () => {
        assert.equal(parsePrice(''), 0);
    });

    it('ignore les espaces insécables', () => {
        assert.equal(parsePrice('1\u00a0500,00\u00a0€'), 1500);
    });

    it('parse un entier sans décimale', () => {
        assert.equal(parsePrice('20 €'), 20);
    });
});

// ─── calcLineTotal ──────────────────────────────────────────────────────────

describe('calcLineTotal', () => {
    it('calcule le total et formate en virgule', () => {
        assert.equal(calcLineTotal(15.5, 2), '31,00');
    });

    it('gère les décimales correctement', () => {
        assert.equal(calcLineTotal(12.99, 3), '38,97');
    });

    it('retourne 0,00 pour qty=0', () => {
        assert.equal(calcLineTotal(10, 0), '0,00');
    });

    it('arrondit à 2 décimales', () => {
        assert.equal(calcLineTotal(0.1, 3), '0,30');
    });
});
