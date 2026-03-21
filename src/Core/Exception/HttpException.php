<?php

declare(strict_types=1);

namespace Core\Exception;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $location = null,
        string $message = ''
    ) {
        parent::__construct($message ?: "HTTP $status", $status);
    }
}
