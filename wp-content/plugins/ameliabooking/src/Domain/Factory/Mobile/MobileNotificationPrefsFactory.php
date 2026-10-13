<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Factory\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Mobile\MobileNotificationPrefs;
use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;

/**
 * Class MobileNotificationPrefsFactory
 *
 * @package AmeliaBooking\Domain\Factory\Mobile
 */
class MobileNotificationPrefsFactory
{
    /**
     * @param array $data
     *
     * @return MobileNotificationPrefs
     * @throws InvalidArgumentException
     */
    public static function create($data)
    {
        $preferences = !empty($data['preferences'])
            ? $data['preferences']
            : json_encode(MobileNotificationPreferences::getDefaults());

        $entity = new MobileNotificationPrefs(
            new Id($data['userId']),
            new Json($preferences)
        );

        if (isset($data['id'])) {
            $entity->setId(new Id($data['id']));
        }

        return $entity;
    }
}
