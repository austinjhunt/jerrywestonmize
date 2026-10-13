<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Repository\Mobile;

use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Mobile\MobileDevice;
use AmeliaBooking\Domain\Factory\Mobile\MobileDeviceFactory;
use AmeliaBooking\Domain\Repository\Mobile\MobileDeviceRepositoryInterface;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;

/**
 * Class MobileDeviceRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Mobile
 */
class MobileDeviceRepository extends AbstractRepository implements MobileDeviceRepositoryInterface
{
    public const FACTORY = MobileDeviceFactory::class;

    /**
     * @param MobileDevice $entity
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($entity)
    {
        $data = $entity->toArray();

        $params = [
            ':userId'        => $data['userId'],
            ':expoPushToken' => $data['expoPushToken'],
            ':platform'      => $data['platform'],
            ':appVersion'    => $data['appVersion'],
            ':lastSeen'      => $data['lastSeen'] ?: DateTimeService::getNowDateTime(),
            ':createdAt'     => $data['createdAt'] ?: DateTimeService::getNowDateTime(),
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table}
                (`userId`, `expoPushToken`, `platform`, `appVersion`, `lastSeen`, `createdAt`)
                VALUES
                (:userId, :expoPushToken, :platform, :appVersion, :lastSeen, :createdAt)"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection->lastInsertId();
    }

    /**
     * @param int          $id
     * @param MobileDevice $entity
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function update($id, $entity)
    {
        $data = $entity->toArray();

        $params = [
            ':userId'        => $data['userId'],
            ':expoPushToken' => $data['expoPushToken'],
            ':platform'      => $data['platform'],
            ':appVersion'    => $data['appVersion'],
            ':lastSeen'      => $data['lastSeen'] ?: DateTimeService::getNowDateTime(),
            ':id'            => $id,
        ];

        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET
                `userId` = :userId,
                `expoPushToken` = :expoPushToken,
                `platform` = :platform,
                `appVersion` = :appVersion,
                `lastSeen` = :lastSeen
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
     * @param MobileDevice $entity
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function upsertByToken(MobileDevice $entity)
    {
        $data = $entity->toArray();

        $params = [
            ':userId'        => $data['userId'],
            ':expoPushToken' => $data['expoPushToken'],
            ':platform'      => $data['platform'],
            ':appVersion'    => $data['appVersion'],
            ':lastSeen'      => $data['lastSeen'] ?: DateTimeService::getNowDateTime(),
            ':createdAt'     => $data['createdAt'] ?: DateTimeService::getNowDateTime(),
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table}
                (`userId`, `expoPushToken`, `platform`, `appVersion`, `lastSeen`, `createdAt`)
                VALUES
                (:userId, :expoPushToken, :platform, :appVersion, :lastSeen, :createdAt)
                ON DUPLICATE KEY UPDATE
                `userId` = VALUES(`userId`),
                `platform` = VALUES(`platform`),
                `appVersion` = VALUES(`appVersion`),
                `lastSeen` = VALUES(`lastSeen`)"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to upsert data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        $existing = $this->getByExpoPushToken($data['expoPushToken']);

        return $existing && $existing->getId() ? $existing->getId()->getValue() : $this->connection->lastInsertId();
    }

    /**
     * @param string $token
     *
     * @return MobileDevice|null
     * @throws QueryExecutionException
     */
    public function getByExpoPushToken($token)
    {
        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . ' WHERE expoPushToken = :expoPushToken'
            );

            $statement->execute([':expoPushToken' => $token]);

            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find by token in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row) {
            return null;
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }

    /**
     * @param int $userId
     *
     * @return Collection
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function getByUserId($userId)
    {
        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . ' WHERE userId = :userId'
            );

            $statement->execute([':userId' => $userId]);

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find by user id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        $collection = new Collection();

        foreach ($rows as $row) {
            $collection->addItem(
                call_user_func([static::FACTORY, 'create'], $row),
                $row['id']
            );
        }

        return $collection;
    }

    /**
     * @return int[]
     * @throws QueryExecutionException
     */
    public function getUserIdsWithPushTokens(): array
    {
        try {
            $statement = $this->connection->query(
                "SELECT DISTINCT userId FROM {$this->table}
                WHERE expoPushToken IS NOT NULL AND expoPushToken != ''"
            );

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to find users with push tokens in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $userIds = [];

        foreach ($rows as $row) {
            $userIds[] = (int) $row['userId'];
        }

        return $userIds;
    }

    /**
     * @param string $token
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function deleteByExpoPushToken($token)
    {
        try {
            $statement = $this->connection->prepare(
                "DELETE FROM {$this->table} WHERE expoPushToken = :expoPushToken"
            );

            $statement->execute([':expoPushToken' => $token]);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to delete by token in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * @return string
     */
    protected function selectQuery()
    {
        return "SELECT * FROM {$this->table}";
    }
}
