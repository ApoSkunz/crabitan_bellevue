<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie le dédoublonnage newsletter lors de l'inscription.
 *
 * Critère 4 : si newsletter=1, deleteNewsletterSubscriptionByEmail() doit être appelé.
 * Critère 5 : si newsletter=0, l'entrée newsletter_subscriptions est conservée (pas d'appel DELETE).
 */
class AuthControllerRegisterNewsletterDeduplicationTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        unset($_SERVER['REMOTE_ADDR']);
    }

    private function basePost(string $newsletter): array
    {
        return [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'jean@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'company_name'     => '',
            'newsletter'       => $newsletter,
            'csrf_token'       => 'valid-csrf',
        ];
    }

    // ================================================================
    // Critère 4 : newsletter=1 → DELETE sur newsletter_subscriptions
    // ================================================================

    /**
     * Quand newsletter=1, execute() doit être appelé avec la requête DELETE.
     *
     * @return void
     */
    public function testNewsletterOneTriggersDeleteSubscription(): void
    {
        $_POST = $this->basePost('1');

        $stub = $this->createMock(Database::class);
        // findByEmail → pas de doublon
        $stub->method('fetchOne')->willReturn(false);

        $deleteCalled = false;
        $stub->method('execute')->willReturnCallback(
            function (string $sql, array $params) use (&$deleteCalled): mixed {
                if (str_contains($sql, 'DELETE FROM newsletter_subscriptions')) {
                    $deleteCalled = true;
                }
                return null;
            }
        );

        $this->instanceProp->setValue(null, $stub);

        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertTrue($deleteCalled, 'DELETE newsletter_subscriptions doit être appelé quand newsletter=1');
    }

    // ================================================================
    // Critère 5 : newsletter=0 → pas de DELETE
    // ================================================================

    /**
     * Quand newsletter=0, aucun DELETE sur newsletter_subscriptions ne doit être exécuté.
     *
     * @return void
     */
    public function testNewsletterZeroDoesNotDeleteSubscription(): void
    {
        $_POST = $this->basePost('0');

        $stub = $this->createMock(Database::class);
        // findByEmail → pas de doublon
        $stub->method('fetchOne')->willReturn(false);

        $deleteCalled = false;
        $stub->method('execute')->willReturnCallback(
            function (string $sql, array $params) use (&$deleteCalled): mixed {
                if (str_contains($sql, 'DELETE FROM newsletter_subscriptions')) {
                    $deleteCalled = true;
                }
                return null;
            }
        );

        $this->instanceProp->setValue(null, $stub);

        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertFalse($deleteCalled, 'DELETE newsletter_subscriptions NE doit PAS être appelé quand newsletter=0');
    }
}
