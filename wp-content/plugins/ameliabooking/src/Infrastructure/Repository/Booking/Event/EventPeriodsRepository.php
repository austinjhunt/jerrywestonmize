<?php

namespace AmeliaBooking\Infrastructure\Repository\Booking\Event;

use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Factory\Booking\Event\EventPeriodFactory;
use AmeliaBooking\Domain\Repository\Booking\Event\EventRepositoryInterface;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\AbstractRepository;

/**
 * Class EventPeriodsRepository
 *
 * @package AmeliaBooking\Infrastructure\Repository\Booking\Event
 */
class EventPeriodsRepository extends AbstractRepository implements EventRepositoryInterface
{
    public const FACTORY = EventPeriodFactory::class;

    /**
     * @param EventPeriod $entity
     *
     * @return int
     * @throws QueryExecutionException
     */
    public function add($entity)
    {
        $data = $entity->toArray();

        $params = [
            ':eventId'        => $data['eventId'],
            ':periodStart'    => DateTimeService::getCustomDateTimeInUtc($data['periodStart']),
            ':periodEnd'      => DateTimeService::getCustomDateTimeInUtc($data['periodEnd']),
        ];

        try {
            $statement = $this->connection->prepare(
                "INSERT INTO {$this->table} 
                (
                `eventId`,
                `periodStart`,
                `periodEnd`
                )
                VALUES (
                :eventId,
                :periodStart,
                :periodEnd
                )"
            );

            $statement->execute($params);

            return $this->connection->lastInsertId();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to add data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int         $id
     * @param EventPeriod $entity
     *
     * @return mixed
     * @throws QueryExecutionException
     */
    public function update($id, $entity)
    {
        $data = $entity->toArray();

        $params = [
            ':id'             => $id,
            ':periodStart'    => DateTimeService::getCustomDateTimeInUtc($data['periodStart']),
            ':periodEnd'      => DateTimeService::getCustomDateTimeInUtc($data['periodEnd'])
        ];

        try {
            $statement = $this->connection->prepare(
                "UPDATE {$this->table}
                SET
                `periodStart` = :periodStart,
                `periodEnd` = :periodEnd
                WHERE id = :id"
            );

            $statement->execute($params);

            return true;
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to save data in ' . __CLASS__ . '. ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return Collection
     * @throws QueryExecutionException
     */
    public function getByFieldValue($field, $value)
    {
        if (
            !in_array(
                $field,
                [
                  'googleCalendarEventId',
                  'outlookCalendarEventId',
                ]
            )
        ) {
            throw new QueryExecutionException("Invalid field $field for " . __CLASS__);
        }

        $params = [
            ":$field" => $value,
        ];

        try {
            $statement = $this->connection->prepare(
                "SELECT * FROM {$this->table} WHERE {$field} = :{$field}"
            );

            $statement->execute($params);

            $rows = $statement->fetchAll();
        } catch (\Exception $e) {
            throw new QueryExecutionException('Unable to find by field value in ' . __CLASS__, $e->getCode(), $e);
        }

        $collection = new Collection();

        foreach ($rows as $row) {
            $row['periodStart'] = DateTimeService::getCustomDateTimeFromUtc(
                $row['periodStart']
            );

            $row['periodEnd'] = DateTimeService::getCustomDateTimeFromUtc(
                $row['periodEnd']
            );

            $collection->addItem(
                call_user_func([static::FACTORY, 'create'], $row),
                $row['id']
            );
        }

        return $collection;
    }
}
