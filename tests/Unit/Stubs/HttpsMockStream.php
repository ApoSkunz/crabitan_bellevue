<?php

declare(strict_types=1);

namespace Tests\Unit\Stubs;

/**
 * Stream wrapper mock for https:// — returns a controlled string response.
 * Register with stream_wrapper_unregister('https') then
 * stream_wrapper_register('https', HttpsMockStream::class).
 *
 * Used in WeatherControllerTest to simulate API responses without network.
 */
class HttpsMockStream
{
    public static string $response = '';

    /** @var resource|null Required by PHP stream wrapper protocol — prevents dynamic property deprecation (PHP 8.2+) */
    public mixed $context = null;

    private int $position = 0;

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->position = 0;
        return true;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_read(int $count): string
    {
        $chunk = substr(self::$response, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$response);
    }

    /**
     * @return array<string, int>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_stat(): array
    {
        return [];
    }

    /**
     * @return array<string, int>|false
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function url_stat(string $path, int $flags): array|false
    {
        return false;
    }
}
