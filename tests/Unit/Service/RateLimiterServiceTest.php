<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Database;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;

/**
 * Tests unitaires pour RateLimiterService.
 *
 * Tous les tests s'exécutent avec le fallback session (APCu désactivé)
 * via une sous-classe qui force isApcuAvailable() à retourner false.
 * Cela garantit une exécution reproductible sans dépendance à l'extension APCu.
 */
#[AllowMockObjectsWithoutExpectations]
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
     * En mode session, le TTL est maintenant appliqué lors du get() :
     * une clé dont le TTL est expiré est purgée et retourne la valeur par défaut.
     */
    public function testSessionFallbackEnforcesTtlOnGet(): void
    {
        $key     = 'ttl:test:ip';
        $fullKey = 'rl:' . $key . ':count';

        // Simuler une entrée dont le TTL est périmé depuis 1 seconde
        $_SESSION['_rl'][$fullKey]     = 3;
        $_SESSION['_rl_ttl'][$fullKey] = time() - 1;

        // Le TTL est expiré → la clé est purgée et le compteur revient à 0
        // → checkLimit considère le bucket vide → autorisé
        $this->assertTrue($this->service->checkLimit($key, 5, 900));

        // La clé doit avoir été supprimée de la session
        $this->assertArrayNotHasKey($fullKey, $_SESSION['_rl'] ?? []);
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

    public function testCheckLimitWithZeroWindowSecondsAllowsAfterRecordAttempt(): void
    {
        $key = 'zero:window:block:ip';

        $this->service->recordAttempt($key, 0);

        // TTL = 0 → l'entrée expire immédiatement dès la lecture
        // → count revient à 0 → checkLimit autorisé
        $this->assertTrue($this->service->checkLimit($key, 1, 0));
        $this->assertSame(0, $this->service->getRetryAfter($key));
    }

    // ----------------------------------------------------------------
    // remember / recall — stockage de métadonnées arbitraires
    // ----------------------------------------------------------------

    /**
     * remember() stocke une valeur et recall() la retourne correctement.
     */
    public function testRememberAndRecallRoundTrip(): void
    {
        $this->service->remember('rgpd_export_ts:42', 1712345678, 86400);

        $this->assertSame(1712345678, $this->service->recall('rgpd_export_ts:42'));
    }

    /**
     * recall() retourne la valeur par défaut quand la clé est absente.
     */
    public function testRecallReturnsDefaultWhenKeyAbsent(): void
    {
        $this->assertNull($this->service->recall('rgpd_export_ts:999'));
        $this->assertSame(0, $this->service->recall('rgpd_export_ts:999', 0));
    }

    /**
     * remember() écrase une valeur précédemment stockée.
     */
    public function testRememberOverwritesPreviousValue(): void
    {
        $this->service->remember('rgpd_export_ts:7', 111, 86400);
        $this->service->remember('rgpd_export_ts:7', 222, 86400);

        $this->assertSame(222, $this->service->recall('rgpd_export_ts:7'));
    }

    // ----------------------------------------------------------------
    // Backend BDD — isDbAvailable
    // ----------------------------------------------------------------

    /**
     * isDbAvailable() retourne false si aucune instance BDD injectée.
     */
    public function testIsDbAvailableReturnsFalseWithoutDb(): void
    {
        $this->assertFalse($this->service->isDbAvailable());
    }

    /**
     * isDbAvailable() retourne true si une instance Database est injectée.
     */
    public function testIsDbAvailableReturnsTrueWithDb(): void
    {
        /** @var Database $db */
        $db      = $this->createStub(Database::class);
        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        $this->assertTrue($service->isDbAvailable());
    }

    // ----------------------------------------------------------------
    // Backend BDD — get() via checkLimit
    // ----------------------------------------------------------------

    /**
     * Avec BDD disponible et bucket vide (fetchOne retourne false),
     * checkLimit doit autoriser la requête.
     */
    public function testCheckLimitWithDbAllowsWhenBucketEmpty(): void
    {
        /** @var Database $db */
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(false);

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        $this->assertTrue($service->checkLimit('db:ip', 5, 900));
    }

    /**
     * Avec BDD disponible et lockout actif stocké en BDD,
     * checkLimit doit bloquer la requête.
     */
    public function testCheckLimitWithDbBlocksWhenLockoutActive(): void
    {
        /** @var Database $db */
        $db = $this->createStub(Database::class);

        // fetchOne retourne un lockout futur pour les clés :until
        $db->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params) {
                if (str_contains($params[0], ':until')) {
                    return ['value' => json_encode(time() + 600)];
                }
                return false;
            });

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        $this->assertFalse($service->checkLimit('db:locked', 5, 900));
    }

    // ----------------------------------------------------------------
    // Backend BDD — set() via recordAttempt
    // ----------------------------------------------------------------

    /**
     * recordAttempt() avec BDD doit appeler query() (INSERT … ON DUPLICATE KEY).
     */
    public function testRecordAttemptWithDbCallsQuery(): void
    {
        /** @var Database&MockObject $db */
        $db = $this->createMock(Database::class);

        // fetchOne pour get() (bucket vide)
        $db->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $stmtMock = $this->createStub(\PDOStatement::class);
        $db->expects($this->once())
            ->method('query')
            ->willReturn($stmtMock);

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        $service->recordAttempt('db:record', 900);
    }

    // ----------------------------------------------------------------
    // Backend BDD — delete() via reset()
    // ----------------------------------------------------------------

    /**
     * reset() avec BDD doit appeler execute() pour DELETE sur les deux clés.
     */
    public function testResetWithDbCallsExecute(): void
    {
        /** @var Database&MockObject $db */
        $db = $this->createMock(Database::class);

        $db->expects($this->exactly(2))
            ->method('execute')
            ->with(
                $this->stringContains('DELETE FROM rate_limits'),
                $this->anything()
            )
            ->willReturn(1);

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        $service->reset('db:reset:ip');
    }

    // ----------------------------------------------------------------
    // Fallback APCu absent + BDD absente → session
    // ----------------------------------------------------------------

    /**
     * Sans APCu et sans BDD, le service bascule sur la session.
     * recordAttempt doit incrémenter la session normalement.
     */
    public function testFallbackToSessionWhenNeitherApcuNorDb(): void
    {
        // $this->service n'a pas de BDD injectée (setUp)
        $this->service->recordAttempt('fallback:ip', 900);
        $this->service->recordAttempt('fallback:ip', 900);

        $count = $_SESSION['_rl']['rl:fallback:ip:count'] ?? 0;
        $this->assertSame(2, $count);
    }

    /**
     * Sans APCu et sans BDD, reset() efface les entrées de session.
     */
    public function testResetFallbackSessionClearsEntries(): void
    {
        $this->service->recordAttempt('fallback:reset', 900);
        $_SESSION['_rl']['rl:fallback:reset:until'] = time() + 600;

        $this->service->reset('fallback:reset');

        $this->assertArrayNotHasKey('rl:fallback:reset:count', $_SESSION['_rl'] ?? []);
        $this->assertArrayNotHasKey('rl:fallback:reset:until', $_SESSION['_rl'] ?? []);
    }

    // ----------------------------------------------------------------
    // Backend BDD — exception silencieuse
    // ----------------------------------------------------------------

    /**
     * Si la BDD lève une exception sur fetchOne,
     * get() retourne la valeur par défaut sans propager l'exception.
     */
    public function testDbGetReturnDefaultOnException(): void
    {
        /** @var Database $db */
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willThrowException(new \RuntimeException('DB down'));

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        // checkLimit doit retourner true (bucket vide = autorisation par défaut)
        $this->assertTrue($service->checkLimit('db:error', 5, 900));
    }

    /**
     * Si la BDD lève une exception sur execute() (delete),
     * reset() ne doit pas propager l'exception.
     */
    public function testDbDeleteSilentOnException(): void
    {
        /** @var Database $db */
        $db = $this->createStub(Database::class);
        $db->method('execute')->willThrowException(new \RuntimeException('DB down'));

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        // Ne doit pas lever d'exception
        $service->reset('db:delete:error');
        $this->assertTrue(true);
    }

    /**
     * Si la BDD lève une exception sur query() (set),
     * recordAttempt() ne doit pas propager l'exception.
     */
    public function testDbSetSilentOnException(): void
    {
        /** @var Database $db */
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(false);
        $db->method('query')->willThrowException(new \RuntimeException('DB down'));

        $service = new class ($db) extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        };

        // Ne doit pas lever d'exception
        $service->recordAttempt('db:set:error', 900);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // startSession() — implémentation réelle (ligne 330)
    // ----------------------------------------------------------------

    /**
     * startSession() réelle appelle session_start() et rend la session active.
     *
     * RunInSeparateProcess isole le session_write_close() pour ne pas perturber
     * les autres tests qui lisent $_SESSION.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStartSessionRealImplementationActivatesSession(): void
    {
        $service = new class extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
            // startSession() NON overridée → implémentation réelle couverte
        };

        // Fermer la session pour forcer le passage dans startSession()
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // checkLimit → ensureSession() → startSession() réelle → session_start()
        $service->checkLimit('real:start:session', 5, 60);

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());

        // Nettoyage — la session reste ouverte pour les tests suivants (setUp la gère)
    }

    // ----------------------------------------------------------------
    // blockFor() — pose un lockout explicite
    // ----------------------------------------------------------------

    /**
     * blockFor() pose directement :until dans le futur,
     * indépendamment du compteur.
     */
    public function testBlockForSetsActiveLockou(): void
    {
        $this->service->blockFor('block:direct:ip', 300);

        $remaining = $this->service->getRetryAfter('block:direct:ip');
        $this->assertGreaterThanOrEqual(299, $remaining);
        $this->assertLessThanOrEqual(300, $remaining);
    }

    /**
     * blockFor() bloque checkLimit immédiatement après appel.
     */
    public function testBlockForCausesCheckLimitToReturnFalse(): void
    {
        $this->service->blockFor('block:check:ip', 300);

        $this->assertFalse($this->service->checkLimit('block:check:ip', 5, 900));
    }

    // ----------------------------------------------------------------
    // startSession() — seam overridable
    // ----------------------------------------------------------------

    /**
     * startSession() est un seam protected overridable — vérifie que la subclass peut l'intercepter.
     *
     * @return void
     */
    public function testStartSessionIsOverridableSeam(): void
    {
        $service = new class extends RateLimiterService {
            public bool $startCalled = false;

            public function isApcuAvailable(): bool
            {
                return false;
            }

            protected function startSession(): void
            {
                $this->startCalled = true;
            }

            public function callStartSession(): void
            {
                $this->startSession();
            }
        };

        $service->callStartSession();

        $this->assertTrue($service->startCalled);
    }
}
