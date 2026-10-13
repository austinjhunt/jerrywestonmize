<?php

namespace AmeliaBooking\Infrastructure\Repository\Outlook;

use AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar;
use AmeliaBooking\Domain\Factory\Outlook\OutlookCalendarFactory;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;
use Exception;

/**
 * Class OutlookCalendarRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Outlook
 */
class OutlookCalendarRepository extends AbstractRepository
{
    public const FACTORY = OutlookCalendarFactory::class;

    /**
     * @param OutlookCalendar $outlookCalendar
     * @param int            $userId
     * @param array|null     $additionalSettings
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($outlookCalendar, $userId, $additionalSettings = null)
    {
        $data = $outlookCalendar->toArray();

        $params = [
            ':userId' => $userId,
            ':token'  => $data['token'],
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
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection->lastInsertId();
    }

    /**
     * @param OutlookCalendar $outlookCalendar
     * @param int            $id
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function update($outlookCalendar, $id): bool
    {
        $data = $outlookCalendar->toArray();

        $params = [
            ':token'      => $data['token'],
            ':calendarId' => $data['calendarId'],
            ':id'         => $id
        ];

        try {
            // accountId and syncId are deliberately left alone. They are registry bookkeeping rather than
            // anything a provider form submits, and this method is handed entities built from request data
            // that never carry them - writing them here would detach the connection from its watch on
            // every save.
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET `token` = :token, `calendarId` = :calendarId WHERE id = :id"
            );

            $statement->execute($params);

            return true;
        } catch (Exception $e) {
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
    public function updateSyncId($id, $syncId): bool
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table} SET `syncId` = :syncId WHERE id = :id"
            );

            $statement->execute([':syncId' => $syncId, ':id' => $id]);

            return true;
        } catch (Exception $e) {
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
    public function updateAccountId($id, $accountId): bool
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table} SET `accountId` = :accountId WHERE id = :id"
            );

            $statement->execute([':accountId' => $accountId, ':id' => $id]);

            return true;
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to save account id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Connections still attached to a watch, newest last, so a caller can hand ownership to another one.
     *
     * @param int      $syncId
     * @param int|null $excludeId connection being removed
     *
     * @return array list of ['id' => int, 'userId' => int]
     * @throws QueryExecutionException
     */
    public function getConnectionsBySyncId($syncId, $excludeId = null): array
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
        } catch (Exception $e) {
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
     * Whether a provider is connected to the calendar this watch belongs to. This is the calendar scoped
     * guard: an incoming change may only touch appointments of providers attached to the calendar the
     * notification came from.
     *
     * @param int $syncId
     * @param int $providerId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function isProviderAttachedToSync($syncId, $providerId): bool
    {
        try {
            $statement = $this->connection->prepare(
                "SELECT id FROM {$this->table} WHERE `syncId` = :syncId AND `userId` = :userId LIMIT 1"
            );

            $statement->execute([':syncId' => $syncId, ':userId' => $providerId]);

            return (bool)$statement->fetch();
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to check connection in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int $rowId providers_to_outlook_calendar.id
     *
     * @return int|null
     * @throws QueryExecutionException
     */
    public function getUserIdByOutlookCalendarRowId($rowId)
    {
        try {
            $statement = $this->connection->prepare(
                "SELECT userId FROM {$this->table} WHERE id = :id LIMIT 1"
            );
            $statement->execute([':id' => $rowId]);
            $row = $statement->fetch();
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to lookup userId in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row || !isset($row['userId'])) {
            return null;
        }

        return (int)$row['userId'];
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
        } catch (Exception $e) {
            throw new QueryExecutionException('Unable to find by id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row) {
            throw new NotFoundException('Data not found in ' . __CLASS__);
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }
}
