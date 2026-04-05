<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\SessionTtlRefresher;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SessionTtlRefresher::maybeRefresh().
 *
 * Couvre : déclenchement du refresh, absence de refresh (TTL confortable),
 * session remember-me exclue, payload incomplet, TTL expiré ou nul.
 */
class SessionTtlRefresherTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['JWT_EXPIRY']            = '3600';
        $_ENV['JWT_REFRESH_THRESHOLD'] = '0.5';
    }

    // ------------------------------------------------------------------
    // Cas nominal : refresh déclenché
    // ------------------------------------------------------------------

    /**
     * Le refresh est déclenché quand le ratio restant < seuil (ex : 40% restant, seuil 50%).
     */
    public function testRefreshTriggeredWhenRemainingBelowThreshold(): void
    {
        $now = time();
        // Token émis il y a 2200 s sur 3600 s de TTL → 1400 s restantes → ratio 38.9% < 50%
        $payload = ['iat' => $now - 2200, 'exp' => $now + 1400];

        $called = false;
        $refresher = function (string $token, int $expiry) use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertTrue($result);
        $this->assertTrue($called);
    }

    /**
     * Le refresher reçoit bien le token et la durée JWT_EXPIRY.
     */
    public function testRefresherReceivesCorrectTokenAndExpiry(): void
    {
        $now = time();
        $payload = ['iat' => $now - 2200, 'exp' => $now + 1400];

        $receivedToken  = null;
        $receivedExpiry = null;
        $refresher = function (string $token, int $expiry) use (&$receivedToken, &$receivedExpiry): bool {
            $receivedToken  = $token;
            $receivedExpiry = $expiry;
            return true;
        };

        SessionTtlRefresher::maybeRefresh($payload, 'my-jwt-token', false, $refresher);

        $this->assertSame('my-jwt-token', $receivedToken);
        $this->assertSame(3600, $receivedExpiry);
    }

    // ------------------------------------------------------------------
    // Cas nominal : pas de refresh (TTL confortable)
    // ------------------------------------------------------------------

    /**
     * Aucun refresh quand le ratio restant ≥ seuil (ex : 60% restant, seuil 50%).
     */
    public function testNoRefreshWhenRemainingAboveThreshold(): void
    {
        $now = time();
        // Token émis il y a 1440 s sur 3600 s de TTL → 2160 s restantes → ratio 60% ≥ 50%
        $payload = ['iat' => $now - 1440, 'exp' => $now + 2160];

        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    /**
     * Aucun refresh quand le ratio restant est exactement égal au seuil (50% = 50%).
     */
    public function testNoRefreshWhenRemainingExactlyAtThreshold(): void
    {
        $now = time();
        // Token à exactement 50% de sa durée → ratio = 0.5 ≥ 0.5 → pas de refresh
        $payload = ['iat' => $now - 1800, 'exp' => $now + 1800];

        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    // ------------------------------------------------------------------
    // Sessions remember-me exclues
    // ------------------------------------------------------------------

    /**
     * Une session remember-me n'est jamais rafraîchie, quel que soit le TTL restant.
     */
    public function testRememberMeSessionIsNeverRefreshed(): void
    {
        $now = time();
        // Token remember-me (30j) dont le TTL restant est très faible — on s'assure quand même du non-refresh
        $payload = ['iat' => $now - (29 * 86400), 'exp' => $now + 86400];

        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', true, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    // ------------------------------------------------------------------
    // Payload incomplet ou invalide
    // ------------------------------------------------------------------

    /**
     * Payload sans 'exp' → pas de refresh, pas d'exception.
     */
    public function testNoRefreshWhenPayloadMissingExp(): void
    {
        $payload   = ['iat' => time() - 100];
        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    /**
     * Payload sans 'iat' → pas de refresh, pas d'exception.
     */
    public function testNoRefreshWhenPayloadMissingIat(): void
    {
        $payload   = ['exp' => time() + 100];
        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    /**
     * totalTtl = 0 (iat = exp) → pas de division par zéro, pas de refresh.
     */
    public function testNoRefreshWhenTotalTtlIsZero(): void
    {
        $now       = time();
        $payload   = ['iat' => $now, 'exp' => $now]; // durée nulle
        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    /**
     * Token déjà expiré (remaining <= 0) → pas de refresh.
     */
    public function testNoRefreshWhenTokenAlreadyExpired(): void
    {
        $now       = time();
        $payload   = ['iat' => $now - 7200, 'exp' => $now - 10]; // expiré il y a 10 s
        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    // ------------------------------------------------------------------
    // Seuil configurable via $_ENV
    // ------------------------------------------------------------------

    /**
     * Un seuil de 0.2 (20%) ne déclenche pas de refresh si 30% restant > 20%.
     */
    public function testCustomThresholdLow(): void
    {
        $_ENV['JWT_REFRESH_THRESHOLD'] = '0.2';
        $now = time();
        // 30% restant > seuil 20% → pas de refresh
        $payload = ['iat' => $now - 2520, 'exp' => $now + 1080]; // 30% de 3600

        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertFalse($result);
        $this->assertFalse($called);
    }

    /**
     * Un seuil de 0.8 (80%) déclenche le refresh si 60% restant < 80%.
     */
    public function testCustomThresholdHigh(): void
    {
        $_ENV['JWT_REFRESH_THRESHOLD'] = '0.8';
        $now = time();
        // 60% restant < seuil 80% → refresh déclenché
        $payload = ['iat' => $now - 1440, 'exp' => $now + 2160]; // 60% de 3600

        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertTrue($result);
        $this->assertTrue($called);
    }

    /**
     * Absence de JWT_REFRESH_THRESHOLD → valeur par défaut 0.5.
     */
    public function testDefaultThresholdUsedWhenEnvAbsent(): void
    {
        unset($_ENV['JWT_REFRESH_THRESHOLD']);
        $now = time();
        // 40% restant < seuil 0.5 par défaut → refresh déclenché
        $payload = ['iat' => $now - 2160, 'exp' => $now + 1440];

        $called    = false;
        $refresher = function () use (&$called): bool {
            $called = true;
            return true;
        };

        $result = SessionTtlRefresher::maybeRefresh($payload, 'tok', false, $refresher);

        $this->assertTrue($result);
        $this->assertTrue($called);
    }
}
