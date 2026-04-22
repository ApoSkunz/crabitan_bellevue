<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use Service\MailService;
use Service\RateLimiterService;
use Service\SecurityAlertService;
use Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests d'intégration de SecurityAlertService.
 *
 * Vérifie que 3 échecs consécutifs déclenchent bien l'envoi de l'alerte email
 * en utilisant la session PHP comme backend (pas APCu en CLI).
 */
#[AllowMockObjectsWithoutExpectations]
class SecurityAlertServiceIntegrationTest extends IntegrationTestCase
{
    private SecurityAlertService $service;
    private MailService $mailMock;
    private RateLimiterService $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        // Session propre pour chaque test (RateLimiterService utilise la session en CLI)
        $_SESSION = [];

        $this->rateLimiter = new RateLimiterService();
        $this->mailMock    = $this->createMock(MailService::class);
        $this->service     = new SecurityAlertService($this->rateLimiter, $this->mailMock);
    }

    // ----------------------------------------------------------------
    // shouldAlertOnFailedLogins — comportement réel via session
    // ----------------------------------------------------------------

    /**
     * Après 2 échecs, shouldAlertOnFailedLogins retourne false.
     */
    public function testTwoFailuresDoNotTriggerAlert(): void
    {
        $adminId = 1001;

        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $result = $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);

        $this->assertFalse($result, 'Pas d\'alerte avant le 3ème échec');
    }

    /**
     * Au 3ème échec consécutif, shouldAlertOnFailedLogins retourne true.
     */
    public function testThirdFailureTriggersAlert(): void
    {
        $adminId = 1002;

        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $result = $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);

        $this->assertTrue($result, 'L\'alerte doit se déclencher exactement au 3ème échec');
    }

    /**
     * Au 4ème échec, shouldAlertOnFailedLogins retourne false (alerte déjà envoyée).
     */
    public function testFourthFailureDoesNotRetriggerAlert(): void
    {
        $adminId = 1003;

        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3); // Seuil atteint
        $result = $this->service->shouldAlertOnFailedLogins($adminId, 5, 3); // 4ème

        $this->assertFalse($result, 'Pas de double alerte au-delà du seuil');
    }

    // ----------------------------------------------------------------
    // 3 échecs → email déclenché (critère d'acceptation TI)
    // ----------------------------------------------------------------

    /**
     * 3 échecs consécutifs → sendAdminLoginAlert est appelé exactement une fois.
     */
    public function testThreeFailuresCallSendAdminLoginAlert(): void
    {
        $adminId = 1004;
        $account = [
            'id'        => $adminId,
            'email'     => 'admin@test.local',
            'firstname' => 'Alice',
            'lang'      => 'fr',
            'role'      => 'admin',
        ];
        $ip = '127.0.0.1';

        $_ENV['MAINTAINER_MAIL'] = '';

        $this->mailMock->expects($this->once())
            ->method('sendAdminLoginFailureAlert');

        for ($i = 0; $i < 3; $i++) {
            if ($this->service->shouldAlertOnFailedLogins($adminId, 5, 3)) {
                $this->service->sendAdminLoginAlert($account, $ip);
            }
        }
    }

    /**
     * Reset du compteur → après reset, il faut à nouveau 3 échecs pour déclencher l'alerte.
     */
    public function testResetResetsCounter(): void
    {
        $adminId = 1005;

        // Premier cycle : 3 échecs
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3); // Seuil

        // Reset après connexion réussie
        $this->service->resetFailedLoginCount($adminId);

        // Nouveau cycle : 2 échecs seulement → pas d'alerte
        $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);
        $result = $this->service->shouldAlertOnFailedLogins($adminId, 5, 3);

        $this->assertFalse($result, 'Après reset, 2 échecs ne déclenchent pas l\'alerte');
    }

    /**
     * Deux admins différents ont des compteurs indépendants.
     */
    public function testDifferentAdminIdsAreIsolated(): void
    {
        $admin1 = 2001;
        $admin2 = 2002;

        // Admin1 : 3 échecs
        $this->service->shouldAlertOnFailedLogins($admin1, 5, 3);
        $this->service->shouldAlertOnFailedLogins($admin1, 5, 3);
        $resultAdmin1 = $this->service->shouldAlertOnFailedLogins($admin1, 5, 3);

        // Admin2 : 1 seul échec
        $resultAdmin2 = $this->service->shouldAlertOnFailedLogins($admin2, 5, 3);

        $this->assertTrue($resultAdmin1, 'Admin1 doit déclencher l\'alerte');
        $this->assertFalse($resultAdmin2, 'Admin2 ne doit pas déclencher l\'alerte');
    }
}
