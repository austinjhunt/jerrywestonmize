<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\InstallActions\DB\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\AbstractDatabaseTable;

/**
 * Class MobileDevicesTable
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions\DB\Mobile
 */
class MobileDevicesTable extends AbstractDatabaseTable
{
    public const TABLE = 'mobile_devices';

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
                   `expoPushToken` VARCHAR(255) NOT NULL,
                   `platform` ENUM('ios', 'android') NOT NULL,
                   `appVersion` VARCHAR(20) DEFAULT NULL,
                   `lastSeen` DATETIME NOT NULL,
                   `createdAt` DATETIME NOT NULL,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `expoPushToken` (`expoPushToken`),
                   KEY `userId` (`userId`)
                ) {$charsetCollate};";
    }
}
