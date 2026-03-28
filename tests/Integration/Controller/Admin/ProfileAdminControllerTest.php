<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\ProfileAdminController;
use Core\Exception\HttpException;

/**
 * Tests d'intégration pour ProfileAdminController.
 */
class ProfileAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): ProfileAdminController
    {
        return new ProfileAdminController(
            $this->makeRequest($method, '/admin/securite')
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    /**
     * GET /admin/securite affiche la vue de sécurité admin.
     */
    public function testIndexRendersSecurityPage(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }

    // ----------------------------------------------------------------
    // changePassword — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * Un POST avec CSRF invalide redirige.
     */
    public function testChangePasswordInvalidCsrfRedirects(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->changePassword([]);
    }

    /**
     * Un mot de passe actuel incorrect flash une erreur et redirige.
     */
    public function testChangePasswordWrongCurrentRedirects(): void
    {
        $_POST = [
            'csrf_token'          => self::CSRF_TOKEN,
            'current_password'    => 'WrongPass!',
            'new_password'        => 'NewPassword123!',
            'new_password_confirm' => 'NewPassword123!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->changePassword([]);
    }

    /**
     * Un nouveau mot de passe trop court flash une erreur et redirige.
     */
    public function testChangePasswordTooShortRedirects(): void
    {
        $_POST = [
            'csrf_token'           => self::CSRF_TOKEN,
            'current_password'     => 'Admin123!',
            'new_password'         => 'short',
            'new_password_confirm' => 'short',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->changePassword([]);
    }

    /**
     * Des mots de passe qui ne correspondent pas flash une erreur et redirigent.
     */
    public function testChangePasswordMismatchRedirects(): void
    {
        $_POST = [
            'csrf_token'           => self::CSRF_TOKEN,
            'current_password'     => 'Admin123!',
            'new_password'         => 'NewPassword123!',
            'new_password_confirm' => 'DifferentPassword123!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->changePassword([]);
    }

    /**
     * Un changement de mot de passe valide redirige avec succès.
     */
    public function testChangePasswordSuccessRedirects(): void
    {
        $_POST = [
            'csrf_token'           => self::CSRF_TOKEN,
            'current_password'     => 'Admin123!',
            'new_password'         => 'NewSecure123!',
            'new_password_confirm' => 'NewSecure123!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->changePassword([]);
    }

    // ----------------------------------------------------------------
    // revokeSession
    // ----------------------------------------------------------------

    /**
     * Un POST CSRF valide avec un id de session inexistant redirige.
     */
    public function testRevokeSessionRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->revokeSession(['id' => '999999']);
    }

    /**
     * Un POST CSRF invalide redirige sans révocation.
     */
    public function testRevokeSessionInvalidCsrfRedirects(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->revokeSession(['id' => '999999']);
    }

    // ----------------------------------------------------------------
    // revokeAllSessions
    // ----------------------------------------------------------------

    /**
     * Un POST CSRF invalide ne révoque rien et redirige.
     */
    public function testRevokeAllSessionsInvalidCsrfRedirects(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->revokeAllSessions([]);
    }

    /**
     * Un POST CSRF valide révoque toutes les sessions et redirige vers /admin.
     */
    public function testRevokeAllSessionsSuccessRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->revokeAllSessions([]);
    }

    // ----------------------------------------------------------------
    // untrustDevice
    // ----------------------------------------------------------------

    /**
     * Un POST CSRF valide avec un device_token connu retire la confiance et redirige.
     */
    public function testUntrustDeviceRedirects(): void
    {
        $_POST['csrf_token']   = self::CSRF_TOKEN;
        $_POST['device_token'] = 'some-device-token';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->untrustDevice([]);
    }

    // ----------------------------------------------------------------
    // untrustAllDevices
    // ----------------------------------------------------------------

    /**
     * Un POST CSRF invalide redirige sans suppression.
     */
    public function testUntrustAllDevicesInvalidCsrfRedirects(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->untrustAllDevices([]);
    }

    /**
     * Un POST CSRF valide supprime tous les appareils et redirige.
     */
    public function testUntrustAllDevicesSuccessRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->untrustAllDevices([]);
    }

    // ----------------------------------------------------------------
    // resetSecurity
    // ----------------------------------------------------------------

    /**
     * Un POST CSRF invalide redirige.
     */
    public function testResetSecurityInvalidCsrfRedirects(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->resetSecurity([]);
    }

    /**
     * Un mot de passe incorrect flash une erreur et redirige.
     */
    public function testResetSecurityWrongPasswordRedirects(): void
    {
        $_POST = [
            'csrf_token' => self::CSRF_TOKEN,
            'password'   => 'WrongPassword!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->resetSecurity([]);
    }

    /**
     * Un mot de passe correct révoque tout et redirige vers /admin.
     */
    public function testResetSecuritySuccessRedirects(): void
    {
        $_POST = [
            'csrf_token' => self::CSRF_TOKEN,
            'password'   => 'Admin123!',
        ];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->resetSecurity([]);
    }
}
