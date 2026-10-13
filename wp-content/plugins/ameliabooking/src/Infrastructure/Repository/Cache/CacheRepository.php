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
     * How long a paid-finalization claim is trusted before it is treated as abandoned.
     *
     * Long enough to outlast a slow booking (notifications, calendar sync, invoices) and
     * short enough that a customer whose finalization died is booked by the next webhook
     * delivery rather than left paid and unbooked.
     */
    public const PAID_CLAIM_STALE_SECONDS = 300;

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
     * "captureFailed" counts as unpaid: the booking was stored but the authorized funds were never
     * captured, so a later retry that does capture them has to be able to claim the row.
     *
     * @param int $id
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function claimPendingAsPaid($id)
    {
        // 'captureFailed' is claimable too (AMR-2555): an authorization whose capture failed is
        // still an unpaid row that a later delivery must be able to take over. The claim goes
        // through claimAsPaid() so it is stamped with the time it was taken, which is what lets
        // an abandoned finalization be told apart from one that is still running (AMR-2304).
        return $this->claimAsPaid(
            $id,
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.status')) IN ('', 'null', 'pending', 'captureFailed')",
            'Unable to claim unpaid cache in '
        );
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

    /**
     * @param string $paymentIntentId
     *
     * @return Cache|null
     * @throws QueryExecutionException
     */
    public function getByStripePaymentIntentId($paymentIntentId): ?Cache
    {
        try {
            $statement = $this->connection->prepare(
                $this->selectQuery() . " WHERE JSON_VALID(`data`)
                AND JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.stripePaymentIntentId')) = :paymentIntentId
                ORDER BY id DESC
                LIMIT 1"
            );

            $statement->execute(
                [
                    ':paymentIntentId' => $paymentIntentId,
                ]
            );

            $row = $statement->fetch();
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                'Unable to find cache by Stripe PaymentIntent id in ' . __CLASS__ . '. ' . $e->getMessage(),
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
     * Atomically claim a cache row for paid finalization unless it is already paid.
     * Allows failed/canceled rows so a succeeded PaymentIntent can still be booked.
     *
     * A claim that never produced a response is taken over once it is older than
     * $staleAfterSeconds: the holder was killed mid-finalization (PHP timeout, OOM), and
     * without a takeover the row would keep reading as "paid" while no booking exists -
     * the money is captured and nothing ever retries.
     *
     * Only a claim this class stamped is taken over, which is what isPaidClaimAbandoned()
     * reports on: a paid row with no stamp predates the stamp or was written by another flow,
     * and re-running finalization on one of those could book a second time.
     *
     * @param int $id
     * @param int $staleAfterSeconds
     *
     * @return bool
     * @throws QueryExecutionException
     */
    public function claimForPaidFinalization($id, $staleAfterSeconds = self::PAID_CLAIM_STALE_SECONDS): bool
    {
        return $this->claimAsPaid(
            $id,
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.status')) <> 'paid'
                    OR (
                        JSON_EXTRACT(`data`, '$.response') IS NULL
                        AND JSON_EXTRACT(`data`, '$.paidClaimedAt') IS NOT NULL
                        AND CAST(
                            JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.paidClaimedAt')) AS UNSIGNED
                        ) < :staleBefore
                    )",
            'Unable to claim cache for paid finalization in ',
            [':staleBefore' => time() - max((int)$staleAfterSeconds, 0)]
        );
    }

    /**
     * Whether a row that reads as paid was left that way by a caller that died before it
     * produced a booking.
     *
     * Only a claim this class stamped counts: a paid row with no stamp predates the stamp or
     * was written by another flow, and re-running finalization on one of those could book a
     * second time for a payment that was already booked.
     *
     * @param array $cacheData
     * @param int   $staleAfterSeconds
     *
     * @return bool
     */
    public static function isPaidClaimAbandoned(
        array $cacheData,
        $staleAfterSeconds = self::PAID_CLAIM_STALE_SECONDS
    ): bool {
        if (!empty($cacheData['response'])) {
            return false;
        }

        if (empty($cacheData['paidClaimedAt'])) {
            return false;
        }

        return (time() - (int)$cacheData['paidClaimedAt']) >= max((int)$staleAfterSeconds, 0);
    }

    /**
     * Atomically flips a cache row's status to "paid" when it still matches the given
     * status predicate. Returns true only for the caller that performed the transition.
     *
     * The claim is stamped with the time it was taken, so a claim whose holder died can be
     * told apart from one that is still running - see claimForPaidFinalization().
     *
     * @param int    $id
     * @param string $statusPredicate SQL predicate the current status must satisfy
     * @param string $errorMessage
     * @param array  $extraParams     Additional bindings the predicate refers to
     *
     * @return bool
     * @throws QueryExecutionException
     */
    private function claimAsPaid($id, $statusPredicate, $errorMessage, array $extraParams = []): bool
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
                    'paid',
                    '$.paidClaimedAt',
                    :claimedAt
                )
                WHERE id = :id
                  AND (
                    NOT JSON_VALID(`data`)
                    OR JSON_EXTRACT(`data`, '$.status') IS NULL
                    OR {$statusPredicate}
                  )"
            );

            $statement->execute(
                array_merge(
                    [
                        ':id'        => $id,
                        ':claimedAt' => time(),
                    ],
                    $extraParams
                )
            );

            return $statement->rowCount() > 0;
        } catch (\Exception $e) {
            throw new QueryExecutionException(
                $errorMessage . __CLASS__ . '. ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
