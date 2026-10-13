<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Services\Mobile;

/**
 * Class MobileNotificationPreferences
 *
 * @package AmeliaBooking\Domain\Services\Mobile
 */
class MobileNotificationPreferences
{
    public const CATEGORY_NEW_BOOKINGS   = 'new_bookings';
    public const CATEGORY_STATUS_CHANGES = 'status_changes';
    public const CATEGORY_CHANGES        = 'changes';
    public const CATEGORY_REMINDERS      = 'reminders';

    public const OPTION_REMINDER_TIME_BEFORE = 'reminder_time_before';

    public const DEFAULT_REMINDER_TIME_BEFORE = 3600;

    /**
     * @return array<string, bool>
     */
    public static function getDefaults()
    {
        return [
            self::CATEGORY_NEW_BOOKINGS   => true,
            self::CATEGORY_STATUS_CHANGES => true,
            self::CATEGORY_CHANGES        => true,
            self::CATEGORY_REMINDERS      => true,
        ];
    }

    /**
     * Allowed reminder offsets in seconds (15m, 30m, 1h, 2h, 3h, 6h, 12h, 24h).
     *
     * @return int[]
     */
    public static function getReminderTimeBeforeOptions(): array
    {
        return [900, 1800, 3600, 7200, 10800, 21600, 43200, 86400];
    }

    /**
     * @param array<string, mixed> $preferences
     *
     * @return array<string, bool|int>
     */
    public static function mergeWithDefaults(array $preferences)
    {
        return self::normalize($preferences);
    }

    /**
     * @param array<string, mixed> $preferences
     *
     * @return array<string, bool|int>
     */
    public static function normalize(array $preferences)
    {
        $normalized = self::getDefaults();

        foreach (array_keys($normalized) as $category) {
            if (array_key_exists($category, $preferences)) {
                $normalized[$category] = (bool) $preferences[$category];
            }
        }

        $normalized[self::OPTION_REMINDER_TIME_BEFORE] = self::normalizeReminderTimeBefore(
            $preferences[self::OPTION_REMINDER_TIME_BEFORE] ?? self::DEFAULT_REMINDER_TIME_BEFORE
        );

        return $normalized;
    }

    /**
     * @param mixed $value
     *
     * @return int
     */
    public static function normalizeReminderTimeBefore($value): int
    {
        if (
            !is_int($value)
            && !(is_string($value) && ctype_digit($value))
        ) {
            return self::DEFAULT_REMINDER_TIME_BEFORE;
        }

        $seconds = (int) $value;

        if (in_array($seconds, self::getReminderTimeBeforeOptions(), true)) {
            return $seconds;
        }

        return self::DEFAULT_REMINDER_TIME_BEFORE;
    }

    /**
     * @param array<string, mixed> $preferences
     *
     * @return int
     */
    public static function getReminderTimeBefore(array $preferences): int
    {
        $normalized = self::normalize($preferences);

        return (int) $normalized[self::OPTION_REMINDER_TIME_BEFORE];
    }

    /**
     * @param array<string, mixed> $preferences
     * @param string               $category
     *
     * @return bool
     */
    public static function isCategoryEnabled(array $preferences, $category)
    {
        $normalized = self::normalize($preferences);

        return !empty($normalized[$category]);
    }
}
