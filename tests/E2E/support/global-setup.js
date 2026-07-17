// @ts-check
import { chromium } from '@playwright/test';

/**
 * Référence du fichier de storageState client (partagé par les specs qui
 * n'ont besoin que d'un précondition "connecté", pas de tester le login lui-même).
 */
export const CUSTOMER_STORAGE_STATE = 'tests/E2E/.auth/customer.json';

const CUSTOMER_EMAIL    = 'client.verifie@dev.local';
const CUSTOMER_PASSWORD = 'Dev123456789!';
const CUSTOMER_DEVICE   = 'b2c3d4e5b2c3d4e5b2c3d4e5b2c3d4e5b2c3d4e5b2c3d4e5b2c3d4e5b2c3d4e5';

/**
 * Global setup Playwright — authentifie une seule fois le compte client de test
 * et sauvegarde les cookies (age_verified, device_token, auth_token) dans un
 * fichier storageState réutilisé par les specs (checkout, mon-compte, content-i18n,
 * vins-detail) qui n'ont besoin que d'être "connectées", pas de tester le login.
 *
 * Sans ça, chaque test relançait son propre POST /connexion — sur ~30 tests
 * enchaînés en CI (1 seul worker), le rate limiter (R1 : 10 tentatives / IP / 15 min,
 * AuthController::MAX_LOGIN_IP) se déclenchait avant la fin de la suite et cassait
 * en cascade tous les tests suivants nécessitant une session connectée.
 *
 * @param {import('@playwright/test').FullConfig} config
 * @return {Promise<void>}
 */
export default async function globalSetup(config) {
    const baseURL = config.projects[0]?.use?.baseURL || process.env.APP_URL || 'http://crabitan.local';
    const domain  = new URL(baseURL).hostname;

    const browser = await chromium.launch();
    const context = await browser.newContext({ baseURL });

    await context.addCookies([
        { name: 'age_verified', value: '1',             domain, path: '/', httpOnly: true,  sameSite: 'Lax' },
        { name: 'device_token', value: CUSTOMER_DEVICE, domain, path: '/', httpOnly: false, sameSite: 'Lax' },
    ]);

    const page = await context.newPage();
    await page.goto('/fr');
    await page.locator('#login-modal-trigger').click();
    await page.locator('#login-modal-email').fill(CUSTOMER_EMAIL);
    await page.locator('#login-modal-password').fill(CUSTOMER_PASSWORD);
    await Promise.all([
        page.waitForURL(/\/(fr|en)/, { timeout: 15000 }),
        page.locator('form[action*="/connexion"] button[type="submit"]').click(),
    ]);

    const cookies    = await context.cookies();
    const authCookie = cookies.find((c) => c.name === 'auth_token');
    if (!authCookie) {
        await browser.close();
        throw new Error(
            'globalSetup : le login du compte client de test n\'a pas posé de cookie auth_token — '
            + `URL finale : ${page.url()}. `
            + 'Vérifier que MFA_EMAIL_ENABLED=false (sinon un appareil non reconnu déclenche un '
            + 'challenge MFA au lieu d\'une connexion directe) et que le compte/mot de passe seedés '
            + 'correspondent à CUSTOMER_EMAIL/CUSTOMER_PASSWORD ci-dessus.'
        );
    }

    await context.storageState({ path: CUSTOMER_STORAGE_STATE });
    await browser.close();
}
