<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\RatingController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour RatingController.
 * Couvre les guards de validation (auth, CSRF, note, token) sans BDD réelle.
 */
class RatingControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        // RateLimiterService appelle fetchOne → null = aucun record = requête autorisée
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('execute')->willReturn(0);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/rating/test';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_ENV['JWT_SECRET']        = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): RatingController
    {
        return new RatingController(new Request());
    }

    // ================================================================
    // rateViaTokenApi — validation (php://input vide → token='' → 422)
    // ================================================================

    /**
     * rateViaTokenApi() retourne 422 quand token et note sont absents (corps vide).
     */
    public function testRateViaTokenApiReturns422WhenBodyEmpty(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->rateViaTokenApi([]);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaAccount — guard : non authentifié → 401
    // ================================================================

    /**
     * rateViaAccount() retourne 404 quand aucun cookie auth_token
     * (AuthMiddleware masque les routes protégées — design sécurité).
     */
    public function testRateViaAccountReturns404WhenNotAuthenticated(): void
    {
        $_COOKIE = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * rateViaAccount() retourne 404 quand le JWT est invalide
     * (AuthMiddleware masque les routes protégées — design sécurité).
     */
    public function testRateViaAccountReturns404WhenTokenInvalid(): void
    {
        $_COOKIE = ['auth_token' => 'invalid.jwt.token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaAccount — guard : CSRF invalide → 403
    // ================================================================

    /**
     * rateViaAccount() retourne 403 quand le CSRF ne correspond pas.
     */
    public function testRateViaAccountReturns403WhenCsrfInvalid(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'WRONG-CSRF', 'note' => '4'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // rateViaAccount — guard : note invalide → 422
    // ================================================================

    /**
     * rateViaAccount() retourne 422 quand la note est hors bornes (0).
     */
    public function testRateViaAccountReturns422WhenNoteIsZero(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '0'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * rateViaAccount() retourne 422 quand la note dépasse 5.
     */
    public function testRateViaAccountReturns422WhenNoteExceedsFive(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(['id' => 1]);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = ['csrf_token' => 'valid-csrf', 'note' => '6'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->rateViaAccount(['lang' => 'fr', 'wineId' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ================================================================
    // Helper
    // ================================================================

    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }
}
