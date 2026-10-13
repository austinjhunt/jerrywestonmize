<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Repository\Mobile;

/**
 * Interface MobilePushReminderLogRepositoryInterface
 *
 * @package AmeliaBooking\Domain\Repository\Mobile
 */
interface MobilePushReminderLogRepositoryInterface
{
    /**
     * @param int[] $appointmentIds
     *
     * @return int[]
     */
    public function getSentAppointmentIds(array $appointmentIds): array;

    /**
     * Atomically claim a reminder before delivery. Returns false if another process already claimed it.
     *
     * @param int $userId
     * @param int $appointmentId
     *
     * @return bool
     */
    public function claim(int $userId, int $appointmentId): bool;

    /**
     * Release a claim so the reminder can be retried after a failed send.
     *
     * @param int $userId
     * @param int $appointmentId
     *
     * @return void
     */
    public function release(int $userId, int $appointmentId): void;

    /**
     * @param int $appointmentId
     *
     * @return void
     */
    public function deleteByAppointmentId(int $appointmentId): void;
}
