<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Service\DeliveryZoneService;

/**
 * Tests unitaires de DeliveryZoneService : France métropolitaine continentale uniquement.
 */
class DeliveryZoneServiceTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function mainlandZips(): array
    {
        return [
            'Paris'            => ['75001'],
            'Marseille'        => ['13001'],
            'Strasbourg'       => ['67000'],
            'Sainte-Croix'     => ['33410'],
            'Vendée continent' => ['85000'],
            'La Rochelle'      => ['17000'],
            'Dernier métropole' => ['95999'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function excludedZips(): array
    {
        return [
            'Corse-du-Sud'   => ['20000'],
            'Haute-Corse'    => ['20200'],
            'Guadeloupe'     => ['97100'],
            'Martinique'     => ['97200'],
            'Réunion'        => ['97400'],
            'Mayotte'        => ['97600'],
            'Polynésie'      => ['98700'],
            'Monaco'         => ['98000'],
            'Île d\'Yeu'     => ['85350'],
            'Noirmoutier'    => ['85330'],
            'Île de Ré'      => ['17590'],
            'Île d\'Oléron'  => ['17310'],
            'Belle-Île'      => ['56360'],
            'Groix'          => ['56590'],
            'Ouessant'       => ['29242'],
            'Île de Sein'    => ['29990'],
            'Bréhat'         => ['22870'],
            'zip roumain'    => ['407205'],
            'trop court'     => ['7500'],
            'non numérique'  => ['abcde'],
            'vide'           => [''],
        ];
    }

    #[DataProvider('mainlandZips')]
    public function testMainlandZipsAreAccepted(string $zip): void
    {
        $this->assertTrue(DeliveryZoneService::isMainlandZip($zip));
    }

    #[DataProvider('excludedZips')]
    public function testExcludedZipsAreRefused(string $zip): void
    {
        $this->assertFalse(DeliveryZoneService::isMainlandZip($zip));
    }

    public function testIsFranceCountryAcceptsVariants(): void
    {
        $this->assertTrue(DeliveryZoneService::isFranceCountry('France'));
        $this->assertTrue(DeliveryZoneService::isFranceCountry(' france '));
        $this->assertTrue(DeliveryZoneService::isFranceCountry('FR'));
    }

    public function testIsFranceCountryRejectsOthers(): void
    {
        $this->assertFalse(DeliveryZoneService::isFranceCountry('Roumanie'));
        $this->assertFalse(DeliveryZoneService::isFranceCountry('RO'));
        $this->assertFalse(DeliveryZoneService::isFranceCountry(''));
    }

    public function testIsDeliverableRequiresFranceAndMainlandZip(): void
    {
        $this->assertTrue(DeliveryZoneService::isDeliverable('France', '33410'));
        $this->assertFalse(DeliveryZoneService::isDeliverable('Roumanie', '33410'));
        $this->assertFalse(DeliveryZoneService::isDeliverable('France', '85350'));
        $this->assertFalse(DeliveryZoneService::isDeliverable('Roumanie', '407205'));
    }

    public function testIslandZipsAreFiveDigitStringsWithoutDuplicates(): void
    {
        $zips = DeliveryZoneService::ISLAND_ZIPS;

        foreach ($zips as $zip) {
            $this->assertMatchesRegularExpression('/^\d{5}$/', $zip);
        }
        $this->assertSame(count($zips), count(array_unique($zips)));
    }
}
