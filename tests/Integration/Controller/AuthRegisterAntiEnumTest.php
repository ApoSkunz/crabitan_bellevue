<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour la sécurité anti-énumération et les protections de l'inscription.
 *
 * Couvre :
 * - R5 : anti-énumération — email existant → même réponse qu'inscription réussie
 * - R2 : rate limiting inscription (max 5 par IP sur la session)
 * - E2 : validation ANSSI granulaire (uppercase, lowercase, digit, special, longueur)
 * - B1 : passwords mismatch côté serveur
 * - Inscription nominale réussie (individual + company)
 */
class AuthRegisterAntiEnumTest extends IntegrationTestCase
{
    private const CSRF = 'antienum-csrf-token';

    /** Payload d'inscription valide pour un compte individuel. */
    private const VALID_INDIVIDUAL = [
        'account_type'     => 'individual',
        'civility'         => 'M',
        'lastname'         => 'Dupont',
        'firstname'        => 'Jean',
        'email'            => 'jean.dupont@example.com',
        'password'         => 'Str0ng&Secure!',
        'password_confirm' => 'Str0ng&Secure!',
        'newsletter'       => '0',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_COOKIE  = [];
        // csrf + reset du bucket rate-limiting session pour isolation
        $_SESSION = [
            'csrf'    => self::CSRF,
            '_rl'     => [],
            '_rl_ttl' => [],
        ];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST    = [];
        $_COOKIE  = [];
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Construit un AuthController avec une requête POST simulée.
     *
     * @param string $uri URI de la requête
     * @return AuthController
     */
    private function makeController(string $uri = '/fr/inscription'): AuthController
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new AuthController(new Request());
    }

