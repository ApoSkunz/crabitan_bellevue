<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\MajorityService;

/**
 * Tests unitaires pour MajorityService.
 *
 * Couvre les cas : adulte valide, mineur, date invalide.
 * Base légale : Art. L3342-1 CSP — sanctions pénales Art. L3353-3.
 */
class MajorityServiceTest extends TestCase
{
    // ----------------------------------------------------------------
    // isEligible() — adulte valide (>= 18 ans)
    // ----------------------------------------------------------------

    /**
     * Un adulte né il y a 20 ans doit être déclaré éligible.
     */
    public function testEligibleAdult(): void
    {
        $birthDate = date('Y-m-d', strtotime('-20 years'));
        $this->assertTrue(MajorityService::isEligible($birthDate));
    }

    /**
     * Un adulte né exactement il y a 18 ans aujourd'hui est éligible.
     */
    public function testEligibleAdultExactly18(): void
    {
        $birthDate = date('Y-m-d', strtotime('-18 years'));
        $this->assertTrue(MajorityService::isEligible($birthDate));
    }

    /**
     * Un adulte né il y a 40 ans doit être déclaré éligible.
     */
    public function testEligibleOlderAdult(): void
    {
        $birthDate = date('Y-m-d', strtotime('-40 years'));
        $this->assertTrue(MajorityService::isEligible($birthDate));
    }

    // ----------------------------------------------------------------
    // isEligible() — mineur (< 18 ans)
    // ----------------------------------------------------------------

    /**
     * Un mineur né il y a 17 ans doit être rejeté.
     */
    public function testMinorRejected(): void
    {
        $birthDate = date('Y-m-d', strtotime('-17 years'));
        $this->assertFalse(MajorityService::isEligible($birthDate));
    }

    /**
     * Un mineur né hier doit être rejeté.
     */
    public function testNewbornRejected(): void
    {
        $birthDate = date('Y-m-d', strtotime('-1 day'));
        $this->assertFalse(MajorityService::isEligible($birthDate));
    }

    /**
     * Un mineur né il y a 17 ans et 364 jours doit être rejeté.
     */
    public function testAlmostAdultRejected(): void
    {
        $birthDate = date('Y-m-d', strtotime('-17 years -364 days'));
        $this->assertFalse(MajorityService::isEligible($birthDate));
    }

    // ----------------------------------------------------------------
    // isEligible() — date invalide
    // ----------------------------------------------------------------

    /**
     * Une chaîne de date invalide doit retourner false.
     */
    public function testInvalidDateStringRejected(): void
    {
        $this->assertFalse(MajorityService::isEligible('not-a-date'));
    }

    /**
     * Une chaîne vide doit retourner false.
     */
    public function testEmptyStringRejected(): void
    {
        $this->assertFalse(MajorityService::isEligible(''));
    }

    /**
     * Une date dans le futur doit retourner false.
     */
    public function testFutureDateRejected(): void
    {
        $birthDate = date('Y-m-d', strtotime('+1 day'));
        $this->assertFalse(MajorityService::isEligible($birthDate));
    }

    /**
     * Un format de date incorrect (DD/MM/YYYY) doit retourner false.
     */
    public function testWrongFormatRejected(): void
    {
        $this->assertFalse(MajorityService::isEligible('01/01/2000'));
    }

    /**
     * Une date avec mois invalide doit retourner false.
     */
    public function testInvalidMonthRejected(): void
    {
        $this->assertFalse(MajorityService::isEligible('2000-13-01'));
    }

    /**
     * Une date avec jour invalide doit retourner false.
     */
    public function testInvalidDayRejected(): void
    {
        $this->assertFalse(MajorityService::isEligible('2000-01-32'));
    }

    // ----------------------------------------------------------------
    // isEligible() — cohérence entre cas limites
    // ----------------------------------------------------------------

    /**
     * Un individu né un jour avant ses 18 ans doit être rejeté.
     */
    public function testDayBeforeAdulthoodRejected(): void
    {
        // Né il y a 18 ans et 1 jour → a déjà ses 18 ans
        // Né il y a 17 ans et 364 jours → pas encore 18 ans
        $notYet18 = date('Y-m-d', strtotime('-17 years -364 days'));
        $this->assertFalse(MajorityService::isEligible($notYet18));
    }
}
