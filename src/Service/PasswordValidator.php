<?php

declare(strict_types=1);

namespace Service;

/**
 * Validates password strength according to ANSSI MDP 2021 guidelines.
 *
 * Rules:
 * - Minimum 12 characters
 * - At least one uppercase letter [A-Z]
 * - At least one lowercase letter [a-z]
 * - At least one digit [0-9]
 * - At least one special character from: !@#$%^&*()_+-=[]{}|;:,.<>?
 */
class PasswordValidator
{
    /**
     * PCRE pattern matching one special character (ANSSI MDP 2021).
     * Character class used directly in preg_match.
     */
    private const PATTERN_SPECIAL = '/[!@#$%^&*()\-_+=\[\]{}|;:,.<>?]/';

    /**
     * Returns true if the password meets ANSSI MDP 2021 strength requirements.
     *
     * @param string $password The plain-text password to evaluate.
     * @return bool True if the password is strong enough, false otherwise.
     */
    public static function isStrong(string $password): bool
    {
        return self::getErrors($password) === [];
    }

    /**
     * Returns a list of i18n error keys for each ANSSI rule the password fails.
     *
     * Each key maps to a translation string that describes the missing rule.
     * An empty array means the password is fully compliant.
     *
     * @param string $password The plain-text password to evaluate.
     * @return array<string> List of i18n error keys, one per failed rule.
     */
    public static function getErrors(string $password): array
    {
        $errors = [];

        if (strlen($password) < 12) {
            $errors[] = 'validation.password_min';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'validation.password_uppercase';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'validation.password_lowercase';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'validation.password_digit';
        }
        if (!preg_match(self::PATTERN_SPECIAL, $password)) {
            $errors[] = 'validation.password_special';
        }

        return $errors;
    }
}
