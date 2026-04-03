<?php

declare(strict_types=1);

namespace Core\Exception;

/**
 * Levée lors d'une erreur dans le flux OAuth2 Google
 * (échange de code, récupération userinfo, appel HTTP).
 */
class GoogleOAuthException extends \RuntimeException
{
}
