<?php

namespace AmeliaBooking\Infrastructure\Services\ErrorTracking;

use AmeliaBooking\Infrastructure\Services\Logger\LogSanitizer;
use Sentry\Event;
use Sentry\ExceptionDataBag;

/**
 * Class SentryReporter
 *
 * Optional error reporting to Sentry, gated only by a configured DSN. Customer zips
 * omit `sentry/sentry` (`require-dev`), so `Sentry\\init` is absent and this is a no-op
 * unless the SDK is installed and `AMELIA_SENTRY_DSN` is set via wp-config.php or env.
 *
 * Deliberately captures the real (unsanitized) exception object — not the array
 * LogSanitizer produces for file logging — so Sentry gets a native stack trace for
 * grouping. To compensate, before_send() below runs the same LogSanitizer string
 * redaction over exception/event messages, and send_default_pii/max_request_body_size
 * are locked down, in case a dev/staging box was ever restored from a production dump.
 *
 * @package AmeliaBooking\Infrastructure\Services\ErrorTracking
 */
final class SentryReporter
{
    private static bool $initialized = false;

    /** @var array<int, bool> spl_object_id => reported, so a caught-then-rethrown exception isn't sent twice */
    private static array $reportedIds = [];

    public static function report(string $message, array $context): void
    {
        try {
            if (!self::isEnabled()) {
                return;
            }

            self::init();

            $exception = $context['exception'] ?? null;

            if ($exception instanceof \Throwable) {
                $id = spl_object_id($exception);

                if (isset(self::$reportedIds[$id])) {
                    return;
                }

                self::$reportedIds[$id] = true;

                \Sentry\captureException($exception);

                return;
            }

            \Sentry\captureMessage($message);
        } catch (\Throwable $e) {
            // Telemetry must never break application flow.
        }
    }

    private static function isEnabled(): bool
    {
        return defined('AMELIA_SENTRY_DSN') && AMELIA_SENTRY_DSN !== ''
            && function_exists('Sentry\\init');
    }

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Strauss prefixes psr/log and psr/http-message to AmeliaVendor\*. The
        // unprefixed sentry/sentry SDK still typehints Psr\Log and Psr\Http.
        self::aliasPrefixedPsrClasses();

        \Sentry\init([
            'dsn'         => AMELIA_SENTRY_DSN,
            'environment' => self::environment(),
            'release'     => defined('AMELIA_VERSION') ? AMELIA_VERSION : null,

            // Belt-and-braces on top of before_send(): never attach request cookies/auth headers
            // or user IP/email, and never attach request bodies (booking forms carry customer PII).
            'send_default_pii'      => false,
            'max_request_body_size' => 'none',

            'before_send' => static function (Event $event): Event {
                return self::scrub($event);
            },

            // Only report what report() above explicitly sends — without this, the SDK installs
            // global set_error_handler/set_exception_handler/shutdown handlers and starts
            // capturing unrelated WP core/theme/other-plugin errors on the same page.
            //
            // RequestIntegration is also excluded: it always attaches the request URL and full
            // query string to every event regardless of send_default_pii (that flag only gates
            // IP/cookies/headers), and before_send() below only sanitizes message text — so a
            // query-string token or customer data would otherwise reach Sentry unredacted.
            'integrations' => static function (array $integrations): array {
                return array_filter($integrations, static function ($integration): bool {
                    return !$integration instanceof \Sentry\Integration\ExceptionListenerIntegration
                        && !$integration instanceof \Sentry\Integration\ErrorListenerIntegration
                        && !$integration instanceof \Sentry\Integration\FatalErrorListenerIntegration
                        && !$integration instanceof \Sentry\Integration\RequestIntegration;
                });
            },
        ]);
    }

    private static function aliasPrefixedPsrClasses(): void
    {
        $groups = [
            'Psr\\Log\\' => [
                'AbstractLogger',
                'InvalidArgumentException',
                'LoggerAwareInterface',
                'LoggerAwareTrait',
                'LoggerInterface',
                'LoggerTrait',
                'LogLevel',
                'NullLogger',
            ],
            'Psr\\Http\\Message\\' => [
                'MessageInterface',
                'RequestInterface',
                'ResponseInterface',
                'ServerRequestInterface',
                'StreamInterface',
                'UploadedFileInterface',
                'UriInterface',
            ],
        ];

        foreach ($groups as $namespace => $shortNames) {
            foreach ($shortNames as $shortName) {
                $original = $namespace . $shortName;
                $prefixed = 'AmeliaVendor\\' . $original;

                if (
                    class_exists($original, false)
                    || interface_exists($original, false)
                    || trait_exists($original, false)
                ) {
                    continue;
                }

                if (
                    class_exists($prefixed)
                    || interface_exists($prefixed)
                    || trait_exists($prefixed)
                ) {
                    class_alias($prefixed, $original);
                }
            }
        }
    }

    private static function environment(): string
    {
        if (defined('AMELIA_SENTRY_ENVIRONMENT') && AMELIA_SENTRY_ENVIRONMENT !== '') {
            return AMELIA_SENTRY_ENVIRONMENT;
        }

        return 'development';
    }

    /**
     * Redacts message text with the same LogSanitizer used for file logs, without touching
     * the native Stacktrace objects (that's the whole reason to capture before sanitization).
     */
    private static function scrub(Event $event): Event
    {
        $sanitizer = new LogSanitizer();

        $message = $event->getMessage();

        if ($message !== null) {
            $event->setMessage($sanitizer(['message' => $message])['message']);
        }

        $exceptions = $event->getExceptions();

        foreach ($exceptions as $exceptionDataBag) {
            if (!$exceptionDataBag instanceof ExceptionDataBag) {
                continue;
            }

            $exceptionDataBag->setValue($sanitizer(['message' => $exceptionDataBag->getValue()])['message']);
        }

        $event->setExceptions($exceptions);

        return $event;
    }
}
