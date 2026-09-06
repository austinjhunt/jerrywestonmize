<?php

namespace AmeliaBooking\Infrastructure\Services\Logger;

/**
 * Class LogDirectoryManager
 *
 * Ensures a writable log directory under wp-content/uploads/amelia-logs, with
 * access-protection files as defense-in-depth. Log filenames include a site-specific
 * hash so they are harder to guess via direct URL.
 *
 * @package AmeliaBooking\Infrastructure\Services\Logger
 */
final class LogDirectoryManager
{
    public const DIRECTORY_NAME = 'amelia-logs';

    public static function ensureDirectory(): string
    {
        $directory = self::resolveDirectory();

        if (!is_dir($directory)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($directory);
            } else {
                @mkdir($directory, 0755, true);
            }
        }

        self::ensureAccessProtection($directory);

        return $directory;
    }

    /**
     * Filename stem used by RotatingFileHandler, e.g. amelia-a1b2c3d4e5f6-payment.log
     * which rotates to amelia-a1b2c3d4e5f6-payment-YYYY-MM-DD.log.
     */
    public static function getLogBasename(string $channel): string
    {
        return self::getFilenamePrefix() . '-' . self::sanitizeChannel($channel) . '.log';
    }

    /**
     * Stable per-site prefix: amelia-{12-char-hash}.
     */
    public static function getFilenamePrefix(): string
    {
        $secret = defined('AUTH_KEY') && is_string(AUTH_KEY) && AUTH_KEY !== ''
            ? AUTH_KEY
            : (defined('DB_NAME') && is_string(DB_NAME) ? DB_NAME : 'amelia');

        return 'amelia-' . substr(hash('sha256', 'amelia-logs|' . $secret), 0, 12);
    }

    /**
     * Keep channel segment filesystem-safe (lowercase [a-z0-9_-]).
     */
    public static function sanitizeChannel(string $channel): string
    {
        $sanitized = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $channel) ?? '');

        return $sanitized !== '' ? $sanitized : 'app';
    }

    private static function resolveDirectory(): string
    {
        if (defined('AMELIA_UPLOADS_PATH') && is_string(AMELIA_UPLOADS_PATH) && AMELIA_UPLOADS_PATH !== '') {
            return rtrim(str_replace('\\', '/', AMELIA_UPLOADS_PATH), '/') . '/' . self::DIRECTORY_NAME;
        }

        if (defined('WP_CONTENT_DIR') && is_string(WP_CONTENT_DIR) && WP_CONTENT_DIR !== '') {
            return rtrim(str_replace('\\', '/', WP_CONTENT_DIR), '/') . '/uploads/' . self::DIRECTORY_NAME;
        }

        return dirname(__DIR__, 4) . '/storage/logs';
    }

    private static function ensureAccessProtection(string $directory): void
    {
        if (!@is_dir($directory) || !@is_writable($directory)) {
            return;
        }

        $htaccess = $directory . '/.htaccess';

        if (!file_exists($htaccess)) {
            file_put_contents(
                $htaccess,
                "Deny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            );
        }

        $webConfig = $directory . '/web.config';

        if (!file_exists($webConfig)) {
            file_put_contents(
                $webConfig,
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<configuration>\n"
                . "  <system.webServer>\n"
                . "    <authorization>\n"
                . "      <deny users=\"*\" />\n"
                . "    </authorization>\n"
                . "  </system.webServer>\n"
                . "</configuration>\n"
            );
        }

        $index = $directory . '/index.php';

        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }
}
