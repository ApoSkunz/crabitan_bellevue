<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;

/**
 * Tests unitaires pour RateLimiterService.
 *
 * Tous les tests s'exécutent avec le fallback session (APCu désactivé)
 * via une sous-classe qui force isApcuAvailable() à retourner false.
 * Cela garantit une exécution reproductible sans dépendance à l'extension APCu.
 */
class RateLimiterServiceTest extends TestCase
{
    private RateLimiterService $service;

    protected function setUp(): void
    {
        // Démarrer la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Purger les données de rate limiting de la session avant chaque test
        $_SESSION['_rl']     = [];
        $_SESSION['_rl_ttl'] = [];

        // Utiliser la sous-classe sans APCu pour des tests déterministes
        $this->service = new class extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };
    }

    // ----------------------------------------------------------------
    // checkLimit — première tentative (bucket vide)
    // ----------------------------------------------------------------

    public function testCheckLimitAllowsWhenBucketIsEmpty(): void
    {
        $this->assertTrue($this->service->checkLimit('test:ip', 5, 900));
    }

    // ----------------------------------------------------------------
    // checkLimit — sous le seuil
    // ----------------------------------------------------------------

    public function testCheckLimitAllowsWhenBelowMaxAttempts(): void
    {
        $this->service->recordAttempt('test:ip', 900);
        $this->service->recordAttempt('test:ip', 900);
        $this->service->recordAttempt('test:ip', 900);

        // 3 tentatives sur 5 → autorisé
        $this->assertTrue($this->service->checkLimit('test:ip', 5, 900));
    }

    // ----------------------------------------------------------------
    // checkLimit — exactement au seuil
    // ----------------------------------------------------------------

    public function testCheckLimitBlocksWhenAtMaxAttempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordAttempt('test:exact', 900);
        }

        $this->assertFalse($this->service->checkLimit('test:exact', 5, 900));
    }

    // ----------------------------------------------------------------
    // checkLimit — lockout actif (timestamp futur)
    // ----------------------------------------------------------------

    public function testCheckLimitBlocksWhenLockoutIsActive(): void
    {
        // Simuler un lockout actif directement en session
        $_SESSION['_rl']['rl:locked:ip:until'] = time() + 600;

        $this->assertFalse($this->service->checkLimit('locked:ip', 5, 900));
    }

    // ----------------------------------------------------------------
    // checkLimit — lockout expiré
    // ----------------------------------------------------------------

    public function testCheckLimitAllowsAfterLockoutExpired(): void
    {
        // Lockout passé
        $_SESSION['_rl']['rl:expired:ip:until'] = time() - 1;
        $_SESSION['_rl']['rl:expired:ip:count'] = 3;

        $this->assertTrue($this->service->checkLimit('expired:ip', 5, 900));
    }

    // ----------------------------------------------------------------
    // recordAttempt — incrémente le compteur
    // ----------------------------------------------------------------

    public function testRecordAttemptIncrementsCounter(): void
    {
        $this->service->recordAttempt('counter:ip', 900);
        $this->service->recordAttempt('counter:ip', 900);
        $this->service->recordAttempt('counter:ip', 900);

        $count = $_SESSION['_rl']['rl:counter:ip:count'] ?? 0;
        $this->assertSame(3, $count);
    }

    // ----------------------------------------------------------------
    // recordAttempt — premier enregistrement part de 0
    // ----------------------------------------------------------------

    public function testRecordAttemptStartsFromZero(): void
    {
        $this->service->recordAttempt('fresh:ip', 900);

        $count = $_SESSION['_rl']['rl:fresh:ip:count'] ?? 0;
        $this->assertSame(1, $count);
    }

    // ----------------------------------------------------------------
    // reset — efface les entrées
    // ----------------------------------------------------------------

    public function testResetClearsCounterAndLockout(): void
    {
        $this->service->recordAttempt('reset:ip', 900);
        $this->service->recordAttempt('reset:ip', 900);
        // Forcer un lockout
        $_SESSION['_rl']['rl:reset:ip:until'] = time() + 600;

        $this->service->reset('reset:ip');

        $this->assertArrayNotHasKey('rl:reset:ip:count', $_SESSION['_rl'] ?? []);
        $this->assertArrayNotHasKey('rl:reset:ip:until', $_SESSION['_rl'] ?? []);
    }

    // ----------------------------------------------------------------
    // reset — le bucket est de nouveau autorisé après reset
    // ----------------------------------------------------------------

    public function testCheckLimitAllowsAfterReset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordAttempt('reset2:ip', 900);
        }

        // Bloqué avant reset
        $this->assertFalse($this->service->checkLimit('reset2:ip', 5, 900));

        $this->service->reset('reset2:ip');

        // Autorisé après reset
        $this->assertTrue($this->service->checkLimit('reset2:ip', 5, 900));
    }

    // ----------------------------------------------------------------
    // getRetryAfter — retourne 0 si pas de lockout
    // ----------------------------------------------------------------

    public function testGetRetryAfterReturnsZeroWhenNoLockout(): void
    {
        $this->assertSame(0, $this->service->getRetryAfter('no:lockout'));
    }

    // ----------------------------------------------------------------
    // getRetryAfter — retourne le temps restant si lockout actif
    // ----------------------------------------------------------------

    public function testGetRetryAfterReturnsRemainingSecondsWhenLocked(): void
    {
        $until = time() + 300;
        $_SESSION['_rl']['rl:locked2:ip:until'] = $until;

        $remaining = $this->service->getRetryAfter('locked2:ip');

        // Tolérance d'une seconde pour l'exécution du test
        $this->assertGreaterThanOrEqual(299, $remaining);
        $this->assertLessThanOrEqual(300, $remaining);
    }

    // ----------------------------------------------------------------
    // getRetryAfter — retourne 0 si lockout expiré
    // ----------------------------------------------------------------

    public function testGetRetryAfterReturnsZeroWhenLockoutExpired(): void
    {
        $_SESSION['_rl']['rl:old:lock:until'] = time() - 10;

        $this->assertSame(0, $this->service->getRetryAfter('old:lock'));
    }

    // ----------------------------------------------------------------
    // Scénario complet — R1 : 5 échecs → lockout → retry
    // ----------------------------------------------------------------

    public function testFullScenarioFiveFailuresThenLockoutThenReset(): void
    {
        $key = 'scenario:127.0.0.1';

        // 5 tentatives → checkLimit passe toujours (pas encore à 5 enregistrés)
        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue(
                $this->service->checkLimit($key, 5, 900),
                "Tentative {$i} doit être autorisée avant enregistrement"
            );
            $this->service->recordAttempt($key, 900);
        }

        // Après 5 enregistrements → bloqué
        $this->assertFalse($this->service->checkLimit($key, 5, 900));

        // getRetryAfter > 0
        $this->assertGreaterThan(0, $this->service->getRetryAfter($key));

        // Après reset → de nouveau autorisé
        $this->service->reset($key);
        $this->assertTrue($this->service->checkLimit($key, 5, 900));
        $this->assertSame(0, $this->service->getRetryAfter($key));
    }

    // ----------------------------------------------------------------
    // Scénario BT2 — lockout de compte (max 5 sur un compte)
    // ----------------------------------------------------------------

    public function testAccountLockoutAfterFiveConsecutiveFailures(): void
    {
        $accountKey = 'account_lockout:42';

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->service->checkLimit($accountKey, 5, 900));
            $this->service->recordAttempt($accountKey, 900);
        }

        $this->assertFalse($this->service->checkLimit($accountKey, 5, 900));

        // Reset après connexion réussie
        $this->service->reset($accountKey);
        $this->assertTrue($this->service->checkLimit($accountKey, 5, 900));
    }

    // ----------------------------------------------------------------
    // Isolation — des clés distinctes ne se perturbent pas
    // ----------------------------------------------------------------

    public function testDifferentKeysAreIndependent(): void
    {
        $keyA = 'ip:192.168.1.1';
        $keyB = 'ip:192.168.1.2';

        for ($i = 0; $i < 5; $i++) {
            $this->service->recordAttempt($keyA, 900);
        }

        // A est bloqué
        $this->assertFalse($this->service->checkLimit($keyA, 5, 900));

        // B n'est pas affecté
        $this->assertTrue($this->service->checkLimit($keyB, 5, 900));
    }

    // ----------------------------------------------------------------
    // Session TTL — le fallback session ne vérifie PAS les TTL au get()
    // ----------------------------------------------------------------

    /**
     * Documente le comportement connu : en mode session, le TTL est stocké
     * dans $_SESSION['_rl_ttl'] mais n'est PAS relu lors du get().
     * Une clé dont le TTL est expiré est donc toujours retournée telle quelle.
     * Ce test fixe ce comportement pour détecter toute régression future.
     */
    public function testSessionFallbackDoesNotEnforceTtlOnGet(): void
    {
        $key     = 'ttl:test:ip';
        $fullKey = 'rl:' . $key . ':count';

        // Simuler une entrée dont le TTL est périmé depuis 1 seconde
        $_SESSION['_rl'][$fullKey]     = 3;
        $_SESSION['_rl_ttl'][$fullKey] = time() - 1;

        // Le service renvoie quand même la valeur (pas de purge TTL côté get)
        // → checkLimit lit count = 3, pas de lockout → autorisé (3 < 5)
        $this->assertTrue($this->service->checkLimit($key, 5, 900));

        // Et le compteur en session vaut toujours 3
        $this->assertSame(3, $_SESSION['_rl'][$fullKey]);
    }

    // ----------------------------------------------------------------
    // checkLimit — frontière exacte : $until == time() → autorisé
    // ----------------------------------------------------------------

    /**
     * La condition de lockout est `$until > time()`.
     * Quand $until == time(), le lockout est considéré expiré → autorisé.
     */
    public function testCheckLimitAllowsWhenLockoutTimestampEqualsNow(): void
    {
        $key     = 'boundary:ip';
        $fullKey = 'rl:' . $key . ':until';

        // until = maintenant (ni dans le futur ni dans le passé)
        $_SESSION['_rl'][$fullKey] = time();

        $this->assertTrue($this->service->checkLimit($key, 5, 900));
    }

    // ----------------------------------------------------------------
    // getRetryAfter — lockout exactement égal à time() → retourne 0
    // ----------------------------------------------------------------

    /**
     * max(0, time() - time()) == 0.
     * Vérifie que getRetryAfter ne retourne jamais de valeur négative
     * à la frontière exacte d'expiration.
     */
    public function testGetRetryAfterReturnsZeroWhenLockoutExpiresExactlyNow(): void
    {
        $key     = 'exact:now:ip';
        $fullKey = 'rl:' . $key . ':until';

        $_SESSION['_rl'][$fullKey] = time();

        $this->assertSame(0, $this->service->getRetryAfter($key));
    }

    // ----------------------------------------------------------------
    // recordAttempt + checkLimit — maxAttempts = 1 (seuil minimal)
    // ----------------------------------------------------------------

    /**
     * Avec maxAttempts = 1, la première tentative enregistrée doit
     * immédiatement déclencher un blocage au checkLimit suivant.
     */
    public function testCheckLimitBlocksAfterSingleAttemptWhenMaxIsOne(): void
    {
        $key = 'single:attempt:ip';

        // Avant enregistrement → autorisé
        $this->assertTrue($this->service->checkLimit($key, 1, 60));

        $this->service->recordAttempt($key, 60);

        // Après 1 enregistrement avec maxAttempts = 1 → bloqué
        $this->assertFalse($this->service->checkLimit($key, 1, 60));
    }

    // ----------------------------------------------------------------
    // reset — idempotent sur une clé inexistante
    // ----------------------------------------------------------------

    /**
     * Appeler reset() sur une clé qui n'a jamais été utilisée
     * ne doit pas lever d'exception ni modifier l'état de la session.
     */
    public function testResetOnNonExistentKeyIsIdempotent(): void
    {
        $key = 'ghost:key:ip';

        // Ne doit pas lever d'exception
        $this->service->reset($key);

        // Les tableaux session ne doivent pas contenir la clé
        $this->assertArrayNotHasKey('rl:' . $key . ':count', $_SESSION['_rl'] ?? []);
        $this->assertArrayNotHasKey('rl:' . $key . ':until', $_SESSION['_rl'] ?? []);

        // checkLimit doit rester permissif
        $this->assertTrue($this->service->checkLimit($key, 5, 900));
    }

    // ----------------------------------------------------------------
    // checkLimit — windowSeconds = 0 (cas dégénéré)
    // ----------------------------------------------------------------

    /**
     * Avec windowSeconds = 0 et un compteur à zéro, checkLimit doit
     * retourner true (bucket vide, count 0 < maxAttempts 1).
     * Après un recordAttempt, checkLimit pose un lockout avec TTL = 0 + 60 = 60 s.
     */
    public function testCheckLimitWithZeroWindowSecondsOnEmptyBucket(): void
    {
        $key = 'zero:window:ip';

        // Bucket vide → autorisé même avec fenêtre nulle
        $this->assertTrue($this->service->checkLimit($key, 1, 0));
    }

    public function testCheckLimitWithZeroWindowSecondsBlocksAfterRecordAttempt(): void
    {
        $key = 'zero:window:block:ip';

        $this->service->recordAttempt($key, 0);

        // count = 1 >= maxAttempts = 1 → bloqué
        // Le lockout est posé avec TTL = 0 + 60 = 60 s
        $this->assertFalse($this->service->checkLimit($key, 1, 0));

        // windowSeconds=0 → until = time()+0 = time() → expire immédiatement
        // getRetryAfter retourne max(0, time()-time()) = 0 : comportement attendu
        $this->assertSame(0, $this->service->getRetryAfter($key));
    }
}
