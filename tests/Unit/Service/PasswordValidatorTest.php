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
        $this->assertCount(1, $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — isolation : exactement 1 erreur par règle violée
    // ----------------------------------------------------------------

    public function testGetErrorsExactlyOneErrorWhenOnlyMinViolated(): void
    {
        // 11 chars, toutes autres règles OK → exactement ['validation.password_min']
        $errors = PasswordValidator::getErrors('Aa1!aaaaaaa');
        $this->assertSame(['validation.password_min'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlyUppercaseViolated(): void
    {
        // ≥12 chars, pas de majuscule, tout le reste OK
        $errors = PasswordValidator::getErrors('password123!ab');
        $this->assertSame(['validation.password_uppercase'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlyLowercaseViolated(): void
    {
        // ≥12 chars, pas de minuscule, tout le reste OK
        $errors = PasswordValidator::getErrors('PASSWORD123!AB');
        $this->assertSame(['validation.password_lowercase'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlyDigitViolated(): void
    {
        // ≥12 chars, pas de chiffre, tout le reste OK
        $errors = PasswordValidator::getErrors('PasswordNoDigit!');
        $this->assertSame(['validation.password_digit'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlySpecialViolated(): void
    {
        // ≥12 chars, pas de caractère spécial, tout le reste OK
        $errors = PasswordValidator::getErrors('Password1234567');
        $this->assertSame(['validation.password_special'], $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — exactement 12 chars valide
    // ----------------------------------------------------------------

    public function testGetErrorsEmptyArrayForExactly12CharsValid(): void
    {
        // Exactement 12 caractères couvrant toutes les règles
        $errors = PasswordValidator::getErrors('Aa1!aaaaaaaa');
        $this->assertSame([], $errors);
    }

    // ----------------------------------------------------------------
    // Cohérence isStrong() ↔ getErrors() === []
    // ----------------------------------------------------------------

    public function testIsStrongReturnsTrueWhenGetErrorsIsEmpty(): void
    {
        $password = 'Password123!';
        $this->assertSame([], PasswordValidator::getErrors($password));
        $this->assertTrue(PasswordValidator::isStrong($password));
    }

    public function testIsStrongReturnsFalseWhenGetErrorsIsNotEmpty(): void
    {
        $password = 'weakpassword';
        $errors = PasswordValidator::getErrors($password);
        $this->assertNotEmpty($errors);
        $this->assertFalse(PasswordValidator::isStrong($password));
    }

    public function testIsStrongCoherenceForValidPassword(): void
    {
        // isStrong doit être équivalent à getErrors() === []
        $validPasswords = [
            'Password123!',
            'Aa1!aaaaaaaa',
            'MyVeryLong&SecurePassword99!',
            'AbcDef1@ghij',
        ];
        foreach ($validPasswords as $pwd) {
            $this->assertSame(
                PasswordValidator::getErrors($pwd) === [],
                PasswordValidator::isStrong($pwd),
                "Incohérence isStrong/getErrors pour : $pwd"
            );
        }
    }

    public function testIsStrongCoherenceForInvalidPasswords(): void
    {
        // Pour chaque mot de passe invalide, isStrong doit être false et getErrors non vide
        $invalidPasswords = [
            '',
            'Aa1!aaaaaaa',    // 11 chars
            'password123!ab', // pas de majuscule
            'PASSWORD123!AB', // pas de minuscule
            'PasswordNoDigit!', // pas de chiffre
            'Password1234567',  // pas de spécial
        ];
        foreach ($invalidPasswords as $pwd) {
            $errors = PasswordValidator::getErrors($pwd);
            $this->assertNotEmpty($errors, "getErrors devrait retourner des erreurs pour : $pwd");
            $this->assertFalse(
                PasswordValidator::isStrong($pwd),
                "isStrong devrait retourner false pour : $pwd"
            );
        }
    }
}
