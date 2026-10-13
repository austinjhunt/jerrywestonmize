<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Repository\Mobile;

use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Mobile\MobileDevice;
use AmeliaBooking\Domain\Repository\BaseRepositoryInterface;

/**
 * Interface MobileDeviceRepositoryInterface
 *
 * @package AmeliaBooking\Domain\Repository\Mobile
 */
interface MobileDeviceRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * @param MobileDevice $entity
     *
     * @return int
     */
    public function upsertByToken(MobileDevice $entity);

    /**
     * @param string $token
     *
     * @return MobileDevice|null
     */
    public function getByExpoPushToken($token);

    /**
     * @param int $userId
     *
     * @return Collection
     */
    public function getByUserId($userId);

    /**
     * @return int[]
     */
    public function getUserIdsWithPushTokens(): array;

    /**
     * @param string $token
     *
     * @return bool
     */
    public function deleteByExpoPushToken($token);
}
