<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour les fonctionnalités TTL du token de vérification email.
 *
 * Couvre :
 *  - findByVerificationToken() token valide (non expiré)
 *  - findByVerificationToken() token expiré (expires_at dans le passé)
 *  - findByVerificationToken() expires_at NULL (rétrocompatibilité anciens tokens)
 *  - create() insère email_verification_token_expires_at à NOW() + 24 H
 *  - verifyEmail() nullifie email_verification_token_expires_at
 *  - Token déjà consommé (après verifyEmail) → findByVerificationToken retourne false
 */
class AccountModelTokenTtlTest extends IntegrationTestCase
{
    private AccountModel $model;

    /**
     * Initialise le modèle et garantit que la colonne TTL existe.
     *
     * @return void
     */
    /**
     * Ajoute la colonne TTL une seule fois avant tous les tests de la classe.
     * DOIT être dans setUpBeforeClass : un ALTER TABLE déclenche un commit implicite
     * MySQL qui tuerait la transaction de chaque test si appelé dans setUp().
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // MySQL 8 ne supporte pas IF NOT EXISTS sur ADD COLUMN — on vérifie manuellement.
        $col = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'accounts'
               AND COLUMN_NAME  = 'email_verification_token_expires_at'"
        );

        if ((int) ($col['cnt'] ?? 0) === 0) {
            self::$db->execute(
                "ALTER TABLE accounts ADD COLUMN
                 email_verification_token_expires_at DATETIME DEFAULT NULL
                 AFTER email_verification_token"
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    /**
     * Insère un compte minimal avec un token de vérification et une expiration configurable.
     *
     * @param string      $email     Adresse email unique pour ce test
     * @param string      $token     Token de vérification
     * @param string|null $expiresAt Valeur SQL de l'expiration (NULL, expression ou littéral)
     * @return int Identifiant du compte inséré
     */
    private function insertAccountWithToken(string $email, string $token, ?string $expiresAt): int
    {
        if ($expiresAt === null) {
            $id = (int) self::$db->insert(
                "INSERT INTO accounts
                 (email, password, lang, newsletter,
                  email_verification_token,
                  email_verification_token_expires_at)
                 VALUES (?, 'h', 'fr', 0, ?, NULL)",
                [$email, $token]
            );
        } else {
            // $expiresAt est une expression SQL — on la passe en littéral via CAST pour
            // éviter l'injection (valeurs contrôlées par les tests uniquement).
            $id = (int) self::$db->insert(
                "INSERT INTO accounts
                 (email, password, lang, newsletter,
                  email_verification_token,
                  email_verification_token_expires_at)
                 VALUES (?, 'h', 'fr', 0, ?, ?)",
                [$email, $token, $expiresAt]
            );
        }

        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'TTL', 'Test', 'M')",
            [$id]
        );

        return $id;
    }

    // ----------------------------------------------------------------
    // findByVerificationToken — token valide (non expiré)
    // ----------------------------------------------------------------

    /**
     * Un token dont expires_at est dans le futur (dans 1 heure) doit être retourné.
     *
     * @return void
     */
    public function testFindByVerificationTokenValidNotExpired(): void
    {
        $token = bin2hex(random_bytes(16));

        // +3h pour être sûrement dans le futur même si MySQL est UTC+2 par rapport à PHP (UTC)
        $futureDate = date('Y-m-d H:i:s', strtotime('+3 hours'));
        $this->insertAccountWithToken('ttl-valid@example.com', $token, $futureDate);

        $account = $this->model->findByVerificationToken($token);

        $this->assertIsArray($account);
        $this->assertSame('ttl-valid@example.com', $account['email']);
    }

    // ----------------------------------------------------------------
    // findByVerificationToken — token expiré
    // ----------------------------------------------------------------

    /**
     * Un token dont expires_at est dans le passé (il y a 1 seconde) doit retourner false.
     *
     * @return void
     */
    public function testFindByVerificationTokenExpiredReturnsFalse(): void
    {
        $token = bin2hex(random_bytes(16));

        $pastDate = date('Y-m-d H:i:s', strtotime('-1 second'));
        $this->insertAccountWithToken('ttl-expired@example.com', $token, $pastDate);

        $result = $this->model->findByVerificationToken($token);

        $this->assertFalse($result);
    }

    /**
     * Un token dont expires_at est très ancien (il y a 25 heures) doit retourner false.
     *
     * @return void
     */
    public function testFindByVerificationTokenExpiredLongAgoReturnsFalse(): void
    {
        $token = bin2hex(random_bytes(16));

        $ancientDate = date('Y-m-d H:i:s', strtotime('-25 hours'));
        $this->insertAccountWithToken('ttl-ancient@example.com', $token, $ancientDate);

        $result = $this->model->findByVerificationToken($token);

        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // findByVerificationToken — expires_at NULL (rétrocompatibilité)
    // ----------------------------------------------------------------

    /**
     * Un token sans TTL (expires_at NULL, anciens comptes) doit être accepté.
     * Rétrocompatibilité : la requête SQL inclut "OR expires_at IS NULL".
     *
     * @return void
     */
    public function testFindByVerificationTokenNullExpiresAtIsRetrocompatible(): void
    {
        $token = bin2hex(random_bytes(16));
        $this->insertAccountWithToken('ttl-null@example.com', $token, null);

        $account = $this->model->findByVerificationToken($token);

        $this->assertIsArray($account);
        $this->assertSame('ttl-null@example.com', $account['email']);
    }

    // ----------------------------------------------------------------
    // create() — email_verification_token_expires_at inséré à NOW() + 24 H
    // ----------------------------------------------------------------

    /**
     * Après create(), le champ email_verification_token_expires_at doit être
     * présent et situé approximativement à NOW() + 24 heures (±60 secondes de tolérance).
     *
     * @return void
     */
    public function testCreateSetsTokenExpiresAtTo24HoursFromNow(): void
    {
        $verificationToken = bin2hex(random_bytes(16));

        $id = $this->model->create(
            'individual',
            'ttl-create@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            $verificationToken,
            'M',
            'Dupont',
            'Jean',
            ''
        );

        // TIMESTAMPDIFF reste dans le contexte MySQL — pas de dérive timezone PHP/MySQL
        $row = self::$db->fetchOne(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), email_verification_token_expires_at) AS delta FROM accounts WHERE id = ?",
            [(int) $id]
        );

        $this->assertIsArray($row);
        $this->assertEqualsWithDelta(
            86400,
            (int) $row['delta'],
            120,
            'email_verification_token_expires_at devrait être à NOW() + 24H (±120 s)'
        );
    }

    /**
     * Après create() pour un compte company, le TTL doit également être défini.
     *
     * @return void
     */
    public function testCreateCompanyAlsoSetsTokenExpiresAt(): void
    {
        $verificationToken = bin2hex(random_bytes(16));

        $id = $this->model->create(
            'company',
            'ttl-company@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            $verificationToken,
            '',
            '',
            '',
            'Château TTL SARL'
        );

        $row = self::$db->fetchOne(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), email_verification_token_expires_at) AS delta FROM accounts WHERE id = ?",
            [(int) $id]
        );

        $this->assertIsArray($row);
        $this->assertEqualsWithDelta(
            86400,
            (int) $row['delta'],
            120,
            'email_verification_token_expires_at doit être à NOW() + 24H pour un compte company'
        );
    }

    // ----------------------------------------------------------------
    // verifyEmail() — nullifie email_verification_token_expires_at
    // ----------------------------------------------------------------

    /**
     * Après verifyEmail(), email_verification_token_expires_at doit être NULL.
     *
     * @return void
     */
    public function testVerifyEmailNullifiesTokenExpiresAt(): void
    {
        $verificationToken = bin2hex(random_bytes(16));

        $id = $this->model->create(
            'individual',
            'ttl-verify@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            $verificationToken,
            'F',
            'Martin',
            'Alice',
            ''
        );

        $this->model->verifyEmail((int) $id);

        $row = self::$db->fetchOne(
            "SELECT email_verification_token,
                    email_verification_token_expires_at,
                    email_verified_at
             FROM accounts WHERE id = ?",
            [(int) $id]
        );

        $this->assertIsArray($row);
        $this->assertNull(
            $row['email_verification_token'],
            'email_verification_token doit être NULL après vérification'
        );
        $this->assertNull(
            $row['email_verification_token_expires_at'],
            'email_verification_token_expires_at doit être NULL après vérification'
        );
        $this->assertNotNull(
            $row['email_verified_at'],
            'email_verified_at doit être défini après vérification'
        );
    }

    // ----------------------------------------------------------------
    // Token déjà utilisé → findByVerificationToken retourne false
    // ----------------------------------------------------------------

    /**
     * Après verifyEmail(), le token est consommé.
     * findByVerificationToken avec ce même token doit retourner false.
     *
     * @return void
     */
    public function testTokenAlreadyUsedAfterVerifyEmailReturnsFalse(): void
    {
        $verificationToken = bin2hex(random_bytes(16));

        $id = $this->model->create(
            'individual',
            'ttl-used@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            $verificationToken,
            'M',
            'Blanc',
            'Pierre',
            ''
        );

        // Vérifie que le token fonctionne avant consommation
        $before = $this->model->findByVerificationToken($verificationToken);
        $this->assertIsArray(
            $before,
            'Le token doit être valide avant verifyEmail()'
        );

        $this->model->verifyEmail((int) $id);

        // Après consommation, le token ne doit plus fonctionner
        $after = $this->model->findByVerificationToken($verificationToken);
        $this->assertFalse(
            $after,
            'findByVerificationToken doit retourner false après verifyEmail()'
        );
    }

    // ----------------------------------------------------------------
    // Cas limite — token inexistant
    // ----------------------------------------------------------------

    /**
     * Un token inexistant en BDD doit toujours retourner false,
     * quelle que soit la logique TTL.
     *
     * @return void
     */
    public function testFindByVerificationTokenUnknownTokenReturnsFalse(): void
    {
        $result = $this->model->findByVerificationToken('nonexistent-ttl-token-xyz');
        $this->assertFalse($result);
    }
}
