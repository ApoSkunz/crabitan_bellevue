<?php

declare(strict_types=1);

namespace Core;

/**
 * Gestion des traductions i18n.
 * Charge les fichiers de langue et expose la méthode get() pour les vues.
 */
class Lang
{
    private static array $translations = [];

    /**
     * Charge les traductions depuis le fichier de langue correspondant.
     *
     * @param string $lang Code langue (ex. 'fr', 'en')
     * @return void
     */
    public static function load(string $lang): void
    {
        $file = LANG_PATH . '/' . $lang . '.php';
        if (file_exists($file)) {
            self::$translations = require $file; // NOSONAR — require_once retournerait true au 2e appel
        }
    }

    /**
     * Retourne la traduction d'une clé avec remplacement optionnel de variables.
     *
     * @param string               $key     Clé de traduction (ex. 'auth.login')
     * @param array<string, mixed> $replace Substitutions (ex. ['name' => 'Alice'] remplace ':name')
     * @return string Texte traduit, ou la clé si absente
     */
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
