<?php

/**
 * Mutex for creating a CPT row per Amelia entity id (WordPress add_option).
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes;

/**
 * Class CptEntityCreateLock
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes
 */
class CptEntityCreateLock
{
    /**
     * Remove option only if option_value still matches the observed logical value (atomic compare-and-delete).
     *
     * @param string $key                  Option name.
     * @param string $expectedLogicalValue Value as returned by get_option (unserialized).
     *
     * @return bool True when a row was deleted.
     */
    private static function deleteOptionIfValueMatches(string $key, string $expectedLogicalValue): bool
    {
        global $wpdb;

        $serialized = maybe_serialize($expectedLogicalValue);

        $deleted = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
                $key,
                $serialized
            )
        );

        if ($deleted > 0) {
            wp_cache_delete($key, 'options');

            return true;
        }

        return false;
    }

    /**
     * @param string $optionKeyPrefix e.g. amelia_wpa_event_lock_
     * @param int    $entityId
     * @param int    $ttlSeconds
     *
     * @return string|false Lock token or false if locked
     */
    public static function acquire($optionKeyPrefix, $entityId, $ttlSeconds)
    {
        $entityId = (int) $entityId;
        $ttlSeconds = (int) $ttlSeconds;

        if ($entityId <= 0 || $ttlSeconds <= 0) {
            return false;
        }

        $key = (string) $optionKeyPrefix . $entityId;

        $existing = get_option($key, false);

        if ($existing !== false) {
            $existing = (string) $existing;
            $parts = explode(':', $existing);
            $expiresAt = (int) end($parts);

            if ($expiresAt > time()) {
                return false;
            }

            self::deleteOptionIfValueMatches($key, $existing);
        }

        $token = wp_generate_uuid4() . ':' . (time() + $ttlSeconds);

        return add_option(
            $key,
            $token,
            '',
            false
        ) ? $token : false;
    }

    /**
     * @param string $optionKeyPrefix
     * @param int    $entityId
     * @param string $lockToken
     */
    public static function release($optionKeyPrefix, $entityId, $lockToken)
    {
        $entityId = (int) $entityId;
        $lockToken = (string) $lockToken;

        if ($entityId <= 0 || $lockToken === '') {
            return;
        }

        $key = (string) $optionKeyPrefix . $entityId;

        self::deleteOptionIfValueMatches($key, $lockToken);
    }
}
