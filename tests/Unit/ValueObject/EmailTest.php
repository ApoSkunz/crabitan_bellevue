<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use ValueObject\Email;

/**
 * Tests unitaires pour le Value Object Email.
 */
class EmailTest extends TestCase
{
    /**
     * Email valide — construction réussie.
     */
    public function testValidEmailConstructs(): void
    {
        $email = new Email('user@example.com');
        $this->assertSame('user@example.com', $email->getValue());
    }

    /**
     * Email invalide — exception levée.
     */
    public function testInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Email('not-an-email');
    }

    /**
     * Email vide — exception levée.
     */
    public function testEmptyEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Email('');
    }

    /**
     * Email sans domaine — exception levée.
     */
    public function testEmailWithoutDomainThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Email('user@');
    }

    /**
     * Email sans @ — exception levée.
     */
    public function testEmailWithoutAtThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Email('userexample.com');
    }

    /**
     * Normalisation en minuscules.
     */
    public function testEmailNormalizesToLowercase(): void
    {
        $email = new Email('User@EXAMPLE.COM');
        $this->assertSame('user@example.com', $email->getValue());
    }

    /**
     * Suppression des espaces.
     */
    public function testEmailTrimmed(): void
    {
        $email = new Email('  user@example.com  ');
        $this->assertSame('user@example.com', $email->getValue());
    }

    /**
     * Méthode equals — mêmes valeurs.
     */
    public function testEqualsReturnsTrueForSameValue(): void
    {
        $a = new Email('user@example.com');
        $b = new Email('user@example.com');
        $this->assertTrue($a->equals($b));
    }

    /**
     * Méthode equals — valeurs différentes.
     */
    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $a = new Email('user@example.com');
        $b = new Email('other@example.com');
        $this->assertFalse($a->equals($b));
    }

    /**
     * getDomain — retourne le domaine correct.
     */
    public function testGetDomainReturnsCorrectDomain(): void
    {
        $email = new Email('user@example.com');
        $this->assertSame('example.com', $email->getDomain());
    }

    /**
     * __toString — retourne la valeur normalisée.
     */
    public function testToStringReturnsNormalizedValue(): void
    {
        $email = new Email('User@EXAMPLE.COM');
        $this->assertSame('user@example.com', (string) $email);
    }

    /**
     * Equals est insensible à la casse lors de la construction.
     */
    public function testEqualsAfterNormalization(): void
    {
        $a = new Email('User@EXAMPLE.COM');
        $b = new Email('user@example.com');
        $this->assertTrue($a->equals($b));
    }
}
