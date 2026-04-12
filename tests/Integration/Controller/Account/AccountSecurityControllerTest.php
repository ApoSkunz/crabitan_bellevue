<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Account;

use Controller\Account\AccountSecurityController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountSecurityController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountSecurityControllerTest extends IntegrationTestCase
{
    private const CSRF     = 'test-security-csrf';
    private const PASSWORD = 'Password123!';

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
     * Insère un compte client vérifié.
     *
     * @param string $email       Adresse e-mail unique
     * @param string $accountType 'individual' ou 'company'
     * @return int Identifiant du compte créé
     */
    private function insertCustomer(string $email, string $accountType = 'individual'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash(self::PASSWORD, PASSWORD_BCRYPT), $accountType]
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
     * Crée un objet Request.
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
     * Instancie AccountSecurityController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountSecurityController
     */
    private function makeController(string $method, string $uri): AccountSecurityController
    {
        return new AccountSecurityController($this->makeRequest($method, $uri));
    }

    // ----------------------------------------------------------------
    // security()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié accède à la page sécurité.
     */
    public function testSecurityRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('sec.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('security', $output);
    }

    /**
     * Les flashs security sont consommés à l'affichage.
     */
    public function testSecurityConsumesFlashData(): void
    {
        $userId = $this->insertCustomer('sec.flash@test.local');
        $this->loginAs($userId);
        $_SESSION['flash']['security_success'] = 'ok';
        $_SESSION['flash']['security_errors']  = ['err' => 'msg'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
        ob_get_clean();

        $this->assertArrayNotHasKey('security_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('security_errors', $_SESSION['flash'] ?? []);
    }

    /**
     * Un visiteur non authentifié est redirigé (302).
     */
    public function testSecurityRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/securite')->security(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // changePassword()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testChangePasswordCsrfFails(): void
    {
        $userId = $this->insertCustomer('chpw.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    /**
     * Mauvais mot de passe actuel → redirige avec erreur.
     */
    public function testChangePasswordWrongCurrent(): void
    {
        $userId = $this->insertCustomer('chpw.wrong@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'                 => self::CSRF,
            'current_password'     => 'WrongPass!',
            'new_password'         => 'NewStrong#99!',
            'new_password_confirm' => 'NewStrong#99!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    /**
     * Nouveau mot de passe identique à l'actuel → redirige avec erreur.
     */
    public function testChangePasswordSameAsCurrent(): void
    {
        $userId = $this->insertCustomer('chpw.same@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'                 => self::CSRF,
            'current_password'     => self::PASSWORD,
            'new_password'         => self::PASSWORD,
            'new_password_confirm' => self::PASSWORD,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    /**
     * Nouveau mot de passe trop faible → redirige avec erreur.
     */
    public function testChangePasswordWeakNew(): void
    {
        $userId = $this->insertCustomer('chpw.weak@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'                 => self::CSRF,
            'current_password'     => self::PASSWORD,
            'new_password'         => 'weak',
            'new_password_confirm' => 'weak',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    /**
     * Confirmation ne correspond pas → redirige avec erreur.
     */
    public function testChangePasswordConfirmMismatch(): void
    {
        $userId = $this->insertCustomer('chpw.mismatch@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'                 => self::CSRF,
            'current_password'     => self::PASSWORD,
            'new_password'         => 'NewStrong#99!',
            'new_password_confirm' => 'Different#99!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    /**
     * Tout valide → redirige avec succès.
     */
    public function testChangePasswordSuccess(): void
    {
        $userId = $this->insertCustomer('chpw.ok@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'                 => self::CSRF,
            'current_password'     => self::PASSWORD,
            'new_password'         => 'NewStrong#99!',
            'new_password_confirm' => 'NewStrong#99!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testChangePasswordRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/mot-de-passe')
             ->changePassword(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // revokeAllUserSessions()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige vers sécurité.
     */
    public function testRevokeAllUserSessionsCsrfFails(): void
    {
        $userId = $this->insertCustomer('revall.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/sessions/revoquer-toutes')
             ->revokeAllUserSessions(['lang' => 'fr']);
    }

    /**
     * CSRF valide → toutes sessions révoquées, redirection home.
     */
    public function testRevokeAllUserSessionsSuccess(): void
    {
        $userId = $this->insertCustomer('revall.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/sessions/revoquer-toutes')
             ->revokeAllUserSessions(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testRevokeAllUserSessionsRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/sessions/revoquer-toutes')
             ->revokeAllUserSessions(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // revokeSession()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige vers sécurité sans révoquer.
     */
    public function testRevokeSessionCsrfFails(): void
    {
        $userId = $this->insertCustomer('revsess.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/session/1/revoquer')
             ->revokeSession(['lang' => 'fr', 'id' => '1']);
    }

    /**
     * Session existante révoquée, ce n'est pas la session courante → redirige vers sécurité.
     */
    public function testRevokeSessionOtherSession(): void
    {
        $userId = $this->insertCustomer('revsess.other@test.local');
        $token = $this->loginAs($userId);

        // Deuxième connexion (à révoquer)
        $otherToken = Jwt::generate($userId, 'customer');
        $connId = (int) self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $otherToken]
        );

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/securite/session/{$connId}/revoquer")
             ->revokeSession(['lang' => 'fr', 'id' => (string) $connId]);
    }

    /**
     * Révocation de la session courante → logout + redirect home.
     */
    public function testRevokeSessionCurrentSession(): void
    {
        $userId = $this->insertCustomer('revsess.self@test.local');
        $token = $this->loginAs($userId);

        $connId = (int) self::$db->fetchOne(
            "SELECT id FROM connections WHERE token = ?",
            [$token]
        )['id'];

        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/securite/session/{$connId}/revoquer")
             ->revokeSession(['lang' => 'fr', 'id' => (string) $connId]);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testRevokeSessionRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/session/1/revoquer')
             ->revokeSession(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // untrustDevice()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige sans untrust.
     */
    public function testUntrustDeviceCsrfFails(): void
    {
        $userId = $this->insertCustomer('untrust.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad', 'device_token' => 'some-device'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
             ->untrustDevice(['lang' => 'fr']);
    }

    /**
     * CSRF valide mais device_token vide → redirige sans action.
     */
    public function testUntrustDeviceEmptyToken(): void
    {
        $userId = $this->insertCustomer('untrust.empty@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'device_token' => ''];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
             ->untrustDevice(['lang' => 'fr']);
    }

    /**
     * CSRF valide + device_token → untrust et redirection.
     */
    public function testUntrustDeviceSuccess(): void
    {
        $userId = $this->insertCustomer('untrust.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'device_token' => 'device-abc-123'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
             ->untrustDevice(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testUntrustDeviceRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/retirer-confiance')
             ->untrustDevice(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // untrustAllDevices()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testUntrustAllDevicesCsrfFails(): void
    {
        $userId = $this->insertCustomer('untrustall.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/supprimer-toutes')
             ->untrustAllDevices(['lang' => 'fr']);
    }

    /**
     * CSRF valide → tous appareils supprimés, redirection.
     */
    public function testUntrustAllDevicesSuccess(): void
    {
        $userId = $this->insertCustomer('untrustall.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/supprimer-toutes')
             ->untrustAllDevices(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testUntrustAllDevicesRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/appareils/supprimer-toutes')
             ->untrustAllDevices(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // resetSecurity()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testResetSecurityCsrfFails(): void
    {
        $userId = $this->insertCustomer('reset.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad', 'password' => self::PASSWORD];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
             ->resetSecurity(['lang' => 'fr']);
    }

    /**
     * Mauvais mot de passe → redirige avec erreur.
     */
    public function testResetSecurityWrongPassword(): void
    {
        $userId = $this->insertCustomer('reset.wrong@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'password' => 'WrongPass!'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
             ->resetSecurity(['lang' => 'fr']);
    }

    /**
     * Mot de passe correct → sessions et appareils révoqués, logout.
     */
    public function testResetSecuritySuccess(): void
    {
        $userId = $this->insertCustomer('reset.ok@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'password' => self::PASSWORD];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
             ->resetSecurity(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testResetSecurityRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/reinitialiser')
             ->resetSecurity(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // deleteAccount()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testDeleteAccountCsrfFails(): void
    {
        $userId = $this->insertCustomer('del.csrf@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
             ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Texte de confirmation incorrect → redirige avec erreur.
     */
    public function testDeleteAccountWrongConfirmText(): void
    {
        $userId = $this->insertCustomer('del.text@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF, 'confirm_text' => 'SUPPR', 'confirm_password' => self::PASSWORD];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
             ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Mauvais mot de passe confirmation → redirige avec erreur.
     */
    public function testDeleteAccountWrongPassword(): void
    {
        $userId = $this->insertCustomer('del.pwd@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'             => self::CSRF,
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'WrongPass!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
             ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Tout correct → compte supprimé (soft-delete), logout.
     */
    public function testDeleteAccountSuccess(): void
    {
        $userId = $this->insertCustomer('del.ok@test.local');
        $this->loginAs($userId);
        $_POST = [
            'csrf_token'             => self::CSRF,
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => self::PASSWORD,
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
             ->deleteAccount(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testDeleteAccountRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/securite/supprimer-compte')
             ->deleteAccount(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // newDevice()
    // ----------------------------------------------------------------

    /**
     * Sans pending_device en session → redirige vers home.
     */
    public function testNewDeviceNoPendingSession(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/nouvel-appareil')
             ->newDevice(['lang' => 'fr']);
    }

    /**
     * Avec pending_device en session → vue rendue.
     */
    public function testNewDeviceWithPendingSession(): void
    {
        $_SESSION['pending_device'] = [
            'device_name' => 'Chrome sur Windows',
            'mfa_token'   => 'mfa-token-123',
        ];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/nouvel-appareil')
             ->newDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('mfa-status', $output);
    }

    // ----------------------------------------------------------------
    // confirmDevice()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue device_confirmed avec success=false.
     */
    public function testConfirmDeviceNoToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/appareil/confirmer')
             ->confirmDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token invalide → vue device_confirmed avec success=false.
     */
    public function testConfirmDeviceInvalidToken(): void
    {
        $_GET = ['token' => 'invalid-device-token'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/appareil/confirmer?token=invalid-device-token')
             ->confirmDevice(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // cancelMfa()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue mfa_cancelled avec revoked=false.
     */
    public function testCancelMfaEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/appareil/annuler')
             ->cancelMfa(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable → vue mfa_cancelled avec revoked=false.
     */
    public function testCancelMfaTokenNotFound(): void
    {
        $_GET = ['token' => 'not-found'];

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/appareil/annuler?token=not-found')
             ->cancelMfa(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // confirmSecurityAction()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue security-confirm avec success=false.
     */
    public function testConfirmSecurityActionEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer')
             ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable → vue security-confirm avec expired=true.
     */
    public function testConfirmSecurityActionTokenNotFound(): void
    {
        $_GET = ['token' => 'non-existent-token'];

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer?token=non-existent-token')
             ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide → mot de passe appliqué, vue security-confirm avec success=true.
     */
    public function testConfirmSecurityActionSuccess(): void
    {
        $userId = $this->insertCustomer('confirm.sec@test.local');
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $pendingHash = password_hash('NewStrong#99!', PASSWORD_BCRYPT);

        self::$db->insert(
            "UPDATE accounts SET
               pending_password_hash            = ?,
               pending_password_expires_at      = ?,
               security_confirm_token_hash      = ?,
               security_confirm_token_expires_at = ?,
               pending_password_session_token   = NULL
             WHERE id = ?",
            [$pendingHash, $expiresAt, $tokenHash, $expiresAt, $userId]
        );

        $_GET = ['token' => $rawToken];

        ob_start();
        $this->makeController('GET', '/fr/securite/confirmer?token=' . $rawToken)
             ->confirmSecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // denySecurityAction()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue security-deny avec success=false.
     */
    public function testDenySecurityActionEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi')
             ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable → vue security-deny avec expired=true.
     */
    public function testDenySecurityActionTokenNotFound(): void
    {
        $_GET = ['token' => 'fake-deny-token'];

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi?token=fake-deny-token')
             ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide → sessions révoquées, vue security-deny avec success=true.
     */
    public function testDenySecurityActionSuccess(): void
    {
        $userId = $this->insertCustomer('deny.sec@test.local');
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $pendingHash = password_hash('NewStrong#99!', PASSWORD_BCRYPT);

        self::$db->insert(
            "UPDATE accounts SET
               pending_password_hash            = ?,
               pending_password_expires_at      = ?,
               security_confirm_token_hash      = ?,
               security_confirm_token_expires_at = ?
             WHERE id = ?",
            [$pendingHash, $expiresAt, $tokenHash, $expiresAt, $userId]
        );

        $_GET = ['token' => $rawToken];

        ob_start();
        $this->makeController('GET', '/fr/securite/ce-netait-pas-moi?token=' . $rawToken)
             ->denySecurityAction(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    // ----------------------------------------------------------------
    // reactivateAccount()
    // ----------------------------------------------------------------

    /**
     * Token vide → vue reactivate avec success=false.
     */
    public function testReactivateAccountEmptyToken(): void
    {
        $_GET = ['token' => ''];

        ob_start();
        $this->makeController('GET', '/fr/compte/reactiver')
             ->reactivateAccount(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token introuvable → vue reactivate avec success=false.
     */
    public function testReactivateAccountTokenNotFound(): void
    {
        $_GET = ['token' => 'not-found-reactivation'];

        ob_start();
        $this->makeController('GET', '/fr/compte/reactiver?token=not-found-reactivation')
             ->reactivateAccount(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }

    /**
     * Token valide (compte soft-deleted) → réactivé, vue reactivate avec success=true.
     */
    public function testReactivateAccountSuccess(): void
    {
        $userId = $this->insertCustomer('reactivate.ok@test.local');
        $rawToken = bin2hex(random_bytes(32));

        self::$db->insert(
            "UPDATE accounts SET
               deleted_at            = NOW(),
               scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
               reactivation_token    = ?
             WHERE id = ?",
            [$rawToken, $userId]
        );

        $_GET = ['token' => $rawToken];

        ob_start();
        $this->makeController('GET', '/fr/compte/reactiver?token=' . $rawToken)
             ->reactivateAccount(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-card', $output);
    }
}
