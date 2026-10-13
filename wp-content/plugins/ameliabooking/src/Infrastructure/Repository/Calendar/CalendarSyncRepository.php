<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Repository\Calendar;

use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Calendar\CalendarSync;
use AmeliaBooking\Domain\Factory\Calendar\CalendarSyncFactory;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;

/**
 * Class CalendarSyncRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Calendar
 */
class CalendarSyncRepository extends AbstractRepository
{
    public const FACTORY = CalendarSyncFactory::class;

    /**
     * Claim the watch for a calendar, or return the one that already exists.
     *
     * Two connections being saved at the same time would otherwise race between the lookup and the
     * insert, and the loser would hit the unique key - which wpdb reports as a database error. INSERT
     * IGNORE makes the claim atomic and lets both callers end up with the same row.
     *
     * @param string   $type
     * @param string   $accountId
     * @param string   $calendarId
     * @param int|null $ownerConnectionId used only when this call is the one that creates the row
     *
     * @return CalendarSync|null
     * @throws QueryExecutionException
     */
    public function getOrCreate($type, $accountId, $calendarId, $ownerConnectionId = null): ?CalendarSync
    {
        try {
            $statement = $this->connection->prepare(
                "INSERT IGNORE INTO {$this->table} (`type`, `accountId`, `calendarId`, `ownerConnectionId`)
                 VALUES (:type, :accountId, :calendarId, :ownerConnectionId)"
            );

            $statement->execute(
                [
                    ':type'              => $type,
                    ':accountId'         => $accountId,
                    ':calendarId'        => $calendarId,
                    ':ownerConnectionId' => $ownerConnectionId,
                ]
            );
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->getByCalendar($type, $accountId, $calendarId);
    }

    /**
     * The calendar's identity: a mailbox plus a calendar id within it.
     *
     * @param string $type
     * @param string $accountId
     * @param string $calendarId
     *
     * @return CalendarSync|null
     * @throws QueryExecutionException
     */
    public function getByCalendar($type, $accountId, $calendarId): ?CalendarSync
    {
        return $this->fetchOne(
            "SELECT * FROM {$this->table}
             WHERE `type` = :type AND `accountId` = :accountId AND `calendarId` = :calendarId
             LIMIT 1",
            [
                ':type'       => $type,
                ':accountId'  => $accountId,
                ':calendarId' => $calendarId,
            ]
        );
    }

    /**
     * @param string $type
     * @param string $subscriptionId
     *
     * @return CalendarSync|null
     * @throws QueryExecutionException
     */
    public function getBySubscriptionId($type, $subscriptionId): ?CalendarSync
    {
        if (!$subscriptionId) {
            return null;
        }

        return $this->fetchOne(
            "SELECT * FROM {$this->table}
             WHERE `type` = :type AND `subscriptionId` = :subscriptionId
             LIMIT 1",
            [
                ':type'           => $type,
                ':subscriptionId' => $subscriptionId,
            ]
        );
    }

    /**
     * @param string $type
     *
     * @return Collection
     * @throws QueryExecutionException
     */
    public function getAllByType($type): Collection
    {
        $collection = new Collection();

        try {
            $statement = $this->connection->prepare("SELECT * FROM {$this->table} WHERE `type` = :type");
            $statement->execute([':type' => $type]);
            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to get data from ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        foreach ($rows as $row) {
            $collection->addItem(
                call_user_func([static::FACTORY, 'create'], $row),
                $row['id']
            );
        }

        return $collection;
    }

    /**
     * @param int         $id
     * @param string|null $subscriptionId
     * @param string|null $expiresAt
     * @param string|null $clientState
     * @param string|null $resourceId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function updateSubscriptionState($id, $subscriptionId, $expiresAt, $clientState, $resourceId = null): bool
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET `subscriptionId` = :subscriptionId,
                    `expiresAt` = :expiresAt,
                    `clientState` = :clientState,
                    `resourceId` = :resourceId
                WHERE id = :id"
            );

            $statement->execute(
                [
                    ':subscriptionId' => $subscriptionId,
                    ':expiresAt'      => $expiresAt,
                    ':clientState'    => $clientState,
                    ':resourceId'     => $resourceId,
                    ':id'             => $id,
                ]
            );

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save subscription in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int $id
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function clearSubscriptionState($id): bool
    {
        return $this->updateSubscriptionState($id, null, null, null, null);
    }

    /**
     * The cursor is read back on its own so a caller that waited on a lock picks up a token written by
     * whoever held it, instead of replaying events with a stale one.
     *
     * @param int $id
     *
     * @return string|null
     * @throws QueryExecutionException
     */
    public function getSyncTokenById($id): ?string
    {
        try {
            $statement = $this->connection->prepare(
                "SELECT `syncToken` FROM {$this->table} WHERE id = :id LIMIT 1"
            );

            $statement->execute([':id' => $id]);

            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to get sync token in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $row && !empty($row['syncToken']) ? (string)$row['syncToken'] : null;
    }

    /**
     * @param int         $id
     * @param string|null $syncToken
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function updateSyncToken($id, $syncToken): bool
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table} SET `syncToken` = :syncToken WHERE id = :id"
            );

            $statement->execute([':syncToken' => $syncToken, ':id' => $id]);

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save sync token in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int      $id
     * @param int|null $ownerConnectionId
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function updateOwnerConnectionId($id, $ownerConnectionId): bool
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table} SET `ownerConnectionId` = :ownerConnectionId WHERE id = :id"
            );

            $statement->execute(
                [
                    ':ownerConnectionId' => $ownerConnectionId,
                    ':id'                => $id,
                ]
            );

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save owner in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $query
     * @param array  $params
     *
     * @return CalendarSync|null
     * @throws QueryExecutionException
     */
    private function fetchOne($query, array $params): ?CalendarSync
    {
        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $row ? call_user_func([static::FACTORY, 'create'], $row) : null;
    }
}
