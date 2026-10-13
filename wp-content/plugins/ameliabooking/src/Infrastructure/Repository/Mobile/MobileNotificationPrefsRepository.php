<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Repository\Mobile;

use AmeliaBooking\Domain\Entity\Mobile\MobileNotificationPrefs;
use AmeliaBooking\Domain\Factory\Mobile\MobileNotificationPrefsFactory;
use AmeliaBooking\Domain\Repository\Mobile\MobileNotificationPrefsRepositoryInterface;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;

/**
 * Class MobileNotificationPrefsRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Mobile
 */
class MobileNotificationPrefsRepository extends AbstractRepository implements MobileNotificationPrefsRepositoryInterface
{
    public const FACTORY = MobileNotificationPrefsFactory::class;

    /**
     * @param MobileNotificationPrefs $entity
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($entity)
    {
        $data = $entity->toArray();

        $params = [
            ':userId'       => $data['userId'],
            ':preferences'  => $data['preferences'],
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table}
                (`userId`, `preferences`)
                VALUES
                (:userId, :preferences)"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection->lastInsertId();
    }

    /**
     * @param int                     $id
     * @param MobileNotificationPrefs $entity
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function update($id, $entity)
    {
        $data = $entity->toArray();

        $params = [
            ':userId'      => $data['userId'],
            ':preferences' => $data['preferences'],
            ':id'          => $id,
        ];

        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET
                `userId` = :userId,
                `preferences` = :preferences
                WHERE
                id = :id"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * @param int $userId
     *
     * @return MobileNotificationPrefs|null
     * @throws QueryExecutionException
     */
    public function getByUserId($userId)
    {
        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . ' WHERE userId = :userId'
            );

            $statement->execute([':userId' => $userId]);

            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find by user id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row) {
            return null;
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }

    /**
     * @param int[] $userIds
     *
     * @return array<int, MobileNotificationPrefs>
     * @throws QueryExecutionException
     */
    public function getByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $params = [];
        $placeholders = [];

        foreach (array_values($userIds) as $index => $userId) {
            $param = ':userId' . $index;
            $placeholders[] = $param;
            $params[$param] = (int) $userId;
        }

        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . ' WHERE userId IN (' . implode(', ', $placeholders) . ')'
            );

            $statement->execute($params);

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to find by user ids in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $prefsByUserId = [];

        foreach ($rows as $row) {
            $entity = call_user_func([static::FACTORY, 'create'], $row);
            $prefsByUserId[(int) $row['userId']] = $entity;
        }

        return $prefsByUserId;
    }

    /**
     * @param MobileNotificationPrefs $entity
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function upsertByUserId(MobileNotificationPrefs $entity)
    {
        $data = $entity->toArray();

        $params = [
            ':userId'      => $data['userId'],
            ':preferences' => $data['preferences'],
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table}
                (`userId`, `preferences`)
                VALUES
                (:userId, :preferences)
                ON DUPLICATE KEY UPDATE
                `preferences` = VALUES(`preferences`)"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to upsert data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        $existing = $this->getByUserId($data['userId']);

        return $existing && $existing->getId() ? $existing->getId()->getValue() : $this->connection->lastInsertId();
    }

    /**
     * @return string
     */
    protected function selectQuery()
    {
        return "SELECT * FROM {$this->table}";
    }
}
