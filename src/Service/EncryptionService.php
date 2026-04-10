<?php

declare(strict_types=1);

namespace Service;

/**
 * Service de chiffrement applicatif AES-256-GCM.
 *
 * Utilisé pour les colonnes sensibles stockées en base de données
 * (ex : majority_declared_ip, email_change_new_email) — RGPD Art. 32.
 *
 * Format du ciphertext stocké : base64( IV[12] + Tag[16] + ciphertext )
 * La clé est dérivée de DB_ENCRYPT_KEY via SHA-256 → 32 octets garantis.
 *
 * Chaque chiffrement génère un IV aléatoire unique (random_bytes(12)).
 * Deux appels sur la même valeur produisent deux chiffrés différents.
 */
class EncryptionService
{
    private const CIPHER    = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    /**
     * @param string $rawKey Clé brute lue depuis DB_ENCRYPT_KEY (chaîne vide = désactivé)
     */
    public function __construct(string $rawKey = '')
    {
        // SHA-256 → 32 octets, quelle que soit la longueur de la clé saisie
        $this->key = hash('sha256', $rawKey, true);
    }

    /**
     * Construit un EncryptionService depuis la variable d'environnement DB_ENCRYPT_KEY.
     *
     * @return self
     */
    public static function fromEnv(): self
    {
        $key = $_ENV['DB_ENCRYPT_KEY'] ?? (string) getenv('DB_ENCRYPT_KEY');
        return new self(is_string($key) ? $key : '');
    }

    /**
     * Chiffre une valeur en AES-256-GCM avec un IV aléatoire.
     *
     * @param string $plaintext Valeur en clair
     * @return string           Valeur chiffrée encodée en base64 (IV + tag + ciphertext)
     * @throws \RuntimeException Si le chiffrement échoue.
     */
    public function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('EncryptionService: chiffrement échoué.'); // NOSONAR — php:S112
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Déchiffre une valeur chiffrée par encrypt().
     *
     * Retourne null si la valeur est corrompue, le tag invalide ou la clé incorrecte.
     *
     * @param string $encoded Valeur chiffrée encodée en base64
     * @return string|null    Valeur en clair, ou null en cas d'échec
     */
    public function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }
}
