<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Exception\GoogleOAuthException;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;

/**
 * Tests unitaires GoogleOAuthService — httpPost() et httpGet() (implémentation réelle).
 *
 * On invoque ces méthodes protégées via ReflectionMethod avec une URL injoignable
 * pour déclencher le chemin d'erreur (file_get_contents retourne false → exception).
 *
 * Les chemins de succès (lines 115 et 140) sont couverts via une sous-classe
 * qui substitue stream_context_create + file_get_contents via data:// URI.
 */
class GoogleOAuthServiceHttpTest extends TestCase
{
    private GoogleOAuthService $service;
    private \ReflectionMethod $httpPost;
    private \ReflectionMethod $httpGet;

    protected function setUp(): void
    {
        $this->service  = new GoogleOAuthService('test-id', 'test-secret');
        $this->httpPost = new \ReflectionMethod($this->service, 'httpPost');
        $this->httpGet  = new \ReflectionMethod($this->service, 'httpGet');
    }

    // ----------------------------------------------------------------
    // httpPost() — implémentation réelle — chemin d'erreur
    // ----------------------------------------------------------------

    public function testHttpPostThrowsGoogleOAuthExceptionOnConnectionFailure(): void
    {
        // Port 1 — aucun service système ne devrait écouter sur ce port
        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Google OAuth HTTP POST failed');

        $this->httpPost->invoke($this->service, 'http://localhost:1/token', 'code=test');
    }

    // ----------------------------------------------------------------
    // httpGet() — implémentation réelle — chemin d'erreur
    // ----------------------------------------------------------------

    public function testHttpGetThrowsGoogleOAuthExceptionOnConnectionFailure(): void
    {
        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Google OAuth HTTP GET failed');

        $this->httpGet->invoke($this->service, 'http://localhost:1/userinfo', 'fake-token');
    }

    // ----------------------------------------------------------------
    // httpPost() — chemin de succès (line 115 : return $result)
    // ----------------------------------------------------------------

    /**
     * Couvre la line 115 : return $result dans httpPost() (file_get_contents réussit).
     *
     * On passe une URL data:// à la méthode réelle httpPost() via réflexion.
     * file_get_contents accepte les URIs data:// indépendamment du stream context http,
     * donc $result !== false et la ligne 115 est atteinte.
     *
     * @return void
     */
    public function testHttpPostSuccessReturnsRawResponse(): void
    {
        $fakePayload = '{"access_token":"tok_success"}';
        // data:// URI — file_get_contents réussit sans réseau, $result !== false → line 115
        $dataUri = 'data://text/plain,' . rawurlencode($fakePayload);

        $result = $this->httpPost->invoke($this->service, $dataUri, 'code=x');

        $this->assertSame($fakePayload, $result);
    }

    // ----------------------------------------------------------------
    // httpGet() — chemin de succès (line 140 : return $result)
    // ----------------------------------------------------------------

    /**
     * Couvre la line 140 : return $result dans httpGet() (file_get_contents réussit).
     *
     * Même technique data:// URI que httpPost().
     *
     * @return void
     */
    public function testHttpGetSuccessReturnsRawResponse(): void
    {
        $fakePayload = '{"sub":"123","email":"user@example.com"}';
        $dataUri     = 'data://text/plain,' . rawurlencode($fakePayload);

        $result = $this->httpGet->invoke($this->service, $dataUri, 'access_token_xyz');

        $this->assertSame($fakePayload, $result);
    }
}
