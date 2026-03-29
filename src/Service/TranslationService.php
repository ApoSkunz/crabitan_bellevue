<?php

declare(strict_types=1);

namespace Service;

/**
 * Traduction automatique FR → EN via l'API DeepL Free (500 000 caractères/mois).
 * Retourne le texte original si l'API est indisponible ou non configurée.
 *
 * Variable d'environnement :
 *   - TRANSLATION_API_KEY : clé API DeepL Free (compte sur deepl.com)
 *
 * Documentation : https://www.deepl.com/docs-api
 */
class TranslationService
{
    private const ENDPOINT = 'https://api-free.deepl.com/v2/translate';

    private string $apiKey;

    /** @var \Closure(string, mixed): (string|false) */
    private \Closure $httpFetch;

    /**
     * @param string                                        $apiKey    Clé API DeepL Free
     * @param \Closure(string, mixed): (string|false)|null $httpFetch Injectable pour les tests unitaires
     */
    public function __construct(string $apiKey, ?\Closure $httpFetch = null)
    {
        $this->apiKey    = $apiKey;
        $this->httpFetch = $httpFetch ?? static function (string $url, mixed $ctx): string|false {
            return @file_get_contents($url, false, $ctx);
        };
    }

    /**
     * Traduit un texte du français vers l'anglais.
     * Retourne le texte original si la clé est absente ou si l'API échoue.
     *
     * @param string $text   Texte source (français)
     * @param string $source Langue source (défaut : 'fr')
     * @param string $target Langue cible  (défaut : 'en')
     * @return string Texte traduit, ou texte original en cas d'échec
     */
    // NOSONAR — php:S1142 : early returns de validation intentionnels
    public function translate(string $text, string $source = 'fr', string $target = 'en'): string
    {
        if ($this->apiKey === '' || $text === '') {
            return $text;
        }

        $payload = http_build_query([
            'text'        => $text,
            'source_lang' => strtoupper($source),
            'target_lang' => self::mapTarget($target),
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Authorization: DeepL-Auth-Key {$this->apiKey}\r\nContent-Type: application/x-www-form-urlencoded",
                'content'       => $payload,
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $raw = ($this->httpFetch)(self::ENDPOINT, $ctx);
            if ($raw === false) {
                return $text;
            }
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data['translations'][0]['text'])) {
                return (string) $data['translations'][0]['text'];
            }
        } catch (\Throwable) {
            // Traduction optionnelle — retourne le texte original en cas d'échec
        }

        return $text;
    }

    /**
     * Convertit un code langue générique vers le format DeepL.
     * DeepL exige 'EN-US' ou 'EN-GB', pas simplement 'EN'.
     */
    private static function mapTarget(string $lang): string
    {
        return match (strtolower($lang)) {
            'en'    => 'EN-US',
            default => strtoupper($lang),
        };
    }
}
