// @ts-check
import { test as base, expect } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';
import { randomUUID } from 'crypto';

/**
 * Pose le cookie age_verified=1 sur le contexte donné.
 * À utiliser dans beforeEach pour les suites nécessitant l'age gate passé.
 *
 * @param {import('@playwright/test').BrowserContext} context
 */
export async function setVerifiedCookie(context) {
    const domain = new URL(process.env.APP_URL || 'http://localhost:8000').hostname;
    await context.addCookies([
        { name: 'age_verified', value: '1', domain, path: '/', httpOnly: true, sameSite: 'Lax' },
    ]);
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
