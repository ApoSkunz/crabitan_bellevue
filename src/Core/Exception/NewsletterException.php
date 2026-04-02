<?php

declare(strict_types=1);

namespace Core\Exception;

/**
 * Exception dédiée au service newsletter (double opt-in RGPD Art. 7).
 *
 * Utiliser les constructeurs nommés pour créer des instances typées
 * et éviter les RuntimeException génériques (SonarCloud php:S112).
 */
class NewsletterException extends \RuntimeException
{
    /**
     * @return self
     */
    public static function alreadyConfirmed(): self
    {
        return new self('already_confirmed');
    }

    /**
     * @return self
     */
    public static function rateLimitExceeded(): self
    {
        return new self('rate_limit');
    }

    /**
     * @return self
     */
    public static function tokenExpired(): self
    {
        return new self('expired');
    }

    /**
     * @return self
     */
    public static function invalidToken(): self
    {
        return new self('invalid');
    }

    /**
     * @return self
     */
    public static function notFound(): self
    {
        return new self('not_found');
    }
}
