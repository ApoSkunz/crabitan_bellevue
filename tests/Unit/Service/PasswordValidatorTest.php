<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\PasswordValidator;

/**
 * Tests unitaires pour PasswordValidator (politique ANSSI MDP 2021 + blocklist + séquences).
 */
class PasswordValidatorTest extends TestCase
{
    // ----------------------------------------------------------------
    // Cas valides — doit retourner true
    // ----------------------------------------------------------------

    public function testStrongPasswordReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Vigilant7#xKqm'));
    }

    public function testStrongPasswordWithAllSpecialCharsReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Kiwi&Blue42sky'));
    }

    public function testPasswordExactly12CharsReturnsTrue(): void
    {
        // Exactement 12 caractères avec toutes les catégories
        $this->assertTrue(PasswordValidator::isStrong('Aa1!aaaaaaaa'));
    }

    public function testLongComplexPasswordReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('MyVeryLong&Secure99!Kiwi'));
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
        $this->assertFalse(PasswordValidator::isStrong('vigilant7#xkqm'));
    }

    // ----------------------------------------------------------------
    // Manque minuscule — doit retourner false
    // ----------------------------------------------------------------

    public function testNoLowercaseReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('VIGILANT7#XKQM'));
    }

    // ----------------------------------------------------------------
    // Manque chiffre — doit retourner false
    // ----------------------------------------------------------------

    public function testNoDigitReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('VigilantNoDigit!'));
    }

    // ----------------------------------------------------------------
    // Manque caractère spécial — doit retourner false
    // ----------------------------------------------------------------

    public function testNoSpecialCharReturnsFalse(): void
    {
        $this->assertFalse(PasswordValidator::isStrong('Vigilant7531abc'));
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
        $this->assertTrue(PasswordValidator::isStrong('Vigilant7@xKqm'));
    }

    public function testSpecialCharHashReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Vigilant7#xKqm'));
    }

    public function testSpecialCharBracketReturnsTrue(): void
    {
        $this->assertTrue(PasswordValidator::isStrong('Vigilant7[xKqm'));
    }

    // ----------------------------------------------------------------
    // getErrors() — doit retourner un tableau vide si valide
    // ----------------------------------------------------------------

    public function testGetErrorsReturnsEmptyArrayForStrongPassword(): void
    {
        $this->assertSame([], PasswordValidator::getErrors('Vigilant7#xKqm'));
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
        $errors = PasswordValidator::getErrors('vigilant7#xkqm');
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
        $errors = PasswordValidator::getErrors('VIGILANT7#XKQM');
        $this->assertContains('validation.password_lowercase', $errors);
        $this->assertNotContains('validation.password_uppercase', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — chiffre manquant
    // ----------------------------------------------------------------

    public function testGetErrorsContainsDigitKeyWhenMissing(): void
    {
        $errors = PasswordValidator::getErrors('VigilantNoDigit!');
        $this->assertContains('validation.password_digit', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — caractère spécial manquant
    // ----------------------------------------------------------------

    public function testGetErrorsContainsSpecialKeyWhenMissing(): void
    {
        $errors = PasswordValidator::getErrors('Vigilant7531abc');
        $this->assertContains('validation.password_special', $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — plusieurs règles manquantes simultanément
    // ----------------------------------------------------------------

    public function testGetErrorsContainsMultipleKeysWhenSeveralRulesFail(): void
    {
        // Mot de passe vide : toutes les règles de base échouent
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
        $errors = PasswordValidator::getErrors('Aa1!aaaaaaa');
        $this->assertSame(['validation.password_min'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlyUppercaseViolated(): void
    {
        $errors = PasswordValidator::getErrors('vigilant7#xkqm');
        $this->assertSame(['validation.password_uppercase'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlyLowercaseViolated(): void
    {
        $errors = PasswordValidator::getErrors('VIGILANT7#XKQM');
        $this->assertSame(['validation.password_lowercase'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlyDigitViolated(): void
    {
        $errors = PasswordValidator::getErrors('VigilantNoDigit!');
        $this->assertSame(['validation.password_digit'], $errors);
    }

    public function testGetErrorsExactlyOneErrorWhenOnlySpecialViolated(): void
    {
        $errors = PasswordValidator::getErrors('Vigilant7531abc');
        $this->assertSame(['validation.password_special'], $errors);
    }

    // ----------------------------------------------------------------
    // getErrors() — exactement 12 chars valide
    // ----------------------------------------------------------------

    public function testGetErrorsEmptyArrayForExactly12CharsValid(): void
    {
        $errors = PasswordValidator::getErrors('Aa1!aaaaaaaa');
        $this->assertSame([], $errors);
    }

    // ----------------------------------------------------------------
    // Cohérence isStrong() ↔ getErrors() === []
    // ----------------------------------------------------------------

    public function testIsStrongReturnsTrueWhenGetErrorsIsEmpty(): void
    {
        $password = 'Vigilant7#xKqm';
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
        $validPasswords = [
            'Vigilant7#xKqm',
            'Aa1!aaaaaaaa',
            'MyVeryLong&Secure99!Kiwi',
            'Kiwi&Blue42sky',
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
        $invalidPasswords = [
            '',
            'Aa1!aaaaaaa',        // 11 chars
            'vigilant7#xkqm',     // pas de majuscule
            'VIGILANT7#XKQM',     // pas de minuscule
            'VigilantNoDigit!',   // pas de chiffre
            'Vigilant7531abc',    // pas de spécial
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

    // ----------------------------------------------------------------
    // Blocklist & séquences — informatifs uniquement (ne bloquent plus)
    // isCommon() et hasSequence() alimentent l'indicateur front mais ne
    // font pas échouer la validation serveur.
    // ----------------------------------------------------------------

    public function testCommonWordPasswordPassesAnssiRules(): void
    {
        // 'password' est dans la blocklist mais respecte les 5 règles ANSSI
        $this->assertTrue(PasswordValidator::isStrong('Password123!'));
    }

    public function testLeetSpeakPasswordPassesAnssiRules(): void
    {
        // Leet-speak de 'password' mais respecte les 5 règles ANSSI
        $this->assertTrue(PasswordValidator::isStrong('P@ssw0rd123!'));
    }

    public function testAzertyPasswordPassesAnssiRules(): void
    {
        // 'azerty' dans la blocklist mais respecte les 5 règles ANSSI
        $this->assertTrue(PasswordValidator::isStrong('Azerty2024!X'));
    }

    public function testGetErrorsDoesNotContainTooCommonKey(): void
    {
        // La clé 'too_common' ne doit plus apparaître dans getErrors()
        $errors = PasswordValidator::getErrors('P@ssw0rd123!');
        $this->assertNotContains('validation.password_too_common', $errors);
    }

    public function testAscendingSequencePassesAnssiRules(): void
    {
        // Séquence 'abcd' informative mais ne bloque plus la validation
        $this->assertTrue(PasswordValidator::isStrong('abcd1234!Xkqm'));
    }

    public function testDescendingSequencePassesAnssiRules(): void
    {
        // Séquence 'dcba' informative mais ne bloque plus la validation
        $this->assertTrue(PasswordValidator::isStrong('dcba9876!Xkqm'));
    }

    public function testGetErrorsDoesNotContainTooCommonKeyForSequence(): void
    {
        $errors = PasswordValidator::getErrors('Xkqm1234!abcd');
        $this->assertNotContains('validation.password_too_common', $errors);
    }

    public function testThreeCharsRunIsNotASequence(): void
    {
        // abc = 3 chars consécutifs → ne déclenche pas la pénalité front
        $this->assertTrue(PasswordValidator::isStrong('AbcXkqm7#yZzz'));
    }
}
