<?php

declare(strict_types=1);

namespace Service;

/**
 * Validates password strength according to ANSSI MDP 2021 guidelines.
 *
 * Blocking rules (validation):
 * - Minimum 12 characters
 * - At least one uppercase letter [A-Z]
 * - At least one lowercase letter [a-z]
 * - At least one digit [0-9]
 * - At least one special character (any non-alphanumeric character)
 *
 * Informational only (not blocking — used by the front-end strength indicator):
 * - Not a common/dictionary password (after leet-speak normalization) → isCommon()
 * - No ascending or descending sequence of 4+ consecutive characters → hasSequence()
 */
class PasswordValidator
{
    /**
     * Matches any non-alphanumeric, non-space character.
     */
    private const PATTERN_SPECIAL = '/[^a-zA-Z0-9\s]/';


    /**
     * Returns true if the password meets all strength requirements.
     *
     * @param string $password The plain-text password to evaluate.
     * @return bool
     */
    public static function isStrong(string $password): bool
    {
        return self::getErrors($password) === [];
    }

    /**
     * Returns a list of i18n error keys for each rule the password fails.
     *
     * @param string $password The plain-text password to evaluate.
     * @return array<string>
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
