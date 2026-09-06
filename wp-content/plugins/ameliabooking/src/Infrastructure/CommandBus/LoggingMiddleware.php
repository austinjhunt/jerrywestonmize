<?php

namespace AmeliaBooking\Infrastructure\CommandBus;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use League\Tactician\Middleware;

/**
 * Class LoggingMiddleware
 *
 * Logs command failures only (exception + timing). Successful results are not logged.
 * Channel is inferred from the command namespace so payment/booking/etc. land in the
 * matching log file instead of a generic "command" file.
 *
 * @package AmeliaBooking\Infrastructure\CommandBus
 */
class LoggingMiddleware implements Middleware
{
    private const COMMANDS_NAMESPACE = 'AmeliaBooking\\Application\\Commands\\';

    /** @var array<string, string> Top-level Commands\* segment → logger channel */
    private const CHANNEL_BY_NAMESPACE = [
        'payment'        => LoggerInterface::CHANNEL_PAYMENT,
        'paymentgateway' => LoggerInterface::CHANNEL_PAYMENT,
        'square'         => LoggerInterface::CHANNEL_PAYMENT,
        'stripe'         => LoggerInterface::CHANNEL_PAYMENT,
        'booking'        => LoggerInterface::CHANNEL_BOOKING,
        'settings'       => LoggerInterface::CHANNEL_SETTINGS,
        'user'           => LoggerInterface::CHANNEL_USER,
        'notification'   => LoggerInterface::CHANNEL_NOTIFICATION,
        'zoom'           => LoggerInterface::CHANNEL_ZOOM,
        'google'         => LoggerInterface::CHANNEL_SYNC,
        'outlook'        => LoggerInterface::CHANNEL_SYNC,
        'apple'          => LoggerInterface::CHANNEL_SYNC,
        'calendar'       => LoggerInterface::CHANNEL_SYNC,
    ];

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute($command, callable $next)
    {
        $commandClass = get_class($command);
        $start        = microtime(true);

        try {
            return $next($command);
        } catch (\Throwable $e) {
            $elapsedMs = round((microtime(true) - $start) * 1000, 2);

            $this->logger
                ->channel($this->resolveChannel($commandClass))
                ->error("Failed: {$commandClass}", [
                    'elapsed_ms' => $elapsedMs,
                    'exception'  => $e,
                ]);

            throw $e;
        }
    }

    private function resolveChannel(string $commandClass): string
    {
        if (strpos($commandClass, self::COMMANDS_NAMESPACE) !== 0) {
            return LoggerInterface::CHANNEL_COMMAND;
        }

        $relative = substr($commandClass, strlen(self::COMMANDS_NAMESPACE));
        $segment  = strtolower(explode('\\', $relative)[0] ?? '');

        return self::CHANNEL_BY_NAMESPACE[$segment] ?? LoggerInterface::CHANNEL_COMMAND;
    }
}
