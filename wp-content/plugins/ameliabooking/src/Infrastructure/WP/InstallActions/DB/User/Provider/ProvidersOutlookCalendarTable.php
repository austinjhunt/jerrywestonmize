<?php

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\User\Provider;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\Email;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;

/**
 * Class ProvidersOutlookCalendarTable
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\User\Provider
 */
class ProvidersOutlookCalendarTable extends AbstractDatabaseTable
{
    public const TABLE = 'providers_to_outlook_calendar';

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public static function buildTable(): string
    {
        $table = self::getTableName();

        $charsetCollate = self::getCharsetCollate();

        $email = Email::MAX_LENGTH;

        return "CREATE TABLE {$table}  (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `userId` INT(11) NOT NULL,
                  `token` TEXT NOT NULL,
                  `calendarId` TEXT({$email}) NULL,
                  `blockedCalendars` TEXT NULL,
                  `insertPendingAppointments` TINYINT(1) DEFAULT 0,
                  `includeBufferTime` TINYINT(1) DEFAULT 0,
                  `title` TEXT NULL,
                  `description` TEXT NULL,
                  `accountId` VARCHAR(191) DEFAULT NULL,
                  `syncId` INT(11) DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `id` (`id`),
                  KEY `syncId` (`syncId`)
                ) {$charsetCollate};";
    }

    public static function alterTable(): array
    {
        $table = self::getTableName();

        global $wpdb;

        /** @var SettingsService $settingsService */
        $settingsService = new SettingsService(new SettingsStorage());

        $outlookCalendarSettings = $settingsService->getCategorySettings('outlookCalendar');

        // Set default values for existing rows (runs only during 9.4 versions)
        if (
            $outlookCalendarSettings &&
            version_compare(AMELIA_VERSION, '9.4', '>=') &&
            version_compare(AMELIA_VERSION, '9.5', '<')
        ) {
            // Update insertPendingAppointments
            $insertPendingAppointments = $outlookCalendarSettings['insertPendingAppointments'];
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET insertPendingAppointments = %d WHERE insertPendingAppointments = 0",
                    $insertPendingAppointments
                )
            );

            // Update includeBufferTime
            $includeBufferTime = $outlookCalendarSettings['includeBufferTimeOutlookCalendar'];
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET includeBufferTime = %d WHERE includeBufferTime = 0",
                    $includeBufferTime
                )
            );

            // Update title
            $titleJson = json_encode([
                'appointment' => $outlookCalendarSettings['title']['appointment'],
                'event' => $outlookCalendarSettings['title']['event']
            ]);
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET title = %s WHERE title IS NULL",
                    $titleJson
                )
            );

            // Update description
            $descriptionJson = json_encode([
                'appointment' => $outlookCalendarSettings['description']['appointment'],
                'event' => $outlookCalendarSettings['description']['event']
            ]);
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET description = %s WHERE description IS NULL",
                    $descriptionJson
                )
            );
        }

        // Subscription state moved to the calendar_sync table. dbDelta() adds and modifies columns but
        // never drops them, so the superseded ones are removed here. They only ever existed on unreleased
        // 9.8 builds, which is why this drops rather than migrates.
        $commands = [];

        $dropped = [
            'graphSubscriptionId',
            'graphSubscriptionExpiresAt',
            'graphSubscriptionClientState',
        ];

        foreach ($dropped as $column) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column));

            if ($exists !== null) {
                $commands[] = "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
            }
        }

        return $commands;
    }
}