    /**
     * Insère un compte individuel vérifié en base.
     *
     * @param string $email Adresse email du compte
     * @return int Identifiant du compte créé
     */
    private function insertVerifiedAccount(string $email): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Str0ng&Secure!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Test', 'User', 'M')",
            [$id]
        );
        return $id;
    }

    /**
     * Retourne un payload valide fusionné avec les overrides fournis.
     *
     * @param array<string, string> $overrides Champs à surcharger
     * @return array<string, string>
     */
    private function buildPayload(array $overrides = []): array
    {
        return array_merge(self::VALID_INDIVIDUAL, ['csrf_token' => self::CSRF], $overrides);
    }

    // ----------------------------------------------------------------
    // R5 — Anti-énumération : email existant
    // ----------------------------------------------------------------

    /**
     * R5a : inscription avec un email déjà utilisé → même redirect (/fr) qu'une inscription réussie.
     *
     * Un attaquant ne peut pas déduire qu'un email est déjà enregistré
     * en observant la redirection : elle est identique dans les deux cas.
     */
    public function testRegisterExistingEmailRedirectsIdenticallyToSuccess(): void
    {
        $this->insertVerifiedAccount('existing@example.com');

        $_POST = $this->buildPayload(['email' => 'existing@example.com']);

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            // Même destination que le succès — indiscernable depuis le client
            $this->assertSame('/fr', $e->location);
        }
    }

    /**
     * R5b : inscription avec un email existant → flash info = auth.register_success (message générique).
     *
     * Le message affiché doit être le même que pour une inscription normale.
     */
    public function testRegisterExistingEmailFlashIsIdenticalToSuccessMessage(): void
    {
        $this->insertVerifiedAccount('dupe-flash@example.com');

        $_POST = $this->buildPayload(['email' => 'dupe-flash@example.com']);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        // Anti-énumération R5 : flag register_success identique à une inscription réussie
        $this->assertNotEmpty(
            $_SESSION['flash']['register_success'] ?? null,
            'Le flag register_success doit être posé (même réponse qu\'un succès)'
        );
        $this->assertArrayNotHasKey('info', $_SESSION['flash'] ?? []);
    }

    /**
     * R5c : inscription avec un email existant → aucune erreur inline sur le champ email.
     *
     * Les erreurs de validation exposées dans register_errors ne doivent
     * pas contenir de clé 'email' — cela trahirait l'existence du compte.
     */
    public function testRegisterExistingEmailExposesNoEmailError(): void
    {
        $this->insertVerifiedAccount('no-email-error@example.com');

        $_POST = $this->buildPayload(['email' => 'no-email-error@example.com']);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayNotHasKey(
            'email',
            $errors,
            'Aucune erreur de champ email ne doit être exposée pour ne pas révéler l\'existence du compte'
        );
    }

    /**
     * R5d : inscription avec un email existant → aucun doublon créé en base.
     */
    public function testRegisterExistingEmailCreatesNoExtraAccount(): void
    {
        $this->insertVerifiedAccount('no-dup@example.com');

        $_POST = $this->buildPayload(['email' => 'no-dup@example.com']);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $count = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM accounts WHERE email = ?",
            ['no-dup@example.com']
        );
        $this->assertSame(1, (int) ($count['cnt'] ?? 0), 'Un seul compte doit exister pour cet email');
    }

    // ----------------------------------------------------------------
    // R2 — Rate limiting inscription
    // ----------------------------------------------------------------

    /**
     * R2 : après MAX_REGISTER_IP (5) tentatives d'inscription, la 6e est bloquée.
     *
     * Le bucket session est utilisé comme fallback quand APCu est absent.
     * On simule 5 tentatives en pré-remplissant le compteur puis on vérifie
     * que la 6e produit le flash auth.too_many_registrations.
     */
    public function testRegisterRateLimitBlocksAfterMaxAttempts(): void
    {
        // Pré-remplir le bucket session pour atteindre la limite (MAX_REGISTER_IP = 5)
        $ip  = '127.0.0.1';
        $key = 'rl:register:' . $ip . ':count';
        $_SESSION['_rl'][$key] = 5; // seuil atteint

        $_POST = $this->buildPayload(['email' => 'ratelimit@example.com']);

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        // Aucun compte ne doit avoir été créé
        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['ratelimit@example.com']
        );
        $this->assertFalse($account, 'Aucun compte ne doit être créé si le rate limit est atteint');

        // Le flash doit contenir le message de rate limiting (pas le message de succès)
        $flashInfo = $_SESSION['flash']['info'] ?? null;
        $this->assertNull($flashInfo, 'Aucun flash info ne doit être émis en cas de rate limit');

        $flashError = $_SESSION['flash']['modal_error'] ?? null;
        $this->assertNotNull($flashError, 'Un flash modal_error doit être émis en cas de rate limit');
    }

    // ----------------------------------------------------------------
    // B1 — Password mismatch
    // ----------------------------------------------------------------

    /**
     * B1 : password et password_confirm différents → erreur validation.password_match sur password_confirm.
     */
    public function testRegisterPasswordMismatchProducesError(): void
    {
        $_POST = $this->buildPayload([
            'email'            => 'mismatch@example.com',
            'password'         => 'Str0ng&Secure!',
            'password_confirm' => 'Str0ng&Different1!',
        ]);

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password_confirm', $errors, 'Une erreur password_confirm doit être présente');
        $this->assertStringContainsString(
            __('validation.password_match'),
            $errors['password_confirm'],
            'Le message doit contenir validation.password_match'
        );

        // Aucun compte créé
        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['mismatch@example.com']
        );
        $this->assertFalse($account);
    }

    // ----------------------------------------------------------------
    // E2 — Validation ANSSI granulaire
    // ----------------------------------------------------------------

    /**
     * E2-uppercase : mot de passe sans majuscule → erreur validation.password_uppercase.
     */
    public function testRegisterPasswordMissingUppercaseProducesSpecificError(): void
    {
        $_POST = $this->buildPayload([
            'email'            => 'no-upper-antienum@example.com',
            'password'         => 'str0ng&secure!',
            'password_confirm' => 'str0ng&secure!',
        ]);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString(
            __('validation.password_uppercase'),
            $errors['password'],
            'L\'erreur doit mentionner l\'absence de majuscule'
        );
    }

    /**
     * E2-lowercase : mot de passe sans minuscule → erreur validation.password_lowercase.
     */
    public function testRegisterPasswordMissingLowercaseProducesSpecificError(): void
    {
        $_POST = $this->buildPayload([
            'email'            => 'no-lower-antienum@example.com',
            'password'         => 'STR0NG&SECURE!',
            'password_confirm' => 'STR0NG&SECURE!',
        ]);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString(
            __('validation.password_lowercase'),
            $errors['password'],
            'L\'erreur doit mentionner l\'absence de minuscule'
        );
    }

    /**
     * E2-digit : mot de passe sans chiffre → erreur validation.password_digit.
     */
    public function testRegisterPasswordMissingDigitProducesSpecificError(): void
    {
        $_POST = $this->buildPayload([
            'email'            => 'no-digit-antienum@example.com',
            'password'         => 'Strong&Secure!!',
            'password_confirm' => 'Strong&Secure!!',
        ]);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString(
            __('validation.password_digit'),
            $errors['password'],
            'L\'erreur doit mentionner l\'absence de chiffre'
        );
    }

    /**
     * E2-special : mot de passe sans caractère spécial → erreur validation.password_special.
     */
    public function testRegisterPasswordMissingSpecialCharProducesSpecificError(): void
    {
        $_POST = $this->buildPayload([
            'email'            => 'no-special-antienum@example.com',
            'password'         => 'Str0ngSecure12',
            'password_confirm' => 'Str0ngSecure12',
        ]);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString(
            __('validation.password_special'),
            $errors['password'],
            'L\'erreur doit mentionner l\'absence de caractère spécial'
        );
    }

    /**
     * E2-length : mot de passe trop court (< 12 caractères) → erreur validation.password_min.
     */
    public function testRegisterPasswordTooShortProducesSpecificError(): void
    {
        $_POST = $this->buildPayload([
            'email'            => 'too-short-antienum@example.com',
            'password'         => 'Sh0rt!',
            'password_confirm' => 'Sh0rt!',
        ]);

        try {
            $this->makeController()->register(['lang' => 'fr']);
        } catch (HttpException) {
            // attendu
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString(
            __('validation.password_min'),
            $errors['password'],
            'L\'erreur doit mentionner la longueur minimale'
        );
    }

    // ----------------------------------------------------------------
    // Inscription nominale réussie
    // ----------------------------------------------------------------

    /**
     * Nominal individual : inscription complète valide → redirect /fr + flash info + compte en BDD.
     */
    public function testRegisterSuccessCreatesAccountAndFlashesInfo(): void
    {
        $_POST = $this->buildPayload(['email' => 'new-individual@example.com']);

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        $this->assertNotEmpty($_SESSION['flash']['register_success'] ?? null);

        $account = self::$db->fetchOne(
            "SELECT email FROM accounts WHERE email = ?",
            ['new-individual@example.com']
        );
        $this->assertNotFalse($account, 'Le compte doit être créé en base');
    }

    /**
     * Nominal company : inscription entreprise valide → redirect /fr + flash info + compte en BDD.
     */
    public function testRegisterCompanyAccountSucceeds(): void
    {
        $_POST = [
            'account_type'     => 'company',
            'company_name'     => 'Vignobles SA',
            'email'            => 'vignobles@example.com',
            'password'         => 'Str0ng&Secure!',
            'password_confirm' => 'Str0ng&Secure!',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        $this->assertNotEmpty($_SESSION['flash']['register_success'] ?? null);

        $account = self::$db->fetchOne(
            "SELECT email FROM accounts WHERE email = ?",
            ['vignobles@example.com']
        );
        $this->assertNotFalse($account, 'Le compte entreprise doit être créé en base');
    }
}
