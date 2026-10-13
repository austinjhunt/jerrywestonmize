<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Factory\Calendar;

use AmeliaBooking\Domain\Entity\Calendar\CalendarSync;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;

/**
 * Class CalendarSyncFactory
 *
 * @package AmeliaBooking\Domain\Factory\Calendar
 */
class CalendarSyncFactory
{
    /**
     * @param array $data
     *
     * @return CalendarSync
     */
    public static function create($data): CalendarSync
    {
        $calendarSync = new CalendarSync(
            isset($data['type']) ? (string)$data['type'] : '',
            isset($data['accountId']) ? (string)$data['accountId'] : '',
            isset($data['calendarId']) ? (string)$data['calendarId'] : ''
        );

        if (!empty($data['id'])) {
            $calendarSync->setId(new Id($data['id']));
        }

        if (!empty($data['ownerConnectionId'])) {
            $calendarSync->setOwnerConnectionId($data['ownerConnectionId']);
        }

        if (!empty($data['subscriptionId'])) {
            $calendarSync->setSubscriptionId((string)$data['subscriptionId']);
        }

        if (!empty($data['resourceId'])) {
            $calendarSync->setResourceId((string)$data['resourceId']);
        }

        if (!empty($data['clientState'])) {
            $calendarSync->setClientState((string)$data['clientState']);
        }

        if (!empty($data['syncToken'])) {
            $calendarSync->setSyncToken((string)$data['syncToken']);
        }

        if (!empty($data['expiresAt'])) {
            $calendarSync->setExpiresAt((string)$data['expiresAt']);
        }

        return $calendarSync;
    }
}
