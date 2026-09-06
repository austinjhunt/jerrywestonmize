<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaVendor\Monolog\Formatter\LineFormatter;
use AmeliaVendor\Monolog\Handler\NullHandler;
use AmeliaVendor\Monolog\Handler\RotatingFileHandler;
use AmeliaVendor\Monolog\Handler\StreamHandler;
use AmeliaVendor\Monolog\Handler\WhatFailureGroupHandler;
use AmeliaVendor\Monolog\Logger;
use AmeliaVendor\Psr\Log\LoggerInterface as Psr3LoggerInterface;

/**
 * Class MonologLoggerFactory
 *
 * Builds (and caches, per channel) the Monolog stack: daily-rotating JSON file handler,
 * request-id + sanitizer processors, optional WP_DEBUG_LOG mirror for ERROR+.
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
class MonologLoggerFactory
{
    private const DEFAULT_MIN_LEVEL = 'debug';

    /** @var array<string, Psr3LoggerInterface> */
    private array $loggers = [];

    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function getLogger(string $channel): Psr3LoggerInterface
    {
        if (isset($this->loggers[$channel])) {
            return $this->loggers[$channel];
        }

        $logger = new Logger($channel);

        if (!$this->isEnabled()) {
            $logger->pushHandler(new NullHandler());

            return $this->loggers[$channel] = $logger;
        }

        $logger->pushProcessor(new RequestIdProcessor());
        $logger->pushProcessor(new LogSanitizer());

        $directory   = LogDirectoryManager::ensureDirectory();
        $fileHandler = new RotatingFileHandler(
            $directory . '/' . LogDirectoryManager::getLogBasename($channel),
            0,
            $this->resolveMinLevel(),
            true,
            null,
            true
        );
        $fileHandler->setFormatter(new JsonLineFormatter());
        $logger->pushHandler(new WhatFailureGroupHandler([$fileHandler]));

        $debugLogHandler = $this->createDebugLogHandler();

        if ($debugLogHandler !== null) {
            $logger->pushHandler($debugLogHandler);
        }

        return $this->loggers[$channel] = $logger;
    }

    /**
     * Clear per-channel cache (tests / long-running workers).
     */
    public function reset(): void
    {
        $this->loggers = [];
    }

    private function isEnabled(): bool
    {
        return (bool) $this->settingsService->getSetting('logging', 'enabled', true);
    }

    private function resolveMinLevel(): int
    {
        $configured = $this->settingsService->getSetting('logging', 'minLevel', self::DEFAULT_MIN_LEVEL);
        $levelName  = is_string($configured) && $configured !== ''
            ? strtolower($configured)
            : self::DEFAULT_MIN_LEVEL;

        try {
            return Logger::toMonologLevel($levelName);
        } catch (\Throwable $e) {
            return Logger::ERROR;
        }
    }

    private function createDebugLogHandler(): ?StreamHandler
    {
        $debugLogEnabled = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

        if (!$debugLogEnabled) {
            return null;
        }

        $path = is_string(WP_DEBUG_LOG)
            ? WP_DEBUG_LOG
            : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/debug.log' : null);

        if ($path === null) {
            return null;
        }

        $handler = new StreamHandler($path, Logger::ERROR);
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        return $handler;
    }
}
