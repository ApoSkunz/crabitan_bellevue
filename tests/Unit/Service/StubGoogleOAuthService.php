<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Exception\GoogleOAuthException;
use Service\GoogleOAuthService;

/**
 * Sous-classe testable qui remplace les appels HTTP par des réponses contrôlées.
 */
class StubGoogleOAuthService extends GoogleOAuthService
{
    public string $postResponse = '';
    public bool $postFail = false;
    public string $getResponse = '';
    public bool $getFail = false;

    /** @param string $url @param string $body @return string */
    protected function httpPost(string $url, string $body): string
    {
        if ($this->postFail) {
            throw new GoogleOAuthException('Google OAuth HTTP POST failed');
        }
        return $this->postResponse;
    }

    /** @param string $url @param string $accessToken @return string */
    protected function httpGet(string $url, string $accessToken): string
    {
        if ($this->getFail) {
            throw new GoogleOAuthException('Google OAuth HTTP GET failed');
        }
        return $this->getResponse;
    }
}
