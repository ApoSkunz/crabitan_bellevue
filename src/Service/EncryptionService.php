<?php

declare(strict_types=1);

namespace Service;

/**
 * Service de chiffrement applicatif AES-256-GCM.
 *
 * Utilisé pour les colonnes sensibles stockées en base de données — RGPD Art. 32.
 *
 * Format new  : ENC:<base64( IV[12] + Tag[16] + ciphertext )>
 * Format legacy (avant 1.0.1) : <base64( IV[12] + Tag[16] + ciphertext )>
 *
 * decrypt() supporte les deux formats pour la backward compatibility pendant la
 * migration. Une fois encrypt_existing_addresses.php exécuté en prod, toutes les
 * valeurs auront le préfixe ENC:.
 *
 * La clé est dérivée de DB_ENCRYPT_KEY via SHA-256 → 32 octets garantis.
 * Chaque chiffrement génère un IV aléatoire unique.
 */
class EncryptionService
{
    private const CIPHER     = 'aes-256-gcm';
    private const IV_LENGTH  = 12;
    private const TAG_LENGTH = 16;
    private const PREFIX     = 'ENC:';

    private string $key;

    /**
     * @param string $rawKey Clé brute lue depuis DB_ENCRYPT_KEY
     */
    public function __construct(string $rawKey = '')
    {
        $this->key = hash('sha256', $rawKey, true);
    }

    /**
     * Construit un EncryptionService depuis DB_ENCRYPT_KEY.
     *
     * @return self
     * @throws \RuntimeException Si DB_ENCRYPT_KEY est absent ou vide.
     */
    public static function fromEnv(): self
    {
        $key = $_ENV['DB_ENCRYPT_KEY'] ?? '';
        if (!is_string($key) || $key === '') {
            throw new \RuntimeException('EncryptionService: DB_ENCRYPT_KEY manquante ou vide.'); // NOSONAR — php:S112
        }
        return new self($key);
    }

    /**
     * Chiffre une valeur en AES-256-GCM avec un IV aléatoire.
     *
     * @param string $plaintext Valeur en clair
     * @return string           ENC:<base64(IV + tag + ciphertext)>
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

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Déchiffre une valeur chiffrée par encrypt().
     *
     * Supporte le format legacy (sans préfixe ENC:) pour la période de migration.
     * Retourne null si la valeur est corrompue, le tag invalide ou la clé incorrecte.
     *
     * @param string $encoded Valeur chiffrée (avec ou sans préfixe ENC:)
     * @return string|null    Valeur en clair, ou null en cas d'échec
     */
    public function decrypt(string $encoded): ?string
    {
        $b64 = str_starts_with($encoded, self::PREFIX)
            ? substr($encoded, strlen(self::PREFIX))
            : $encoded;

        $raw = base64_decode($b64, true);

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

    /**
     * Indique si une valeur a été chiffrée par cette classe (préfixe ENC:).
     *
     * Utile pour le script de migration one-shot : permet de distinguer les
     * valeurs déjà migrées des valeurs legacy ou en clair.
     *
     * @param string $value Valeur à tester
     * @return bool
     */
    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
