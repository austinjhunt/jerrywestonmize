<?php

namespace AmeliaBooking\Domain\Services\Logger;

/**
 * Interface LoggerInterface
 *
 * Minimal domain logging contract with named-channel routing. Intentionally not PSR-3 /
 * vendor-typed so Domain code stays free of third-party logging libraries.
 *
 * @package AmeliaBooking\Domain\Services\Logger
 */
interface LoggerInterface
{
    public const CHANNEL_APP          = 'app';
    public const CHANNEL_COMMAND      = 'command';
    public const CHANNEL_PAYMENT      = 'payment';
    public const CHANNEL_SYNC         = 'sync';
    public const CHANNEL_NOTIFICATION = 'notification';
    public const CHANNEL_ZOOM         = 'zoom';
    public const CHANNEL_USER         = 'user';
    public const CHANNEL_SETTINGS     = 'settings';
    public const CHANNEL_HTTP         = 'http';
    public const CHANNEL_BOOKING      = 'booking';

    /** Log an error message */
    public function error(string $message, array $context = []): void;

    /** Log a warning message */
    public function warning(string $message, array $context = []): void;

    /** Log an informational message */
    public function info(string $message, array $context = []): void;

    /** Log a debug message */
    public function debug(string $message, array $context = []): void;

    /**
     * Get a logger scoped to the given named channel (e.g. "payment", "sync", "command").
     */
    public function channel(string $name): LoggerInterface;
}
