<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;

/**
 * Class MobilePushReminderLogsTable
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\Mobile
 */
class MobilePushReminderLogsTable extends AbstractDatabaseTable
{
    public const TABLE = 'mobile_push_reminder_logs';

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
                   `userId` INT(11) NOT NULL,
                   `appointmentId` INT(11) NOT NULL,
                   `sentDateTime` DATETIME NOT NULL,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `user_appointment` (`userId`, `appointmentId`),
                   KEY `appointmentId` (`appointmentId`)
                ) {$charsetCollate};";
    }
}
