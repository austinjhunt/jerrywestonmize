<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $url): string
    {
        return rtrim($url, '/');
    }
}

if (!function_exists('get_option')) {
    /** @var array<string, mixed> */
    $GLOBALS['melograno_usage_tracker_options'] = [];

    function get_option(string $option, $default = false)
    {
        return array_key_exists($option, $GLOBALS['melograno_usage_tracker_options'])
            ? $GLOBALS['melograno_usage_tracker_options'][$option]
            : $default;
    }

    function update_option(string $option, $value, $autoload = null): bool
    {
        $GLOBALS['melograno_usage_tracker_options'][$option] = $value;

        return true;
    }

    function delete_option(string $option): bool
    {
        unset($GLOBALS['melograno_usage_tracker_options'][$option]);

        return true;
    }

    function metadata_exists(string $meta_type, string $object_id, string $meta_key = ''): bool
    {
        if ($meta_type !== 'option') {
            return false;
        }

        return array_key_exists($object_id, $GLOBALS['melograno_usage_tracker_options']);
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []): bool
    {
        return false;
    }

    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool
    {
        return true;
    }

    function wp_clear_scheduled_hook(string $hook, array $args = []): int
    {
        return 0;
    }
}
