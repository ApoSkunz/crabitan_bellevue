<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Account;

use Controller\Account\AccountProfileController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountProfileController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountProfileControllerTest extends IntegrationTestCase
{
    private const CSRF = 'test-profile-csrf';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte client vérifié avec son profil.
     *
     * @param string $email       Adresse e-mail unique du compte
     * @param string $accountType 'individual' ou 'company'
     * @return int Identifiant du compte créé
     */
    private function insertCustomer(string $email, string $accountType = 'individual'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT), $accountType]
        );

        if ($accountType === 'company') {
            self::$db->insert(
                "INSERT INTO account_companies (account_id, company_name, siret)
                 VALUES (?, 'Acme SARL', '12345678901234')",
                [$id]
            );
        } else {
            self::$db->insert(
                "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                 VALUES (?, 'Dupont', 'Jean', 'M')",
                [$id]
            );
        }

        return $id;
    }

    /**
     * Connecte un utilisateur en posant son cookie JWT et en insérant sa connexion en base.
     *
     * @param int    $userId Identifiant du compte
     * @param string $role   Rôle JWT
     * @return string Token JWT généré
     */
    private function loginAs(int $userId, string $role = 'customer'): string
    {
        $token = Jwt::generate($userId, $role);
        $_COOKIE['auth_token'] = $token;
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        return $token;
    }

    /**
     * Crée un objet Request avec la méthode et l'URI fournies.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return Request
     */
    private function makeRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $uriQuery = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $uriQuery);
        $_GET = array_merge($_GET, $uriQuery);
        return new Request();
    }

    /**
     * Instancie AccountProfileController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountProfileController
     */
    private function makeController(string $method, string $uri): AccountProfileController
    {
        return new AccountProfileController($this->makeRequest($method, $uri));
    }

    // ----------------------------------------------------------------
    // index()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié reçoit la vue dashboard.
     */
    public function testIndexRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('profile.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un compte company authentifié reçoit la vue dashboard.
     */
    public function testIndexRendersForCompany(): void
    {
        $userId = $this->insertCustomer('profile.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-page', $output);
    }

    /**
     * Un message flash info est consommé et la vue s'affiche.
     */
    public function testIndexConsumesFlashInfo(): void
    {
        $userId = $this->insertCustomer('profile.flash@test.local', 'individual');
        $this->loginAs($userId);
        $_SESSION['flash']['info'] = 'some-info';

        ob_start();
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('info', $_SESSION['flash'] ?? []);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testIndexRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte')->index(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // profile()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la page profil.
     */
    public function testProfileRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('profile.view.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('profile', $output);
    }

    /**
     * Un compte company authentifié voit la page profil.
     */
    public function testProfileRendersForCompany(): void
    {
        $userId = $this->insertCustomer('profile.view.co@test.local', 'company');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('profile', $output);
    }

    /**
     * Les flashs profil sont consommés à l'affichage.
     */
    public function testProfileConsumesFlashData(): void
    {
        $userId = $this->insertCustomer('profile.flashview@test.local', 'individual');
        $this->loginAs($userId);
        $_SESSION['flash']['profile_success']   = 'ok';
        $_SESSION['flash']['profile_errors']    = ['firstname' => 'err'];
        $_SESSION['flash']['email_change_sent'] = true;

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('profile_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('profile_errors', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('email_change_sent', $_SESSION['flash'] ?? []);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testProfileRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/profil')->profile(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // updateProfile() — individual
    // ----------------------------------------------------------------

    /**
     * Mise à jour profil individual valide → redirige avec succès.
     */
    public function testUpdateProfileIndividualSuccess(): void
    {
        $userId = $this->insertCustomer('upd.ind@test.local', 'individual');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token' => self::CSRF,
            'civility'   => 'M',
            'firstname'  => 'Pierre',
            'lastname'   => 'Martin',
            'newsletter' => '0',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Mise à jour profil individual avec firstname vide → redirige avec erreur.
     */
    public function testUpdateProfileIndividualMissingFirstname(): void
    {
        $userId = $this->insertCustomer('upd.ind.empty@test.local', 'individual');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token' => self::CSRF,
            'civility'   => 'M',
            'firstname'  => '',
            'lastname'   => 'Martin',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Mise à jour profil individual avec lastname vide → redirige avec erreur.
     */
    public function testUpdateProfileIndividualMissingLastname(): void
    {
        $userId = $this->insertCustomer('upd.ind.nolast@test.local', 'individual');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token' => self::CSRF,
            'civility'   => 'M',
            'firstname'  => 'Pierre',
            'lastname'   => '',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Mise à jour profil company valide → redirige avec succès.
     */
    public function testUpdateProfileCompanySuccess(): void
    {
        $userId = $this->insertCustomer('upd.co@test.local', 'company');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'   => self::CSRF,
            'company_name' => 'Nouveau SARL',
            'siret'        => '98765432109876',
            'newsletter'   => '0',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Mise à jour profil company avec nom vide → erreur.
     */
    public function testUpdateProfileCompanyMissingName(): void
    {
        $userId = $this->insertCustomer('upd.co.empty@test.local', 'company');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'   => self::CSRF,
            'company_name' => '',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * CSRF invalide → redirige avec erreur csrf.
     */
    public function testUpdateProfileCsrfFails(): void
    {
        $userId = $this->insertCustomer('upd.csrf@test.local', 'individual');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testUpdateProfileRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil')->updateProfile(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // requestEmailChange()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testRequestEmailChangeCsrfFails(): void
    {
        $userId = $this->insertCustomer('req.email.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad', 'new_email' => 'new@test.local', 'current_password' => 'Password123!'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
             ->requestEmailChange(['lang' => 'fr']);
    }

    /**
     * Email invalide → redirige avec erreur.
     */
    public function testRequestEmailChangeInvalidEmail(): void
    {
        $userId = $this->insertCustomer('req.email.invalid@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'new_email' => 'not-an-email', 'current_password' => 'Password123!'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
             ->requestEmailChange(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testRequestEmailChangeRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/profil/changer-email')
             ->requestEmailChange(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // confirmEmailChange()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue avec success=false.
     */
    public function testConfirmEmailChangeEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/confirmer')
             ->confirmEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token invalide → vue avec success=false (HttpException du service).
     */
    public function testConfirmEmailChangeInvalidToken(): void
    {
        $_GET = ['token' => 'invalid-token-xyz'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/confirmer?token=invalid-token-xyz')
             ->confirmEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // revokeEmailChange()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue avec success=false.
     */
    public function testRevokeEmailChangeEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/revoquer')
             ->revokeEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable en BDD → vue avec success=false.
     */
    public function testRevokeEmailChangeTokenNotFound(): void
    {
        $_GET = ['token' => 'not-found-token'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/revoquer?token=not-found-token')
             ->revokeEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide → vue avec success=true et revoked=true.
     */
    public function testRevokeEmailChangeSuccess(): void
    {
        $userId = $this->insertCustomer('revoke.email@test.local');
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        self::$db->insert(
            "UPDATE accounts SET email_change_token = ?, email_change_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE id = ?",
            [$hashedToken, $userId]
        );
        $_GET = ['token' => $rawToken];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/email/revoquer?token=' . $rawToken)
             ->revokeEmailChange(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // cancelEmailChange()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testCancelEmailChangeCsrfFails(): void
    {
        $userId = $this->insertCustomer('cancel.email.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/email/annuler')
             ->cancelEmailChange(['lang' => 'fr']);
    }

    /**
     * CSRF valide → token effacé et redirection.
     */
    public function testCancelEmailChangeSuccess(): void
    {
        $userId = $this->insertCustomer('cancel.email@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/email/annuler')
             ->cancelEmailChange(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testCancelEmailChangeRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/email/annuler')
             ->cancelEmailChange(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // updateLangPreference()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → JSON 403.
     */
    public function testUpdateLangPreferenceCsrfFails(): void
    {
        $userId = $this->insertCustomer('lang.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad', 'lang' => 'en'];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
             ->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('csrf', $output);
    }

    /**
     * Langue invalide → JSON 422.
     */
    public function testUpdateLangPreferenceInvalidLang(): void
    {
        $userId = $this->insertCustomer('lang.invalid@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'lang' => 'de'];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
             ->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success'] ?? true);
    }

    /**
     * Langue valide sans redirect_to → JSON success.
     */
    public function testUpdateLangPreferenceSuccess(): void
    {
        $userId = $this->insertCustomer('lang.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'lang' => 'en'];

        ob_start();
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
             ->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success'] ?? false);
    }

    /**
     * Langue valide avec redirect_to valide → redirection.
     */
    public function testUpdateLangPreferenceWithRedirect(): void
    {
        $userId = $this->insertCustomer('lang.redirect@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'lang' => 'en', 'redirect_to' => '/en/boutique'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
             ->updateLangPreference(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testUpdateLangPreferenceRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/preference-langue')
             ->updateLangPreference(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // maskEmail() — static helper
    // ----------------------------------------------------------------

    /**
     * Masque correctement un email standard.
     */
    public function testMaskEmailStandard(): void
    {
        $result = AccountProfileController::maskEmail('jean.dupont@example.com');
        $this->assertStringStartsWith('j***', $result);
        $this->assertStringContainsString('@example.com', $result);
    }

    /**
     * Email sans @ → tout masqué.
     */
    public function testMaskEmailNoAt(): void
    {
        $result = AccountProfileController::maskEmail('noemail');
        $this->assertSame(str_repeat('*', strlen('noemail')), $result);
    }

    /**
     * Email commençant par @ → tout masqué.
     */
    public function testMaskEmailStartsWithAt(): void
    {
        $result = AccountProfileController::maskEmail('@domain.com');
        $this->assertSame(str_repeat('*', strlen('@domain.com')), $result);
    }
}
