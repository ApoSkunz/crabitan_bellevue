<?php

/**
 * Tests unitaires pour les chemins APCu de RateLimiterService.
 *
 * Ce fichier utilise la syntaxe multi-namespace PHP pour définir des fonctions
 * de substitution `apcu_fetch`, `apcu_store`, `apcu_delete` dans le namespace
 * `Service`. PHP résout d'abord les fonctions dans le namespace courant avant de
 * remonter au namespace global : les appels sans `\` de RateLimiterService
 * utilisent donc nos implémentations en mémoire, sans besoin de l'extension APCu.
 *
 * Important : `apcu_enabled()` n'est PAS redéfinie ici afin d'éviter que
 * `function_exists('apcu_enabled')` retourne true dans d'autres suites de test
 * chargées dans le même processus PHPUnit. La sous-classe override `isApcuAvailable()`
 * directement, ce qui suffit pour activer les branches APCu du service.
 */

declare(strict_types=1);

// ============================================================
// Namespace Service — substituts APCu en mémoire
// ============================================================

namespace Service {
    /**
     * Stockage en mémoire partagé entre les substituts APCu du test.
     *
     * @var array<string, mixed>
     */
    $GLOBALS['_test_apcu_store'] = [];

    /**
     * Substitut de apcu_fetch().
     *
     * @param  string $key
     * @param  bool   $success
     * @return mixed
     */
    function apcu_fetch(string $key, bool &$success = false): mixed
    {
        if (array_key_exists($key, $GLOBALS['_test_apcu_store'])) {
            $success = true;
            return $GLOBALS['_test_apcu_store'][$key];
        }
        $success = false;
        return false;
    }

    /**
     * Substitut de apcu_store().
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $ttl
     * @return bool
     */
    function apcu_store(string $key, mixed $value, int $ttl = 0): bool
    {
        $GLOBALS['_test_apcu_store'][$key] = $value;
        return true;
    }

    /**
     * Substitut de apcu_delete().
     *
     * @param  string $key
     * @return bool
     */
    function apcu_delete(string $key): bool
    {
        unset($GLOBALS['_test_apcu_store'][$key]);
        return true;
    }
}

// ============================================================
// Namespace test — classe de test
// ============================================================

namespace Tests\Unit\Service {

    use PHPUnit\Framework\TestCase;
    use Service\RateLimiterService;

    /**
     * Tests des branches APCu de RateLimiterService.
     *
     * Chaque test utilise une sous-classe qui force isApcuAvailable() = true,
     * ce qui active les chemins apcu_fetch / apcu_store / apcu_delete.
     */
    class RateLimiterServiceApcuTest extends TestCase
    {
        /** @var RateLimiterService */
        private RateLimiterService $service;

        protected function setUp(): void
        {
            // Purger le stockage APCu en mémoire avant chaque test
            $GLOBALS['_test_apcu_store'] = [];

            $this->service = new class extends RateLimiterService {
                /**
                 * Force l'utilisation du chemin APCu.
                 *
                 * @return bool
                 */
                public function isApcuAvailable(): bool
                {
                    return true;
                }
            };
        }

        // ================================================================
        // checkLimit — bucket APCu vide → autorisé
        // ================================================================

        public function testApcuCheckLimitAllowsWhenBucketIsEmpty(): void
        {
            $this->assertTrue($this->service->checkLimit('apcu:test:ip', 5, 900));
        }

        // ================================================================
        // recordAttempt + checkLimit — sous le seuil → autorisé
        // ================================================================

        public function testApcuCheckLimitAllowsWhenBelowMaxAttempts(): void
        {
            $this->service->recordAttempt('apcu:below:ip', 900);
            $this->service->recordAttempt('apcu:below:ip', 900);

            $this->assertTrue($this->service->checkLimit('apcu:below:ip', 5, 900));
        }

        // ================================================================
        // recordAttempt × max → bloqué
        // ================================================================

        public function testApcuCheckLimitBlocksAfterMaxAttempts(): void
        {
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordAttempt('apcu:max:ip', 900);
            }

            $this->assertFalse($this->service->checkLimit('apcu:max:ip', 5, 900));
        }

        // ================================================================
        // apcu_store écrit une clé que apcu_fetch retrouve
        // ================================================================

        public function testApcuFetchReturnsStoredValue(): void
        {
            $this->service->recordAttempt('apcu:fetch:ip', 900);

            // Vérifier que la valeur est bien dans le store APCu mock
            $key    = 'rl:apcu:fetch:ip:count';
            $stored = $GLOBALS['_test_apcu_store'][$key] ?? null;
            $this->assertSame(1, $stored);
        }

        // ================================================================
        // reset — efface count + until dans APCu
        // ================================================================

        public function testApcuResetClearsEntries(): void
        {
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordAttempt('apcu:reset:ip', 900);
            }

            // Bloqué avant reset
            $this->assertFalse($this->service->checkLimit('apcu:reset:ip', 5, 900));

            $this->service->reset('apcu:reset:ip');

            // Autorisé après reset
            $this->assertTrue($this->service->checkLimit('apcu:reset:ip', 5, 900));
        }

        // ================================================================
        // getRetryAfter — retourne 0 si aucun lockout APCu
        // ================================================================

        public function testApcuGetRetryAfterReturnsZeroWhenNoLockout(): void
        {
            $this->assertSame(0, $this->service->getRetryAfter('apcu:no:lockout'));
        }

        // ================================================================
        // getRetryAfter — retourne le temps restant si lockout actif
        // ================================================================

        public function testApcuGetRetryAfterReturnsRemainingWhenLocked(): void
        {
            $until = time() + 300;
            $GLOBALS['_test_apcu_store']['rl:apcu:locked:ip:until'] = $until;

            $remaining = $this->service->getRetryAfter('apcu:locked:ip');

            $this->assertGreaterThanOrEqual(299, $remaining);
            $this->assertLessThanOrEqual(300, $remaining);
        }

        // ================================================================
        // remember / recall via APCu
        // ================================================================

        public function testApcuRememberAndRecallRoundTrip(): void
        {
            $this->service->remember('rgpd_export_ts:apcu:42', 9999, 86400);

            $this->assertSame(9999, $this->service->recall('rgpd_export_ts:apcu:42'));
        }

        public function testApcuRecallReturnsDefaultWhenAbsent(): void
        {
            $this->assertNull($this->service->recall('rgpd_export_ts:apcu:missing'));
            $this->assertSame(0, $this->service->recall('rgpd_export_ts:apcu:missing', 0));
        }

        // ================================================================
        // Scénario complet APCu : 5 échecs → lockout → reset → autorisé
        // ================================================================

        public function testApcuFullScenarioFiveFailuresThenReset(): void
        {
            $key = 'apcu:scenario:127.0.0.1';

            for ($i = 1; $i <= 5; $i++) {
                $this->assertTrue(
                    $this->service->checkLimit($key, 5, 900),
                    "Tentative {$i} doit être autorisée avant enregistrement"
                );
                $this->service->recordAttempt($key, 900);
            }

            $this->assertFalse($this->service->checkLimit($key, 5, 900));
            $this->assertGreaterThan(0, $this->service->getRetryAfter($key));

            $this->service->reset($key);
            $this->assertTrue($this->service->checkLimit($key, 5, 900));
            $this->assertSame(0, $this->service->getRetryAfter($key));
        }

        // ================================================================
        // isApcuAvailable — chemin vrai (la méthode publique elle-même)
        // ================================================================

        public function testIsApcuAvailableReturnsTrueForSubclass(): void
        {
            $this->assertTrue($this->service->isApcuAvailable());
        }
    }
}
