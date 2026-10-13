<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Repository\Mobile;

use AmeliaBooking\Domain\Entity\Mobile\MobileNotificationPrefs;
use AmeliaBooking\Domain\Repository\BaseRepositoryInterface;

/**
 * Interface MobileNotificationPrefsRepositoryInterface
 *
 * @package AmeliaBooking\Domain\Repository\Mobile
 */
interface MobileNotificationPrefsRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param int $userId
     *
     * @return MobileNotificationPrefs|null
     */
    public function getByUserId($userId);

    /**
     * @param int[] $userIds
     *
     * @return array<int, MobileNotificationPrefs>
     */
    public function getByUserIds(array $userIds): array;

    /**
     * @param MobileNotificationPrefs $entity
     *
     * @return int
     */
    public function upsertByUserId(MobileNotificationPrefs $entity);
}
