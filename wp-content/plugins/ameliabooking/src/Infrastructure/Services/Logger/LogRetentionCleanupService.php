<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

use AmeliaBooking\Domain\Services\Settings\SettingsService;

/**
 * Class LogRetentionCleanupService
 *
 * WP-Cron callback: deletes log files older than the configured retention window.
 * Age is determined from the UTC date embedded in the filename
 * (amelia-{hash}-{channel}-YYYY-MM-DD.log).
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
class LogRetentionCleanupService
{
    private const DEFAULT_RETENTION_DAYS = 30;

    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function cleanup(): void
    {
        $retentionDays = (int) $this->settingsService->getSetting(
            'logging',
            'retentionDays',
            self::DEFAULT_RETENTION_DAYS
        );

        if ($retentionDays <= 0) {
            return;
        }

        $directory = LogDirectoryManager::ensureDirectory();
        $prefix    = LogDirectoryManager::getFilenamePrefix();
        $cutoff    = strtotime(gmdate('Y-m-d', time() - ($retentionDays * DAY_IN_SECONDS)) . ' UTC');

        if ($cutoff === false) {
            return;
        }

        // Monolog RotatingFileHandler: amelia-{hash}-{channel}-YYYY-MM-DD.log
        // Also matches legacy size-split files: amelia-{hash}-{channel}-YYYY-MM-DD-N.log
        $pattern = '/^' . preg_quote($prefix, '/') . '-[a-z0-9_-]+-(\d{4}-\d{2}-\d{2})(?:-\d+)?\.log$/i';

        foreach (glob($directory . '/' . $prefix . '-*.log') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            if (!preg_match($pattern, basename($file), $matches)) {
                continue;
            }

            $fileDate = strtotime($matches[1] . ' UTC');

            if ($fileDate !== false && $fileDate < $cutoff) {
                @unlink($file);
            }
        }
    }
}
