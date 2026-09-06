<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Repository\Cache;

use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Factory\Cache\CacheFactory;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Connection;
use AmeliaBooking\Infrastructure\WP\InstallActions\DB\Payment\PaymentsTable;

/**
 * Class CacheRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Cache
 */
class CacheRepository extends AbstractRepository
{
    /**
     * @param Connection $connection
     * @param string     $table
     */
    public function __construct(
        Connection $connection,
        $table
    ) {
        parent::__construct($connection, $table);
    }

    public const FACTORY = CacheFactory::class;

    /**
     * @param Cache $entity
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($entity)
    {
        $data = $entity->toArray();

        $params = [
            ':name' => $data['name'],
            ':data' => $data['data'],
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO
                {$this->table} 
                (
                `name`,
                `data`
                ) VALUES (
                :name,
                :data
                )"
            );

            $statement->execute($params);
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        return $this->connection->lastInsertId();
    }

    /**
     * @param int   $id
     * @param Cache $entity
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function update($id, $entity)
    {
        $data = $entity->toArray();

        $params = [
            ':paymentId' => $data['paymentId'],
            ':data'      => $data['data'],
            ':id'        => $id,
        ];

        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET
                `paymentId` = :paymentId,
                `data` = :data
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
     * @param int    $id
     * @param string $name
     *
     * @return Cache|null
     * @throws QueryExecutionException
     */
    public function getByIdAndName($id, $name)
    {
        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . " WHERE id = :id AND name = :name"
            );

            $params = [
                ':id'   => $id,
                ':name' => $name
            ];

            $statement->execute($params);

            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find by id in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!$row) {
            return null;
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }

    /**
     * Lock a cache row for the current transaction so finalization can be claimed atomically.
     *
     * @param int $id
     *
     * @return Cache|null
     * @throws QueryExecutionException
     */
    public function getByIdForUpdate($id)
    {
        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . ' WHERE id = :id FOR UPDATE'
            );

            $statement->execute(
                [
                    ':id' => $id,
                ]
            );

            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to lock cache by id in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        if (!$row) {
            return null;
        }

        return call_user_func([static::FACTORY, 'create'], $row);
    }

    /**
     * Atomically claim an unpaid cache row for paid finalization.
     * Returns true only when this caller transitions the row away from unpaid.
     *
     * @param int $id
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function claimPendingAsPaid($id)
    {
        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET `data` = JSON_SET(
                    CASE
                        WHEN JSON_VALID(`data`) THEN `data`
                        ELSE '{}'
                    END,
                    '$.status',
                    'paid'
                )
                WHERE id = :id
                  AND (
                    NOT JSON_VALID(`data`)
                    OR JSON_EXTRACT(`data`, '$.status') IS NULL
                    OR JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.status')) IN ('', 'null', 'pending')
                  )"
            );

            $statement->execute(
                [
                    ':id' => $id,
                ]
            );

            return $statement->rowCount() > 0;
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to claim unpaid cache in ' . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Finds Cache rows whose linked payment is still "pending" for the given gateway
     * and was created before the given cutoff, so a cron job can reconcile them.
     *
     * @param string $gateway
     * @param string $createdBeforeDateTime
     *
     * @return Cache[]
     * @throws QueryExecutionException
     */
    public function getPendingByGatewayOlderThan($gateway, $createdBeforeDateTime)
    {
        $paymentsTable = PaymentsTable::getTableName();

        try {
            $statement = $this->connection->prepare(
                "SELECT c.* FROM {$this->table} c
                INNER JOIN {$paymentsTable} p ON p.id = c.paymentId
                WHERE p.gateway = :gateway AND p.status = 'pending' AND p.created < :createdBeforeDateTime"
            );

            $statement->execute(
                [
                    ':gateway'               => $gateway,
                    ':createdBeforeDateTime' => $createdBeforeDateTime,
                ]
            );

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find pending cache rows in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }

        $caches = [];

        foreach ($rows as $row) {
            $caches[] = call_user_func([static::FACTORY, 'create'], $row);
        }

        return $caches;
    }
}
