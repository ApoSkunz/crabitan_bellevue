<?php

declare(strict_types=1);

namespace Tests\Unit\Stubs;

/**
 * Stream wrapper mock for https:// — always fails to open (simulates network failure).
 * Register with stream_wrapper_unregister('https') then
 * stream_wrapper_register('https', HttpsFailStream::class).
 *
 * Used in WeatherControllerTest to trigger the fetch_failed 502 path.
 */
class HttpsFailStream
{
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        return false;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_read(int $count): string
    {
        return '';
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_eof(): bool
    {
        return true;
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
