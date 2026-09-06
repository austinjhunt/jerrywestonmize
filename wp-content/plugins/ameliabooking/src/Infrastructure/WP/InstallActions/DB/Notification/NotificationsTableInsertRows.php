<?php

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\Notification;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;
use AmeliaBooking\Infrastructure\WP\Translations\NotificationsStrings;

/**
 * Class NotificationsTableInsertRows
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\Notification
 */
class NotificationsTableInsertRows extends AbstractDatabaseTable
{
    public const TABLE = 'notifications';

    /**
     * Normalizes a notification row before SQL insert.
     * Copy is translated in NotificationsStrings::notificationTxt() after loadNotificationTextdomain() in buildTable().
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function localizeNotificationRow(array $row)
    {
        return $row;
    }

    /**
     * Load Amelia translations for notification seeding.
     * load_plugin_textdomain() does not load MO files during the activation hook.
     */
    private static function loadNotificationTextdomain()
    {
        if (!defined('AMELIA_PATH') || !defined('AMELIA_DOMAIN')) {
            return;
        }

        $locale = get_locale();
        $mo     = AMELIA_PATH . '/languages/' . $locale . '/wpamelia-' . $locale . '.mo';

        if (!file_exists($mo)) {
            return;
        }

        unload_textdomain(AMELIA_DOMAIN);
        load_textdomain(AMELIA_DOMAIN, $mo);
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function buildTable()
    {
        global $wpdb;

        if (defined('AMELIA_PATH') && defined('AMELIA_DOMAIN')) {
            self::loadNotificationTextdomain();
        }

        $table = self::getTableName();
        $rows  = [];

        $addEmail = !(int)$wpdb->get_row("SELECT COUNT(*) AS count FROM {$table} WHERE type = 'email'")->count;

        if ($addEmail) {
            $rows = array_merge($rows, NotificationsStrings::getAppointmentCustomerNonTimeBasedEmailNotifications());
            $rows = array_merge($rows, NotificationsStrings::getAppointmentCustomerTimeBasedEmailNotifications());
            $rows = array_merge($rows, NotificationsStrings::getAppointmentProviderNonTimeBasedEmailNotifications());
            $rows = array_merge($rows, NotificationsStrings::getAppointmentProviderTimeBasedEmailNotifications());
        }

        $addSMS = !(int)$wpdb->get_row("SELECT COUNT(*) AS count FROM {$table} WHERE type = 'sms'")->count;

        if ($addSMS) {
            $rows = array_merge($rows, NotificationsStrings::getAppointmentCustomerNonTimeBasedSMSNotifications());
            $rows = array_merge($rows, NotificationsStrings::getAppointmentCustomerTimeBasedSMSNotifications());
            $rows = array_merge($rows, NotificationsStrings::getAppointmentProviderNonTimeBasedSMSNotifications());
            $rows = array_merge($rows, NotificationsStrings::getAppointmentProviderTimeBasedSMSNotifications());
        }

        $addEvent = !(int)$wpdb->get_row("SELECT COUNT(*) AS count FROM {$table} WHERE entity = 'event'")->count;

        if ($addEvent) {
            $rows = array_merge($rows, NotificationsStrings::getEventCustomerNonTimeBasedEmailNotifications());
            $rows = array_merge($rows, NotificationsStrings::getEventCustomerTimeBasedEmailNotifications());
            $rows = array_merge($rows, NotificationsStrings::getEventProviderNonTimeBasedEmailNotifications());
            $rows = array_merge($rows, NotificationsStrings::getEventProviderTimeBasedEmailNotifications());

            $rows = array_merge($rows, NotificationsStrings::getEventCustomerNonTimeBasedSMSNotifications());
            $rows = array_merge($rows, NotificationsStrings::getEventCustomerTimeBasedSMSNotifications());
            $rows = array_merge($rows, NotificationsStrings::getEventProviderNonTimeBasedSMSNotifications());
            $rows = array_merge($rows, NotificationsStrings::getEventProviderTimeBasedSMSNotifications());
        }

        $addAccountRecovery = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_account_recovery'"
        )->count;

        if ($addAccountRecovery) {
            $rows = array_merge($rows, [NotificationsStrings::getAccountRecoveryNotification()]);
        }

        $addEmployeePanelAccess = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_panel_access'"
        )->count;

        if ($addEmployeePanelAccess) {
            $rows = array_merge($rows, [NotificationsStrings::getEmployeePanelAccessNotification()]);
        }

