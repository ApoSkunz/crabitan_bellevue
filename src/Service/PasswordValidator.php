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
        if (strlen($password) < 12) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        if (!preg_match(self::PATTERN_SPECIAL, $password)) {
            return false;
        }

        return true;
    }
}
