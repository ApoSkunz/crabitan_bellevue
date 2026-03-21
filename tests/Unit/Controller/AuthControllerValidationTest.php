<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests the validateRegister() logic in AuthController (via the register() action).
 * The Database singleton is mocked so no real DB connection is needed.
 */
class AuthControllerValidationTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        // createStub() = pas d'expectations requises (aucune méthode DB n'est appelée
        // lors d'un échec de validation, le redirect se fait avant tout accès BDD)
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    private function makeController(array $post): AuthController
    {
        $_POST = array_merge([
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'jean@example.com',
            'password'         => 'Password1!',
            'password_confirm' => 'Password1!',
            'gender'           => 'M',
            'company_name'     => '',
            'newsletter'       => '0',
            'csrf_token'       => 'test-csrf-token',
        ], $post);

        return new AuthController(new Request());
    }

    /**
     * Helper: call register() and return the caught HttpException.
     * register() only ever redirects (no output), so no output buffering needed.
     */
    private function callRegister(AuthController $controller): HttpException
    {
        $caught = null;
        try {
            $controller->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'Expected HttpException to be thrown by register()');
        return $caught;
    }

    public function testRegisterFailsWithShortLastname(): void
    {
        $e = $this->callRegister($this->makeController(['lastname' => 'A']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('lastname', $_SESSION['flash']['errors']);
    }

    public function testRegisterFailsWithShortFirstname(): void
    {
        $e = $this->callRegister($this->makeController(['firstname' => 'A']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('firstname', $_SESSION['flash']['errors']);
    }

    public function testRegisterFailsWithInvalidEmail(): void
    {
        $e = $this->callRegister($this->makeController(['email' => 'not-an-email']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('email', $_SESSION['flash']['errors']);
    }

    public function testRegisterFailsWithShortPassword(): void
    {
        $e = $this->callRegister($this->makeController(['password' => 'abc', 'password_confirm' => 'abc']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('password', $_SESSION['flash']['errors']);
    }

    public function testRegisterFailsWhenPasswordsDontMatch(): void
    {
        $e = $this->callRegister($this->makeController(['password_confirm' => 'DifferentPass1!']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('password_confirm', $_SESSION['flash']['errors']);
    }

    public function testRegisterFailsWithInvalidGender(): void
    {
        $e = $this->callRegister($this->makeController(['gender' => 'unknown']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('gender', $_SESSION['flash']['errors']);
    }

    public function testRegisterFailsForSocietyWithoutCompanyName(): void
    {
        $e = $this->callRegister($this->makeController(['gender' => 'society', 'company_name' => 'A']));
        $this->assertSame(302, $e->status);
        $this->assertArrayHasKey('company_name', $_SESSION['flash']['errors']);
    }
}
