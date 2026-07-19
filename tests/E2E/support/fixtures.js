// @ts-check
import { test as base, expect } from '@playwright/test';
import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'fs';
import { join } from 'path';
import { randomUUID } from 'crypto';
import { CUSTOMER_STORAGE_STATE } from './global-setup.js';

/**
 * Pose le cookie age_verified=1 sur le contexte donné.
 * À utiliser dans beforeEach pour les suites nécessitant l'age gate passé.
 *
 * @param {import('@playwright/test').BrowserContext} context
 */
export async function setVerifiedCookie(context) {
    const domain = new URL(process.env.APP_URL || 'http://crabitan.local').hostname;
    await context.addCookies([
        { name: 'age_verified', value: '1', domain, path: '/', httpOnly: true, sameSite: 'Lax' },
    ]);
}

/**
 * Applique au contexte donné les cookies de session client déjà authentifiés
 * (produits une seule fois par le globalSetup Playwright), sans repasser par le
 * formulaire de connexion. Évite de recréer une session par test : sur ~30 tests
 * enchaînés en CI (1 seul worker), rejouer le POST /connexion à chaque fois
 * déclenchait le rate limiter (AuthController::MAX_LOGIN_IP, 10/15min) et cassait
 * en cascade tous les tests suivants nécessitant une session connectée.
 *
 * @param {import('@playwright/test').BrowserContext} context
 */
export async function applyCustomerSession(context) {
    if (!existsSync(CUSTOMER_STORAGE_STATE)) {
        throw new Error(
            `Fichier storageState introuvable : ${CUSTOMER_STORAGE_STATE} — le globalSetup a-t-il tourné ?`
        );
    }
    const state = JSON.parse(readFileSync(CUSTOMER_STORAGE_STATE, 'utf-8'));
    await context.addCookies(state.cookies ?? []);
}

/**
 * Extended test fixture that collects window.__coverage__ (Istanbul)
 * after each test and writes it to .nyc_output/ for lcov generation.
 * Only active when the build was instrumented (VITE_COVERAGE=true).
 */
export const test = base.extend({
    page: async ({ page }, use) => {
        await use(page);

        const coverage = await page.evaluate('window.__coverage__ ?? null').catch(() => null);
        if (coverage) {
            mkdirSync('.nyc_output', { recursive: true });
            writeFileSync(
                join('.nyc_output', `${randomUUID()}.json`),
                JSON.stringify(coverage),
            );
        }
    },
});

export { expect };
