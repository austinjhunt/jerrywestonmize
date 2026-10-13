<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;

/**
 * Class MobileNotificationPrefsTable
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\Mobile
 */
class MobileNotificationPrefsTable extends AbstractDatabaseTable
{
    public const TABLE = 'mobile_notification_prefs';

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public static function buildTable()
    {
        $table = self::getTableName();

        $charsetCollate = self::getCharsetCollate();

        return "CREATE TABLE {$table} (
                   `id` INT(11) NOT NULL AUTO_INCREMENT,
                   `userId` INT(11) NOT NULL,
                   `preferences` TEXT NOT NULL,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `userId` (`userId`)
                ) {$charsetCollate};";
    }
}
