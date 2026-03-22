<?php

declare(strict_types=1);

namespace Core;

class Lang
{
    private static array $translations = [];

    public static function load(string $lang): void
    {
        $file = LANG_PATH . '/' . $lang . '.php';
        if (file_exists($file)) {
            self::$translations = require $file; // NOSONAR — require_once retournerait true au 2e appel
        }
    }

    public static function get(string $key, array $replace = []): string
    {
        $translation = self::$translations[$key] ?? $key;

        foreach ($replace as $placeholder => $value) {
            $translation = str_replace(':' . $placeholder, $value, $translation);
        }

        return $translation;
    }
}

// Helper global
function __(string $key, array $replace = []): string // NOSONAR — helper global intentionnel, requis dans les vues
{
    return Lang::get($key, $replace);
}
