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
 * - At least one special character (any non-alphanumeric character)
 * - Not a common/dictionary password (after leet-speak normalization)
 * - No ascending or descending sequence of 4+ consecutive characters
 */
class PasswordValidator
{
    /**
     * Matches any non-alphanumeric, non-space character.
     */
    private const PATTERN_SPECIAL = '/[^a-zA-Z0-9\s]/';

    /**
     * Common passwords and patterns (checked after leet-speak normalization).
     *
     * @var string[]
     */
    private const BLOCKLIST = [
        'password', 'passw0rd', 'motdepasse', 'bienvenue', 'bienvenu',
        'welcome', 'admin', 'administrator', 'root', 'user',
        'azerty', 'qwerty', 'azertyuiop', 'qwertyuiop',
        'abcdefgh', 'abcdef', '123456', '1234567', '12345678',
        'iloveyou', 'letmein', 'sunshine', 'dragon', 'monkey',
        'chateau', 'crabitan', 'bellevue', 'bordeaux',
        'soleil', 'printemps', 'automne', 'hiver', 'ete',
        'france', 'paris', 'marseille', 'lyon',
        'football', 'basketball', 'tennis',
        'superman', 'batman', 'spiderman',
        'starwars', 'matrix',
        'linkedin', 'facebook', 'google', 'twitter', 'instagram',
        'master', 'secret', 'login', 'access', 'test',
    ];

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
        if (self::isCommon($password) || self::hasSequence($password)) {
            $errors[] = 'validation.password_too_common';
        }

        return $errors;
    }

    /**
     * Returns true if the password contains a common word after leet-speak normalization.
     *
     * @param string $password
     * @return bool
     */
    private static function isCommon(string $password): bool
    {
        $normalized = self::normalizeLeet(strtolower($password));
        foreach (self::BLOCKLIST as $word) {
            if (str_contains($normalized, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the password contains an ascending or descending
     * sequence of 4 or more consecutive characters (e.g. "1234", "abcd", "9876").
     *
     * @param string $password
     * @return bool
     */
    private static function hasSequence(string $password): bool
    {
        $lower = strtolower($password);
        $asc   = 1;
        $dsc   = 1;
        for ($i = 1, $len = strlen($lower); $i < $len; $i++) {
            $diff = ord($lower[$i]) - ord($lower[$i - 1]);
            $asc  = $diff === 1  ? $asc + 1 : 1;
            $dsc  = $diff === -1 ? $dsc + 1 : 1;
            if ($asc >= 4 || $dsc >= 4) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalizes common leet-speak substitutions to their base characters.
     *
     * @param string $password Already lowercased input.
     * @return string
     */
    private static function normalizeLeet(string $password): string
    {
        return strtr($password, [
            '@' => 'a', '4' => 'a',
            '3' => 'e',
            '1' => 'i', '!' => 'i',
            '0' => 'o',
            '$' => 's', '5' => 's',
            '7' => 't', '+' => 't',
        ]);
    }
}
