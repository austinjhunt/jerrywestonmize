<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\Translations;

use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;

/**
 * Class MobilePushNotificationStrings
 *
 * @package AmeliaBooking\Infrastructure\WP\Translations
 */
class MobilePushNotificationStrings
{
    /**
     * @param string      $category
     * @param string      $customerName
     * @param string      $serviceName
     * @param string      $start
     * @param string|null $status
     *
     * @return array{0: string, 1: string}
     */
    public static function getMessage($category, $customerName, $serviceName, $start, $status = null)
    {
        switch ($category) {
            case MobileNotificationPreferences::CATEGORY_NEW_BOOKINGS:
                return [
                    self::newAppointmentTitle(),
                    self::newAppointmentBody($customerName, $serviceName, $start),
                ];

            case MobileNotificationPreferences::CATEGORY_STATUS_CHANGES:
                return [
                    self::statusChangedTitle(),
                    self::statusChangedBody($serviceName, $start, $status),
                ];

            case MobileNotificationPreferences::CATEGORY_CHANGES:
                return [
                    self::appointmentUpdatedTitle(),
                    self::appointmentUpdatedBody($serviceName, $start),
                ];

            case MobileNotificationPreferences::CATEGORY_REMINDERS:
                return [
                    self::appointmentReminderTitle(),
                    self::appointmentReminderBody($serviceName, $start),
                ];

            default:
                return ['', ''];
        }
    }

    /**
     * @return string
     */
    public static function customerFallback()
    {
        return __('Customer', 'wpamelia');
    }

    /**
     * @return string
     */
    public static function serviceFallback()
    {
        return __('Appointment', 'wpamelia');
    }

    /**
     * @return string
     */
    private static function newAppointmentTitle()
    {
        return __('New appointment', 'wpamelia');
    }

    /**
     * @param string $customerName
     * @param string $serviceName
     * @param string $start
     *
     * @return string
     */
    private static function newAppointmentBody($customerName, $serviceName, $start)
    {
        return sprintf(
            /* translators: 1: customer name, 2: service name, 3: appointment start date/time */
            __('%1$s booked %2$s at %3$s.', 'wpamelia'),
            $customerName,
            $serviceName,
            $start
        );
    }

    /**
     * @return string
     */
    private static function statusChangedTitle()
    {
        return __('Appointment status changed', 'wpamelia');
    }

    /**
     * @param string      $serviceName
     * @param string      $start
     * @param string|null $status
     *
     * @return string
     */
    private static function statusChangedBody($serviceName, $start, $status = null)
    {
        if ($status === BookingStatus::REJECTED) {
            return sprintf(
                /* translators: 1: service name, 2: appointment start date/time */
                __('%1$s, %2$s, was rejected.', 'wpamelia'),
                $serviceName,
                $start
            );
        }

        if ($status === BookingStatus::CANCELED) {
            return sprintf(
                /* translators: 1: service name, 2: appointment start date/time */
                __('%1$s, %2$s, was canceled.', 'wpamelia'),
                $serviceName,
                $start
            );
        }

        return sprintf(
            /* translators: 1: service name, 2: appointment start date/time */
            __('%1$s on %2$s had a status change.', 'wpamelia'),
            $serviceName,
            $start
        );
    }

    /**
     * @return string
     */
    private static function appointmentUpdatedTitle()
    {
        return __('Appointment updated', 'wpamelia');
    }

    /**
     * @param string $serviceName
     * @param string $start
     *
     * @return string
     */
    private static function appointmentUpdatedBody($serviceName, $start)
    {
        return sprintf(
            /* translators: 1: service name, 2: appointment start date/time */
            __('%1$s on %2$s was updated.', 'wpamelia'),
            $serviceName,
            $start
        );
    }

    /**
     * @return string
     */
    private static function appointmentReminderTitle()
    {
        return __('Appointment reminder', 'wpamelia');
    }

    /**
     * @param string $serviceName
     * @param string $start
     *
     * @return string
     */
    private static function appointmentReminderBody($serviceName, $start)
    {
        return sprintf(
            /* translators: 1: service name, 2: appointment start date/time */
            __('Reminder: %1$s is scheduled for %2$s.', 'wpamelia'),
            $serviceName,
            $start
        );
    }
}
