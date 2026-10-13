<?php

namespace AmeliaBooking\Infrastructure\Repository\Google;

use AmeliaBooking\Domain\Entity\Google\GoogleCalendar;
use AmeliaBooking\Domain\Factory\Google\GoogleCalendarFactory;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;

/**
 * Class GoogleRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Google
 */
class GoogleCalendarRepository extends AbstractRepository
{
    public const FACTORY = GoogleCalendarFactory::class;

    /**
     * @param GoogleCalendar $googleCalendar
     * @param int            $userId
     * @param array|null     $additionalSettings
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($googleCalendar, $userId, $additionalSettings = null)
    {
        $data = $googleCalendar->toArray();

        $params = [
            ':userId'     => $userId,
            ':token'      => $data['token'],
            ':calendarId' => $data['calendarId']
        ];

        $fields = ['userId', 'token', 'calendarId'];
        $placeholders = [':userId', ':token', ':calendarId'];

        if ($additionalSettings !== null) {
            if (isset($additionalSettings['insertPendingAppointments'])) {
                $fields[] = 'insertPendingAppointments';
                $placeholders[] = ':insertPendingAppointments';
                $params[':insertPendingAppointments'] = (int)$additionalSettings['insertPendingAppointments'];
            }

            if (isset($additionalSettings['includeBufferTime'])) {
                $fields[] = 'includeBufferTime';
                $placeholders[] = ':includeBufferTime';
                $params[':includeBufferTime'] = (int)$additionalSettings['includeBufferTime'];
            }

            if (isset($additionalSettings['title'])) {
                $fields[] = 'title';
                $placeholders[] = ':title';
                $params[':title'] = is_array($additionalSettings['title'])
                    ? json_encode($additionalSettings['title'])
                    : $additionalSettings['title'];
            }

            if (isset($additionalSettings['description'])) {
                $fields[] = 'description';
                $placeholders[] = ':description';
                $params[':description'] = is_array($additionalSettings['description'])
                    ? json_encode($additionalSettings['description'])
                    : $additionalSettings['description'];
            }
        }

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table}
                (`" . implode('`, `', $fields) . "`)
                VALUES
                (" . implode(', ', $placeholders) . ")"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection->lastInsertId();
    }

    /**
     * @param GoogleCalendar $googleCalendar
     * @param int            $id
     *
     * @return mixed
     * @throws QueryExecutionException
     */
    public function update($googleCalendar, $id)
    {
        $data = $googleCalendar->toArray();

        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;

        $params = [
            ':token'                      => $data['token'],
            ':calendarId'                 => $data['calendarId'],
            ':insertPendingAppointments'  => (int)($data['insertPendingAppointments'] ?? false),
            ':includeBufferTime'          => (int)($data['includeBufferTime'] ?? false),
            ':title'                      => is_array($title) ? json_encode($title) : $title,
            ':description'                => is_array($description) ? json_encode($description) : $description,
            ':id'                         => $id
        ];

        try {
            // accountId and syncId are deliberately left alone. They are registry bookkeeping rather than
            // anything a provider form submits, and this method is handed entities built from request data
            // that never carry them - writing them here would detach the connection from its watch on
            // every save.
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET 
                    `token` = :token,
                    `calendarId` = :calendarId,
                    `insertPendingAppointments` = :insertPendingAppointments,
                    `includeBufferTime` = :includeBufferTime,
                    `title` = :title,
                    `description` = :description
                WHERE id = :id"
            );

            $statement->execute($params);

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int      $id
     * @param int|null $syncId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function updateSyncId($id, $syncId)
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table} SET `syncId` = :syncId WHERE id = :id"
            );

            $statement->execute([':syncId' => $syncId, ':id' => $id]);

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save sync id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int    $id
     * @param string $accountId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function updateAccountId($id, $accountId)
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table} SET `accountId` = :accountId WHERE id = :id"
            );

            $statement->execute([':accountId' => $accountId, ':id' => $id]);

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save account id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Connections still attached to a watch, so a caller can hand ownership to another one.
     *
     * @param int      $syncId
     * @param int|null $excludeId connection being removed
     *
     * @return array list of ['id' => int, 'userId' => int]
     * @throws QueryExecutionException
     */
    public function getConnectionsBySyncId($syncId, $excludeId = null)
    {
        $params = [':syncId' => $syncId];

        $where = '';
        if ($excludeId !== null) {
            $where = ' AND id <> :excludeId';
            $params[':excludeId'] = $excludeId;
        }

        try {
            $statement = $this->connection->prepare(
                "SELECT id, userId FROM {$this->table} WHERE `syncId` = :syncId{$where} ORDER BY id ASC"
            );

            $statement->execute($params);

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to get connections in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return array_map(
            function ($row) {
                return ['id' => (int)$row['id'], 'userId' => (int)$row['userId']];
            },
            $rows ?: []
        );
    }

    /**
     * Whether a provider is connected to the calendar this watch belongs to - the calendar scoped guard.
     *
     * @param int $syncId
     * @param int $providerId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function isProviderAttachedToSync($syncId, $providerId)
    {
        try {
            $statement = $this->connection->prepare(
                "SELECT id FROM {$this->table} WHERE `syncId` = :syncId AND `userId` = :userId LIMIT 1"
            );

            $statement->execute([':syncId' => $syncId, ':userId' => $providerId]);

            return (bool)$statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to check connection in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param $userId
     *
     * @return mixed
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function getByProviderId($userId)
    {
        try {
            $statement = $this->connection->prepare($this->selectQuery() . " WHERE {$this->table}.userId = :userId");
            $statement->bindParam(':userId', $userId);
            $statement->execute();
            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find by id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row) {
            throw new NotFoundException('Data not found in ' . __CLASS__);
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }
}
