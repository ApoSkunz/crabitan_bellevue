<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use ValueObject\Address;

/**
 * Tests unitaires pour le Value Object Address.
 */
class AddressTest extends TestCase
{
    /**
     * Retourne un tableau de données valides pour la construction.
     *
     * @return array<string, string>
     */
    private function validData(): array
    {
        return [
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
            'street'    => '12 rue des Vignes',
            'city'      => 'Bordeaux',
            'zipCode'   => '33000',
            'country'   => 'FR',
            'civility'  => 'M',
            'phone'     => '0600000000',
        ];
    }

    /**
     * Construction nominale — succès.
     */
    public function testValidAddressConstructs(): void
    {
        $d = $this->validData();
        $address = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], $d['civility'], $d['phone']);
        $this->assertSame('Jean', $address->firstname);
        $this->assertSame('FR', $address->country);
    }

    /**
     * Prénom vide — exception.
     */
    public function testEmptyFirstnameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address('', $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country']);
    }

    /**
     * Nom vide — exception.
     */
    public function testEmptyLastnameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], '', $d['street'], $d['city'], $d['zipCode'], $d['country']);
    }

    /**
     * Rue vide — exception.
     */
    public function testEmptyStreetThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], $d['lastname'], '', $d['city'], $d['zipCode'], $d['country']);
    }

    /**
     * Ville vide — exception.
     */
    public function testEmptyCityThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], $d['lastname'], $d['street'], '', $d['zipCode'], $d['country']);
    }

    /**
     * Code postal vide — exception.
     */
    public function testEmptyZipCodeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], '', $d['country']);
    }

    /**
     * Code pays invalide (pas ISO 3166-1 alpha-2) — exception.
     */
    public function testInvalidCountryCodeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], 'FRANCE');
    }

    /**
     * Pays vide — exception.
     */
    public function testEmptyCountryThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], '');
    }

    /**
     * Civilité invalide — exception.
     */
    public function testInvalidCivilityThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $d = $this->validData();
        new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], 'X');
    }

    /**
     * toArray — retourne toutes les clés attendues.
     */
    public function testToArrayReturnsExpectedKeys(): void
    {
        $d = $this->validData();
        $address = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], $d['civility'], $d['phone']);
        $arr = $address->toArray();
        $this->assertArrayHasKey('firstname', $arr);
        $this->assertArrayHasKey('lastname', $arr);
        $this->assertArrayHasKey('street', $arr);
        $this->assertArrayHasKey('city', $arr);
        $this->assertArrayHasKey('zip_code', $arr);
        $this->assertArrayHasKey('country', $arr);
        $this->assertArrayHasKey('civility', $arr);
        $this->assertArrayHasKey('phone', $arr);
        $this->assertSame('33000', $arr['zip_code']);
    }

    /**
     * equals — même adresse.
     */
    public function testEqualsReturnsTrueForSameAddress(): void
    {
        $d = $this->validData();
        $a = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], $d['civility'], $d['phone']);
        $b = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], $d['civility'], $d['phone']);
        $this->assertTrue($a->equals($b));
    }

    /**
     * equals — adresses différentes.
     */
    public function testEqualsReturnsFalseForDifferentAddress(): void
    {
        $d = $this->validData();
        $a = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], $d['civility'], $d['phone']);
        $b = new Address('Marie', $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], 'F', $d['phone']);
        $this->assertFalse($a->equals($b));
    }

    /**
     * __toString — format lisible.
     */
    public function testToStringReturnsReadableFormat(): void
    {
        $d = $this->validData();
        $address = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country'], $d['civility'], $d['phone']);
        $this->assertSame('Jean Dupont, 12 rue des Vignes, 33000 Bordeaux, FR', (string) $address);
    }

    /**
     * Valeurs par défaut — civility = 'other', phone = ''.
     */
    public function testDefaultValues(): void
    {
        $d = $this->validData();
        $address = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], $d['zipCode'], $d['country']);
        $this->assertSame('other', $address->civility);
        $this->assertSame('', $address->phone);
    }

    /**
     * Code postal avec tiret (ex. Allemagne) — valide.
     */
    public function testZipCodeWithHyphenIsValid(): void
    {
        $d = $this->validData();
        $address = new Address($d['firstname'], $d['lastname'], $d['street'], $d['city'], 'W1A-1AA', 'GB');
        $this->assertSame('W1A-1AA', $address->zipCode);
    }
}
