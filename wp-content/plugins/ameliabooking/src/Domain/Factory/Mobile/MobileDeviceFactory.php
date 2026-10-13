<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Factory\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Mobile\MobileDevice;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Domain\ValueObjects\Mobile\MobilePlatform;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use Exception;

/**
 * Class MobileDeviceFactory
 *
 * @package AmeliaBooking\Domain\Factory\Mobile
 */
class MobileDeviceFactory
{
    public const APP_VERSION_MAX_LENGTH = 20;

    /**
     * @param array $data
     *
     * @return MobileDevice
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function create($data)
    {
        if (!isset($data['userId'])) {
            throw new InvalidArgumentException('User id can\'t be empty');
        }

        if (!isset($data['platform'])) {
            throw new InvalidArgumentException('Platform can\'t be empty');
        }

        $expoPushToken = isset($data['expoPushToken']) ? trim((string) $data['expoPushToken']) : '';

        if ($expoPushToken === '') {
            throw new InvalidArgumentException('Expo push token can\'t be empty');
        }

        $device = new MobileDevice(
            new Id($data['userId']),
            new Name($expoPushToken),
            new MobilePlatform($data['platform'])
        );

        if (isset($data['id'])) {
            $device->setId(new Id($data['id']));
        }

        if (!empty($data['appVersion'])) {
            $appVersion = trim((string) $data['appVersion']);

            if (strlen($appVersion) > self::APP_VERSION_MAX_LENGTH) {
                throw new InvalidArgumentException(
                    'App version can\'t be longer than ' . self::APP_VERSION_MAX_LENGTH . ' characters'
                );
            }

            $device->setAppVersion(new Name($appVersion));
        }

        if (!empty($data['lastSeen'])) {
            $device->setLastSeen(
                new DateTimeValue(DateTimeService::getCustomDateTimeObjectFromUtc($data['lastSeen']))
            );
        }

        if (!empty($data['createdAt'])) {
            $device->setCreatedAt(
                new DateTimeValue(DateTimeService::getCustomDateTimeObjectFromUtc($data['createdAt']))
            );
        }

        return $device;
    }
}
