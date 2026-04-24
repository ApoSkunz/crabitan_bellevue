<?php

declare(strict_types=1);

namespace Exception;

/**
 * Levée quand le serveur SMTP retourne un code de rate-limit (421/429).
 */
class MailRateLimitException extends \RuntimeException
{
}
