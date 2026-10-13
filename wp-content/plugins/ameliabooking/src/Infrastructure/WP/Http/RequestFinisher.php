<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\Http;

/**
 * Class RequestFinisher
 *
 * Lets a webhook answer immediately and do its work afterwards.
 *
 * External calendar providers expect an acknowledgement within a few seconds and retry when they do not get one -
 * Graph eventually drops the subscription altogether, which silently stops two way sync until the renewal cron
 * recreates it. Fetching remote events, writing bookings and sending notifications cannot be done inside that
 * window, so the response is emitted first and the work runs on shutdown.
 *
 * @package AmeliaBooking\Infrastructure\WP\Http
 */
class RequestFinisher
{
    /**
     * Run $work after the response has been sent to the client.
     *
     * Uses the WordPress shutdown action rather than a raw shutdown function so the work still runs before
     * wp_cache_close(), and Slim has emitted the response body by the time it fires.
     *
     * @param callable $work
     *
     * @return void
     */
    public static function deferUntilAfterResponse(callable $work)
    {
        add_action(
            'shutdown',
            function () use ($work) {
                self::finishRequest();

                // Deferred work runs inside the shutdown sequence, where an uncaught throw is a fatal
                // rather than a failed request, and the client that would have seen the error is already
                // gone. Callers are expected to log their own failures with context; this is the last
                // resort that keeps one of them from taking the rest of shutdown down with it.
                try {
                    $work();
                } catch (\Throwable $e) {
                    error_log('Amelia: deferred post-response work failed: ' . $e->getMessage());
                }
            },
            1
        );
    }

    /**
     * Send everything buffered so far and let the client go.
     *
     * @return void
     */
    public static function finishRequest()
    {
        // The client is gone once the response is flushed, so the remaining work must not be killed with it.
        ignore_user_abort(true);

        while (ob_get_level() > 0) {
            if (!@ob_end_flush()) {
                break;
            }
        }

        @flush();

        // Only php-fpm can actually close the connection early. Elsewhere the Content-Length header set by the
        // webhook controller is what tells the provider the response is complete.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
