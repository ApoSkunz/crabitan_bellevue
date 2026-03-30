<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\PasswordValidator;

/**
 * Tests unitaires pour PasswordValidator (politique ANSSI MDP 2021).
 */
class PasswordValidatorTest extends TestCase
{
    // ----------------------------------------------------------------
    // Cas valides — doit retourner true
    // ----------------------------------------------------------------

    public function testStrongPasswordReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Password123!'));
    }

    public function testStrongPasswordWithAllSpecialCharsReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('AbcDef1@ghij'));
    }

    public function testPasswordExactly12CharsReturnsTrue(): void
    {
        // Exactement 12 caractères avec toutes les catégories
        $this->assertTrue(PasswordValidator::isStrong('Aa1!aaaaaaaa'));
    }

    public function testLongComplexPasswordReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('MyVeryLong&SecurePassword99!'));
    }

    // ----------------------------------------------------------------
    // Trop court — doit retourner false
    // ----------------------------------------------------------------

    public function testPasswordTooShortReturnsFalse(): void
    {
        // 11 caractères même avec toutes les catégories
        $this->assertFalse(PasswordValidator::isStrong('Aa1!aaaaaaa'));
    }

    public function testEmptyPasswordReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong(''));
    }

    public function testShortPasswordReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('Abc123!'));
    }

    // ----------------------------------------------------------------
    // Manque majuscule — doit retourner false
    // ----------------------------------------------------------------

    public function testNoUppercaseReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('password123!abc'));
    }

    // ----------------------------------------------------------------
    // Manque minuscule — doit retourner false
    // ----------------------------------------------------------------

    public function testNoLowercaseReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('PASSWORD123!ABC'));
    }

    // ----------------------------------------------------------------
    // Manque chiffre — doit retourner false
    // ----------------------------------------------------------------

    public function testNoDigitReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('PasswordNoDigit!'));
    }

    // ----------------------------------------------------------------
    // Manque caractère spécial — doit retourner false
    // ----------------------------------------------------------------

    public function testNoSpecialCharReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('Password1234567'));
    }

    // ----------------------------------------------------------------
    // Exactement les limites
    // ----------------------------------------------------------------

    public function testPassword11CharsReturnsFalse(): void
    {
        // 11 caractères
        $this->assertFalse(PasswordValidator::isStrong('Aa1!aaaaaaa'));
    }

    public function testPassword13CharsReturnsTrue(): void
    {
        // 13 caractères
        $this->assertTrue(PasswordValidator::isStrong('Aa1!aaaaaaaa1'));
    }

    // ----------------------------------------------------------------
    // Caractères spéciaux acceptés
    // ----------------------------------------------------------------

    public function testSpecialCharAtSymbolReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Password123@abc'));
    }

    public function testSpecialCharHashReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Password123#abc'));
    }

    public function testSpecialCharBracketReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Password123[abc'));
    }

    // ----------------------------------------------------------------
    // getErrors() — doit retourner un tableau vide si valide
    // ----------------------------------------------------------------

    public function testGetErrorsReturnsEmptyArrayForStrongPassword(): void
    {
        $this->assertSame([], PasswordValidator::getErrors('Password123!'));
    }

    // ----------------------------------------------------------------
    // getErrors() — longueur insuffisante
    // ----------------------------------------------------------------

    public function testGetErrorsContainsMinKeyWhenTooShort(): void
    {
        $errors = PasswordValidator::getErrors('Aa1!aaaaaaa'); // 11 chars
        $this->assertContains('validation.password_min', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — majuscule manquante
    // ----------------------------------------------------------------

    public function testGetErrorsContainsUppercaseKeyWhenMissing(): void
    {
        $errors = PasswordValidator::getErrors('password123!abc');
        $this->assertContains('validation.password_uppercase', $errors);
        $this->assertNotContains('validation.password_lowercase', $errors);
        $this->assertNotContains('validation.password_digit', $errors);
        $this->assertNotContains('validation.password_special', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — minuscule manquante
    // ----------------------------------------------------------------

    public function testGetErrorsContainsLowercaseKeyWhenMissing(): void
    {
        $errors = PasswordValidator::getErrors('PASSWORD123!ABC');
        $this->assertContains('validation.password_lowercase', $errors);
        $this->assertNotContains('validation.password_uppercase', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — chiffre manquant
    // ----------------------------------------------------------------

    public function testGetErrorsContainsDigitKeyWhenMissing(): void
    {
        $errors = PasswordValidator::getErrors('PasswordNoDigit!');
        $this->assertContains('validation.password_digit', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — caractère spécial manquant
    // ----------------------------------------------------------------

    public function testGetErrorsContainsSpecialKeyWhenMissing(): void
    {
        $errors = PasswordValidator::getErrors('Password1234567');
        $this->assertContains('validation.password_special', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — plusieurs règles manquantes simultanément
    // ----------------------------------------------------------------

    public function testGetErrorsContainsMultipleKeysWhenSeveralRulesFail(): void
    {
        // Mot de passe vide : toutes les règles échouent
        $errors = PasswordValidator::getErrors('');
        $this->assertContains('validation.password_min', $errors);
        $this->assertContains('validation.password_uppercase', $errors);
        $this->assertContains('validation.password_lowercase', $errors);
        $this->assertContains('validation.password_digit', $errors);
        $this->assertContains('validation.password_special', $errors);
        $this->assertCount(5, $errors);
    }

    public function testGetErrorsOnlyMinKeyWhenJustTooShort(): void
    {
        // Toutes les règles sauf la longueur sont satisfaites
        $errors = PasswordValidator::getErrors('Aa1!aaaaaaa'); // 11 chars
        $this->assertContains('validation.password_min', $errors);
        $this->assertNotContains('validation.password_uppercase', $errors);
        $this->assertNotContains('validation.password_lowercase', $errors);
        $this->assertNotContains('validation.password_digit', $errors);
        $this->assertNotContains('validation.password_special', $errors);
    }
}
