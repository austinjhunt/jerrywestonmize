<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Repository\Calendar;

use AmeliaBooking\Domain\Services\Database\ConnectionInterface;

/**
 * Class CalendarSyncLockRepository
 *
 * Mutual exclusion for external calendar sync. Google push notifications and Graph change notifications
 * are both delivered at least once, so the same change can arrive twice at once. Without a lock both
 * copies read the pre-change booking time, both pass the "did anything actually change" comparison in
 * CalendarApplicationService, and both reschedule the appointment and notify the customer.
 *
 * Built on the database's own advisory locks rather than on a lock table. A table needs a timeout to
 * recover locks left behind by a request that died, and a timeout cannot tell a dead request from a slow
 * one: expire too early and two deliveries run side by side, which is the very thing being prevented;
 * expire too late and a calendar is wedged. Nor is there a safe value to pick, because
 * max_execution_time is routinely unlimited for cron and CLI, so a legitimate run has no upper bound.
 *
 * An advisory lock is owned by the database session, so it is released the moment the connection goes -
 * whether the request finished, timed out or crashed - and never expires while its holder is alive.
 *
 * @package AmeliaBooking\Infrastructure\Repository\Calendar
 */
class CalendarSyncLockRepository
{
    /**
     * The lock was taken; the caller must release it when done.
     */
    public const RESULT_ACQUIRED = 'acquired';

    /**
     * Another request holds the lock and is working on the same change right now. The caller should stand
     * down rather than run alongside it.
     */
    public const RESULT_CONTENDED = 'contended';

    /**
     * Locking could not be evaluated at all - advisory locks unavailable, query failed. The caller should
     * carry on unprotected: losing duplicate suppression is a lesser fault than stopping sync entirely.
     */
    public const RESULT_UNAVAILABLE = 'unavailable';

    /**
     * How long to wait for a lock held by another request before giving up.
     */
    public const DEFAULT_WAIT_SECONDS = 5;

    /**
     * Lock names are capped at 64 characters, and the prefix keeps them recognisable while debugging.
     */
    private const NAME_PREFIX = 'amelia:';

    /** @var ConnectionInterface */
    private $connection;

    /** @var array<string, bool> lock names this instance currently holds */
    private $held = [];

    /**
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Try to take the lock, waiting for a holder to release it.
     *
     * Never throws.
     *
     * @param string $key
     * @param int    $waitSeconds
     *
     * @return string one of the RESULT_* constants
     */
    public function acquire($key, $waitSeconds = self::DEFAULT_WAIT_SECONDS): string
    {
        $name = $this->lockName($key);

        // MySQL < 5.7.5 and MariaDB < 10.0.2 keep only one GET_LOCK per session: taking a second
        // name silently releases the first. Amelia's floor is WordPress 6.5 (MySQL 5.5.5 / MariaDB
        // 5.5), so those servers remain in play. Refuse another name rather than record a lock the
        // session no longer holds; the caller already treats UNAVAILABLE as "run without a lock".
        if ($this->held !== [] && !isset($this->held[$name])) {
            return self::RESULT_UNAVAILABLE;
        }

        try {
            $statement = $this->connection->prepare('SELECT GET_LOCK(:name, :timeout) AS acquired');

            $statement->execute(
                [
                    ':name'    => $name,
                    ':timeout' => (int)$waitSeconds,
                ]
            );

            $row = $statement->fetch();
        } catch (\Throwable $e) {
            return self::RESULT_UNAVAILABLE;
        }

        // 1 taken, 0 timed out while somebody else held it, NULL an error such as advisory locks not
        // being supported on this server.
        if (!$row || !array_key_exists('acquired', $row) || $row['acquired'] === null) {
            return self::RESULT_UNAVAILABLE;
        }

        if ((int)$row['acquired'] !== 1) {
            return self::RESULT_CONTENDED;
        }

        $this->held[$name] = true;

        return self::RESULT_ACQUIRED;
    }

    /**
     * @param string $key
     *
     * @return void
     */
    public function release($key): void
    {
        $name = $this->lockName($key);

        // Only what this instance took: releasing by name alone would let a caller drop a lock that
        // another request is holding.
        if (!isset($this->held[$name])) {
            return;
        }

        unset($this->held[$name]);

        try {
            $statement = $this->connection->prepare('SELECT RELEASE_LOCK(:name) AS released');

            $statement->execute([':name' => $name]);
        } catch (\Throwable $e) {
            // The session drops the lock when the connection closes, so this is not worth failing over.
        }
    }

    /**
     * Hashing keeps the name inside the 64 character limit whatever the caller's key looks like.
     *
     * @param string $key
     *
     * @return string
     */
    private function lockName($key): string
    {
        return self::NAME_PREFIX . substr(hash('sha256', (string)$key), 0, 56);
    }
}
