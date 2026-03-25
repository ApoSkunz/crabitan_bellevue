<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\NewsletterAdminController;
use Core\Exception\HttpException;

class NewsletterAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): NewsletterAdminController
    {
        return new NewsletterAdminController($this->makeRequest($method, '/admin/newsletter'));
    }

    private function insertSubscriber(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, newsletter)
             VALUES ('sub@test.local', 'x', 'customer', 'fr', NOW(), 1)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Sub', 'Test', 'M')",
            [$id]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersSubscribersList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Newsletter', $output);
    }

    public function testIndexWithSubscriberRendersView(): void
    {
        $this->insertSubscriber();

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // send — CSRF invalide
    // ----------------------------------------------------------------

    public function testSendRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — CSRF valide, sujet vide → redirect avec erreur
    // ----------------------------------------------------------------

    public function testSendRedirectsOnEmptySubject(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = '';
        $_POST['body']       = 'Contenu test';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }

    // ----------------------------------------------------------------
    // send — CSRF valide, body vide → redirect avec erreur
    // ----------------------------------------------------------------

    public function testSendRedirectsOnEmptyBody(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['subject']    = 'Sujet test';
        $_POST['body']       = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->send([]);
    }
}
