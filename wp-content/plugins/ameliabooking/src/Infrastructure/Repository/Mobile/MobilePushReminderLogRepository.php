<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Repository\Mobile;

use AmeliaBooking\Domain\Repository\Mobile\MobilePushReminderLogRepositoryInterface;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;

/**
 * Class MobilePushReminderLogRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Mobile
 */
class MobilePushReminderLogRepository extends AbstractRepository implements MobilePushReminderLogRepositoryInterface
{
    /**
     * @param int[] $appointmentIds
     *
     * @return int[]
     * @throws QueryExecutionException
     */
    public function getSentAppointmentIds(array $appointmentIds): array
    {
        if (empty($appointmentIds)) {
            return [];
        }

        $params = [];
        $placeholders = [];

        foreach (array_values($appointmentIds) as $index => $appointmentId) {
            $param = ':appointmentId' . $index;
            $placeholders[] = $param;
            $params[$param] = (int) $appointmentId;
        }

        try {
            $statement = $this->connection->prepare(
                "SELECT appointmentId FROM {$this->table}
                WHERE appointmentId IN (" . implode(', ', $placeholders) . ')'
            );

            $statement->execute($params);

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to find sent reminder logs in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $sentIds = [];

        foreach ($rows as $row) {
            $sentIds[] = (int) $row['appointmentId'];
        }

        return $sentIds;
    }

    /**
     * @param int $userId
     * @param int $appointmentId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function claim(int $userId, int $appointmentId): bool
    {
        $params = [
            ':userId'        => $userId,
            ':appointmentId' => $appointmentId,
            ':sentDateTime'  => DateTimeService::getNowDateTimeInUtc(),
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT IGNORE INTO {$this->table}
                (`userId`, `appointmentId`, `sentDateTime`)
                VALUES
                (:userId, :appointmentId, :sentDateTime)"
            );

            $statement->execute($params);

            return $statement->rowCount() === 1;
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to claim push reminder in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param int $userId
     * @param int $appointmentId
     *
     * @return void
     * @throws QueryExecutionException
     */
    public function release(int $userId, int $appointmentId): void
    {
        try {
            $statement = $this->connection->prepare(
                "DELETE FROM {$this->table}
                WHERE userId = :userId AND appointmentId = :appointmentId"
            );

            $statement->execute([
                ':userId'        => $userId,
                ':appointmentId' => $appointmentId,
            ]);
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to release push reminder claim in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param int $appointmentId
     *
     * @return void
     * @throws QueryExecutionException
     */
    public function deleteByAppointmentId(int $appointmentId): void
    {
        try {
            $statement = $this->connection->prepare(
                "DELETE FROM {$this->table} WHERE appointmentId = :appointmentId"
            );

            $statement->execute([':appointmentId' => $appointmentId]);
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to delete push reminder logs in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
