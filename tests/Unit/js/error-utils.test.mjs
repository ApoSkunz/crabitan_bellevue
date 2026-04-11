/**
 * Tests unitaires — error-utils.js
 * Exécutés avec node:test (Node ≥ 18).
 *
 * @command node --test tests/unit/js/error-utils.test.mjs
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import {
    shouldIgnore,
    buildErrorPayload,
    buildRejectionPayload,
} from '../../../resources/js/error-utils.js';

// ─── shouldIgnore ────────────────────────────────────────────────────────────

describe('shouldIgnore', () => {
    const HOST = 'crabitan.local';

    it('ignore si source et stack sont tous les deux vides', () => {
        assert.equal(shouldIgnore('', '', HOST), true);
    });

    it('ignore si source est absente (falsy) et stack vide', () => {
        assert.equal(shouldIgnore(null, '', HOST), true);
        assert.equal(shouldIgnore(undefined, '', HOST), true);
    });

    it('ne pas ignorer si stack présente mais source vide', () => {
        assert.equal(shouldIgnore('', 'Error at crabitan.local/app.js:12', HOST), false);
    });

    it('ignore si source présente mais hors domaine', () => {
        assert.equal(shouldIgnore('https://evil.com/malware.js', 'Error...', HOST), true);
    });

    it('ne pas ignorer si source contient le hostname courant', () => {
        assert.equal(shouldIgnore('http://crabitan.local/resources/js/main.js', 'Error...', HOST), false);
    });

    it('ne pas ignorer si source et stack valides', () => {
        assert.equal(shouldIgnore('http://crabitan.local/app.js', 'Error\n at fn (app.js:1)', HOST), false);
    });

    it('ignore une extension chrome (source extension://...)', () => {
        assert.equal(shouldIgnore('chrome-extension://abc/content.js', 'Error...', HOST), true);
    });
});

// ─── buildErrorPayload ───────────────────────────────────────────────────────

describe('buildErrorPayload', () => {
    it('construit le payload complet', () => {
        const error = new Error('boom');
        const payload = buildErrorPayload(
            'Uncaught Error: boom',
            'http://crabitan.local/app.js',
            42,
            7,
            error,
            'http://crabitan.local/',
            'Mozilla/5.0'
        );

        assert.equal(payload.message, 'Uncaught Error: boom');
        assert.equal(payload.source, 'http://crabitan.local/app.js');
        assert.equal(payload.lineno, 42);
        assert.equal(payload.colno, 7);
        assert.ok(payload.stack.includes('boom'));
        assert.equal(payload.url, 'http://crabitan.local/');
        assert.equal(payload.userAgent, 'Mozilla/5.0');
    });

    it('gère source null → chaîne vide', () => {
        const payload = buildErrorPayload('msg', null, 0, 0, null, '/', 'ua');
        assert.equal(payload.source, '');
        assert.equal(payload.stack, '');
    });

    it('convertit lineno/colno NaN en 0', () => {
        const payload = buildErrorPayload('msg', '', NaN, undefined, null, '/', 'ua');
        assert.equal(payload.lineno, 0);
        assert.equal(payload.colno, 0);
    });

    it('convertit message en string', () => {
        const payload = buildErrorPayload(42, '', 0, 0, null, '/', 'ua');
        assert.equal(typeof payload.message, 'string');
        assert.equal(payload.message, '42');
    });
});

// ─── buildRejectionPayload ───────────────────────────────────────────────────

describe('buildRejectionPayload', () => {
    it('gère une Error comme reason', () => {
        const err = new Error('promise failed');
        const payload = buildRejectionPayload(err, 'http://crabitan.local/', 'ua');
        assert.equal(payload.message, 'promise failed');
        assert.ok(payload.stack.includes('promise failed'));
        assert.equal(payload.source, '');
        assert.equal(payload.lineno, 0);
        assert.equal(payload.colno, 0);
    });

    it('gère une chaîne comme reason', () => {
        const payload = buildRejectionPayload('network error', '/', 'ua');
        assert.equal(payload.message, 'network error');
        assert.equal(payload.stack, '');
    });

    it('gère null comme reason', () => {
        const payload = buildRejectionPayload(null, '/', 'ua');
        assert.equal(payload.message, 'Unhandled Promise Rejection');
    });

    it('gère undefined comme reason', () => {
        const payload = buildRejectionPayload(undefined, '/', 'ua');
        assert.equal(payload.message, 'Unhandled Promise Rejection');
    });

    it('gère un nombre comme reason', () => {
        const payload = buildRejectionPayload(404, '/', 'ua');
        assert.equal(payload.message, '404');
    });
});
