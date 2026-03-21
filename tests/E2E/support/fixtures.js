// @ts-check
import { test as base, expect } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';
import { randomUUID } from 'crypto';

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
