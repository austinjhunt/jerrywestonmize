<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

/**
 * Class RequestIdProcessor
 *
 * Tags every record with a per-request correlation ID (and multisite blog ID when available).
 * Invokable Monolog processor (callable); does not implement ProcessorInterface to avoid
 * Strauss-broken phpstan Record imports on CI.
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
class RequestIdProcessor
{
    private static ?string $requestId = null;

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record): array
    {
        $record['extra']['request_id'] = self::get();

        if (function_exists('get_current_blog_id')) {
            $record['extra']['blog_id'] = get_current_blog_id();
        }

        return $record;
    }

    public static function get(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }

        $header = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;

        if (is_string($header) && preg_match('/^[A-Za-z0-9\-]{1,128}$/', $header)) {
            return self::$requestId = $header;
        }

        return self::$requestId = bin2hex(random_bytes(16));
    }

    /**
     * Reset cached ID (for tests / long-running workers between jobs).
     */
    public static function reset(): void
    {
        self::$requestId = null;
    }
}
