<?php

declare(strict_types=1);

namespace Core;

/**
 * Accès aux feature flags stockés dans la table `feature_flags`.
 *
 * Chargement en une seule requête par cycle PHP-FPM (cache statique).
 * Les admins/super_admins ne lisent jamais les flags pour leurs accès —
 * seuls les comportements clients sont conditionnés par ces flags.
 *
 * Usage :
 *   FeatureFlags::getBool('invoice_auto_generate', true)
 *   FeatureFlags::getInt('rate_limit_login_max', 10)
 *   FeatureFlags::getFloat('jwt_refresh_threshold', 0.5)
 */
class FeatureFlags
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * Retourne la valeur d'un flag booléen.
     *
     * @param string $key     Clé du flag
     * @param bool   $default Valeur par défaut si flag absent
     * @return bool
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $row = self::row($key);
        return $row !== null ? self::castBool((string) $row['value']) : $default;
    }

    /**
     * Retourne la valeur d'un flag entier.
     *
     * @param string $key     Clé du flag
     * @param int    $default Valeur par défaut si flag absent
     * @return int
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $row = self::row($key);
        return $row !== null ? (int) $row['value'] : $default;
    }

    /**
     * Retourne la valeur d'un flag flottant.
     *
     * @param string $key     Clé du flag
     * @param float  $default Valeur par défaut si flag absent
     * @return float
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $row = self::row($key);
        return $row !== null ? (float) $row['value'] : $default;
    }

    /**
     * Retourne la valeur typée d'un flag selon son type BDD (bool|int|float).
     *
     * @param string $key     Clé du flag
     * @param mixed  $default Valeur par défaut si flag absent
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::row($key);
        if ($row === null) {
            return $default;
        }
        return match ((string) $row['type']) {
            'bool'  => self::castBool((string) $row['value']),
            'int'   => (int) $row['value'],
            'float' => (float) $row['value'],
            default => $row['value'],
        };
    }

    /**
     * Raccourci backward-compatible — Click & Collect activé.
     *
     * @return bool
     */
    public static function isClickCollectEnabled(): bool
    {
        return self::getBool('click_collect_enabled', false);
    }

    /**
     * Injecte le cache directement — usage tests uniquement.
     *
     * @param array<string, array<string, mixed>> $flags Tableau indexé par clé
     * @return void
     */
    public static function setCache(array $flags): void
    {
        self::$cache = $flags;
    }

    /**
     * Réinitialise le cache statique — usage tests uniquement.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * Retourne la ligne brute d'un flag depuis le cache (chargement lazy).
     *
     * @param string $key
     * @return array<string, mixed>|null
     */
    private static function row(string $key): ?array
    {
        if (self::$cache === null) {
            self::$cache = self::loadAll();
        }
        return self::$cache[$key] ?? null;
    }

    /**
     * Charge tous les flags depuis la BDD en une seule requête.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function loadAll(): array
    {
        try {
            $db   = Database::getInstance();
            $rows    = $db->fetchAll('SELECT `key`, `value`, `type` FROM `feature_flags`');
            $indexed = [];
            foreach ($rows as $row) {
                $indexed[(string) $row['key']] = $row;
            }
            return $indexed;
        } catch (\Throwable $e) {
            ErrorLogger::warning($e, '[FeatureFlags]');
            return [];
        }
    }

    /**
     * Convertit une chaîne en booléen (true/1/yes/on → true).
     *
     * @param string $value
     * @return bool
     */
    private static function castBool(string $value): bool
    {
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
}