        $addEmployeePanelRecovery = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_panel_recovery'"
        )->count;

        if ($addEmployeePanelRecovery) {
            $rows = array_merge($rows, [NotificationsStrings::getEmployeeAccountRecoveryNotification()]);
        }

        $customerPackagePurchased = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_package_purchased'"
        )->count;

        if ($customerPackagePurchased) {
            $rows = array_merge($rows, [NotificationsStrings::getCustomerPackagePurchasedEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerPackagePurchasedSmsNotification()]);
        }

        $providerPackagePurchased = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_package_purchased'"
        )->count;

        if ($providerPackagePurchased) {
            $rows = array_merge($rows, [NotificationsStrings::getProviderPackagePurchasedEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getProviderPackagePurchasedSmsNotification()]);
        }

        $customerPackageCanceled = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_package_canceled'"
        )->count;

        if ($customerPackageCanceled) {
            $rows = array_merge($rows, [NotificationsStrings::getCustomerPackageCanceledEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerPackageCanceledSmsNotification()]);
        }

        $providerPackageCanceled = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_package_canceled'"
        )->count;

        if ($providerPackageCanceled) {
            $rows = array_merge($rows, [NotificationsStrings::getProviderPackageCanceledEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getProviderPackageCanceledSmsNotification()]);
        }

        $customerCart = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_cart'"
        )->count;

        if ($customerCart) {
            $rows = array_merge($rows, [NotificationsStrings::getCustomerCartEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerCartSmsNotification()]);
        }

        $providerCart = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_cart'"
        )->count;

        if ($providerCart) {
            $rows = array_merge($rows, [NotificationsStrings::getProviderCartEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getProviderCartSmsNotification()]);
        }

        $customerWaitingList = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_event_waiting'"
        )->count;

        if ($customerWaitingList) {
            $rows = array_merge($rows, [NotificationsStrings::getCustomerWaitingListEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerWaitingListSmsNotification()]);
        }

        $customerAppointmentWaitingList = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_appointment_waiting'"
        )->count;

        if ($customerAppointmentWaitingList) {
            $rows = array_merge($rows, [NotificationsStrings::getCustomerAppointmentWaitingListEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerAppointmentWaitingListSmsNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerAppointmentWaitingListAvailableSpotEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getCustomerAppointmentWaitingListAvailableSpotSmsNotification()]);
        }

        $providerWaitingList = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_event_waiting'"
        )->count;

        if ($providerWaitingList) {
            $rows = array_merge($rows, [NotificationsStrings::getProviderWaitingListEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getProviderWaitingListSmsNotification()]);
        }

        $customerQrCode = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_event_qr_code'"
        )->count;

        if ($customerQrCode) {
            $rows = array_merge($rows, [NotificationsStrings::getCustomerQrCodeEmailNotification()]);
        }

        $providerAppointmentWaitingList = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_appointment_waiting'"
        )->count;

        if ($providerAppointmentWaitingList) {
            $rows = array_merge($rows, [NotificationsStrings::getProviderAppointmentWaitingListEmailNotification()]);
            $rows = array_merge($rows, [NotificationsStrings::getProviderAppointmentWaitingListSmsNotification()]);
        }

        $addWhatsApp = !(int)$wpdb->get_row("SELECT COUNT(*) AS count FROM {$table} WHERE type = 'whatsapp'")->count;

        if ($addWhatsApp) {
            $rows = array_merge($rows, NotificationsStrings::getWhatsAppNotifications());
        }

        $whatsAppCart = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_cart' AND type = 'whatsapp'"
        )->count;

        if ($whatsAppCart) {
            $rows = array_merge($rows, NotificationsStrings::getWhatsAppCartNotifications());
        }

        $whatsAppWaitingList = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_event_waiting' AND type = 'whatsapp'"
        )->count;

        if ($whatsAppWaitingList) {
            $rows = array_merge($rows, NotificationsStrings::getWhatsAppWaitingListNotifications());
        }

        $whatsAppAppointmentWaitingList = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'provider_appointment_waiting' AND type = 'whatsapp'"
        )->count;

        if ($whatsAppAppointmentWaitingList) {
            $rows = array_merge($rows, NotificationsStrings::getWhatsAppAppointmentWaitingListNotifications());
            $rows = array_merge($rows, NotificationsStrings::getWhatsAppAppointmentWaitingListAvailableSpotNotifications());
        }

        $appointmentUpdated = !(int)$wpdb->get_row("SELECT COUNT(*) AS count FROM {$table} WHERE name LIKE '%appointment_updated'")->count;
        if ($appointmentUpdated) {
            $rows = array_merge($rows, NotificationsStrings::getAppointmentUpdatedNotifications($addEmail));
        }

        $eventUpdated = !(int)$wpdb->get_row("SELECT COUNT(*) AS count FROM {$table} WHERE name LIKE '%event_updated'")->count;
        if ($eventUpdated) {
            $rows = array_merge($rows, NotificationsStrings::getEventUpdatedNotifications($addEmail));
        }

        $invoiceNotifications = !(int)$wpdb->get_row(
            "SELECT COUNT(*) AS count FROM {$table} WHERE name = 'customer_invoice'"
        )->count;

        if ($invoiceNotifications) {
            $rows = array_merge($rows, NotificationsStrings::getInvoiceNotification());
        }

        $result = [];

        foreach ($rows as $row) {
            $row    = self::localizeNotificationRow($row);
            $status = !empty($row['status']) ? $row['status'] : 'enabled';

            $escapedName    = esc_sql($row['name']);
            $escapedType    = esc_sql($row['type']);
            $escapedSendTo  = esc_sql($row['sendTo']);
            $escapedSubject = esc_sql($row['subject']);
            $escapedContent = esc_sql($row['content']);
            $escapedEntity  = esc_sql($row['entity']);
            $escapedStatus  = esc_sql($status);

            $result[] = "INSERT INTO {$table} 
                        (
                            `name`,
                            `type`,
                            `time`,
                            `timeBefore`,
                            `timeAfter`,
                            `sendTo`,
                            `subject`,
                            `content`,
                            `entity`,
                            `status`
                        ) 
                        VALUES
                        (
                            '{$escapedName}',
                            '{$escapedType}',
                             {$row['time']},
                             {$row['timeBefore']},
                             {$row['timeAfter']},
                            '{$escapedSendTo}',
                            '{$escapedSubject}',
                            '{$escapedContent}',
                            '{$escapedEntity}',
                            '{$escapedStatus}'
                        )";
        }

        return $result;
    }
}
