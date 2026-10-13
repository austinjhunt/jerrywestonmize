<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\Calendar;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;

/**
 * Class CalendarSyncTable
 *
 * One row per watched external calendar. A push channel or Graph subscription belongs to the calendar it
 * watches, not to the employee connection that happened to register it, so several connections pointing at
 * the same calendar share a single row here and a single remote watch.
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\Calendar
 */
class CalendarSyncTable extends AbstractDatabaseTable
{
    public const TABLE = 'calendar_sync';

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public static function buildTable(): string
    {
        $table = self::getTableName();

        $charsetCollate = self::getCharsetCollate();

        return "CREATE TABLE {$table} (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `type` VARCHAR(20) NOT NULL,
                  `accountId` VARCHAR(191) NOT NULL,
                  `calendarId` VARCHAR(191) NOT NULL,
                  `ownerConnectionId` INT(11) DEFAULT NULL,
                  `subscriptionId` VARCHAR(191) DEFAULT NULL,
                  `resourceId` VARCHAR(255) DEFAULT NULL,
                  `clientState` VARCHAR(128) DEFAULT NULL,
                  `syncToken` TEXT NULL,
                  `expiresAt` DATETIME NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `calendar` (`type`, `accountId`, `calendarId`),
                  UNIQUE KEY `subscription` (`type`, `subscriptionId`),
                  KEY `ownerConnectionId` (`ownerConnectionId`)
                ) {$charsetCollate};";
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function alterTable()
    {
        global $wpdb;

        // Sync locking moved to the database's own advisory locks, which need no table. This only ever
        // existed on unreleased 9.8 builds, so it is dropped rather than migrated.
        $legacyLocksTable = self::getTableName() . '_locks';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacyLocksTable)) === $legacyLocksTable) {
            return ["DROP TABLE IF EXISTS `{$legacyLocksTable}`"];
        }

        return [];
    }
}
