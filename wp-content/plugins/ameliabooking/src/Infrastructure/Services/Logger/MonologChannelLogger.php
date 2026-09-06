<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;

/**
 * Class MonologChannelLogger
 *
 * Domain LoggerInterface backed by Monolog. Level methods route to the instance's channel
 * ("app" unless obtained via channel($name)).
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
class MonologChannelLogger implements LoggerInterface
{
    private MonologLoggerFactory $factory;

    private string $channelName;

    public function __construct(MonologLoggerFactory $factory, string $channelName = self::CHANNEL_APP)
    {
        $this->factory     = $factory;
        $this->channelName = $channelName;
    }

    public function channel(string $name): LoggerInterface
    {
        return new self($this->factory, $name);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->factory->getLogger($this->channelName)->{$level}($message, $context);
        } catch (\Throwable $e) {
            // Telemetry must never break application flow after side effects.
        }
    }
}
