<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Exception\NewsletterException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsletterException.
 *
 * Vérifie que chaque constructeur nommé produit le bon message
 * et que la classe hérite de RuntimeException.
 */
class NewsletterExceptionTest extends TestCase
{
    public function testIsInstanceOfRuntimeException(): void
    {
        $e = NewsletterException::invalidToken();
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testAlreadyConfirmedHasCorrectMessage(): void
    {
        $e = NewsletterException::alreadyConfirmed();
        $this->assertSame('already_confirmed', $e->getMessage());
    }

    public function testRateLimitExceededHasCorrectMessage(): void
    {
        $e = NewsletterException::rateLimitExceeded();
        $this->assertSame('rate_limit', $e->getMessage());
    }

    public function testTokenExpiredHasCorrectMessage(): void
    {
        $e = NewsletterException::tokenExpired();
        $this->assertSame('expired', $e->getMessage());
    }

    public function testInvalidTokenHasCorrectMessage(): void
    {
        $e = NewsletterException::invalidToken();
        $this->assertSame('invalid', $e->getMessage());
    }

    public function testNotFoundHasCorrectMessage(): void
    {
        $e = NewsletterException::notFound();
        $this->assertSame('not_found', $e->getMessage());
    }
}
