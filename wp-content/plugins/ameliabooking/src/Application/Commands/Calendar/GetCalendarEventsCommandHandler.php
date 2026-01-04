<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Calendar;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;

class GetCalendarEventsCommandHandler extends CommandHandler
{
    /**
     * @param GetCalendarEventsCommand $command
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     */
    public function handle(GetCalendarEventsCommand $command): CommandResult
    {
        $result      = new CommandResult();
        $queryParams = $command->getField('queryParams');

        if (
            !$command->getPermissionService()->currentUserCanRead(Entities::APPOINTMENTS) &&
            !$command->getPermissionService()->currentUserCanRead(Entities::EVENTS)
        ) {
            throw new AccessDeniedException('You are not allowed to read calendar events.');
        }

        /** @var AbstractUser $user */
        $user = $this->container->get('logged.in.user');

        if ($user->getType() === Entities::CUSTOMER) {
            $queryParams['customers'] = [$user->getId()->getValue()];
        }

        if ($user->getType() === Entities::PROVIDER) {
            $queryParams['providers'] = [$user->getId()->getValue()];
        }

        $appointments = $this->getAppointments($queryParams);
        $events       = $this->getEvents($queryParams);
        $sortedItems  = array_merge($appointments, $events);

        usort(
            $sortedItems,
            function ($a, $b) {
                $startA = $a instanceof Appointment ? $a->getBookingStart()->getValue() : $a['eventPeriod']->getPeriodStart()->getValue();
                $startB = $b instanceof Appointment ? $b->getBookingStart()->getValue() : $b['eventPeriod']->getPeriodStart()->getValue();
                return $startA <=> $startB;
            }
        );

        $filledDays        = [];
        $maxNumberOfEvents = PHP_INT_MAX;

        if ($queryParams['view'] === 'dayGridMonth') {
            $maxNumberOfEvents = 4;
        }

        if (in_array($queryParams['view'], ['dayGridMonthSevenDays', 'dayGridMonthMobile'])) {
            $maxNumberOfEvents = 2;
        }

        foreach ($sortedItems as $item) {
            $itemStartDate = $item instanceof Appointment
                ? $item->getBookingStart()->getValue()->format('Y-m-d')
                : $item['eventPeriod']->getPeriodStart()->getValue()->format('Y-m-d');

            if (!isset($filledDays[$itemStartDate])) {
                $filledDays[$itemStartDate] = ['events' => [], 'count' => 0, 'more' => 0];
            }

            // Add more button items number
            if ($filledDays[$itemStartDate]['count'] >= $maxNumberOfEvents) {
                $filledDays[$itemStartDate]['more']++;
                $this->processEventDates($filledDays, $item, 'more');

                continue;
            }

            $filledDays[$itemStartDate]['events'][] = $item instanceof Appointment
                ? $this->appointmentFormatter($item, $queryParams, $user)
                : $this->eventFormatter($item['event'], $item['eventPeriod'], $queryParams);
            $filledDays[$itemStartDate]['count']++;
            $this->processEventDates($filledDays, $item, 'count');
        }

        $result->setData(['events' => $filledDays]);
        return $result;
    }

    /**
     * @param array $filledDays
     * @param array|Appointment $item
     * @param string $counterKey
     * @return void
     */
    private function processEventDates(array &$filledDays, $item, string $counterKey): void
    {
        if (!$item instanceof Appointment) {
            $eventStartDate = $item['eventPeriod']->getPeriodStart()->getValue()->setTime(0, 0, 0);
            $eventEndDate   = $item['eventPeriod']->getPeriodEnd()->getValue()->setTime(23, 59, 59);

            for ($date = (clone $eventStartDate)->modify('+1 day'); $date <= $eventEndDate; $date->modify('+1 day')) {
                $formattedDate = $date->format('Y-m-d');
                if (!isset($filledDays[$formattedDate])) {
                    $filledDays[$formattedDate] = ['events' => [], 'count' => 0, 'more' => 0];
                }
                $filledDays[$formattedDate][$counterKey]++;
            }
        }
    }

    /**
     * @throws QueryExecutionException
     */
    private function getAppointments(array $queryParams): array
    {
        if (!isset($queryParams['entitiesToShow']) || !in_array('appointments', $queryParams['entitiesToShow']) || !empty($queryParams['events'])) {
            return [];
        }

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        $queryParams['statuses'] =
            isset($queryParams['statuses']) && in_array('pendingAppointments', $queryParams['statuses'])
            ? [BookingStatus::APPROVED, BookingStatus::PENDING]
            : [BookingStatus::APPROVED];

        $queryParams['dates']    = [$queryParams['calendarStartDate'], $queryParams['calendarEndDate']];

        $appointments = $appointmentRepository->getFiltered($queryParams);

        return $appointments->getItems();
    }

    private function getEvents(array $queryParams): array
    {
        if (!isset($queryParams['entitiesToShow']) || !in_array('events', $queryParams['entitiesToShow']) || !empty($queryParams['services'])) {
            return [];
        }

        $eventPeriods = [];

        /** @var EventApplicationService $eventAS */
        $eventAS = $this->container->get('application.booking.event.service');

        $queryParams['dates'] = [$queryParams['calendarStartDate'], $queryParams['calendarEndDate']];

        if (!empty($queryParams['events'])) {
            $queryParams['id'] = $queryParams['events'];
        }

        $events = $eventAS->getEventsByCriteria($queryParams, ['fetchEventsPeriods' => true], -1);

        /** @var Event $event */
        foreach ($events->getItems() as $event) {
            /** @var EventPeriod $eventPeriod */
            foreach ($event->getPeriods()->getItems() as $eventPeriod) {
                $eventPeriods[] = ['event' => $event, 'eventPeriod' => $eventPeriod];
            }
        }

        return $eventPeriods;
    }

    private function appointmentFormatter(Appointment $appointment, array $queryParams, AbstractUser $user): array
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');
        $timeSlotStep    = $settingsService->getSetting('general', 'timeSlotLength');
        $appointmentDurationInSeconds = $appointment->getBookingEnd()->getValue()->getTimestamp() -
            $appointment->getBookingEnd()->getValue()->getTimestamp();
        $bufferTimeBefore = $appointment->getService() && $appointment->getService()->getTimeBefore()
            ? $appointment->getService()->getTimeBefore()->getValue()
            : 0;
        $bufferTimeAfter  = $appointment->getService() && $appointment->getService()->getTimeAfter()
            ? $appointment->getService()->getTimeAfter()->getValue()
            : 0;

        $startWithoutBuffer = $appointment->getBookingStart()->getValue();
        $start = (clone($startWithoutBuffer))->modify('-' . $bufferTimeBefore . 'seconds');

        $endWithoutBuffer = $appointment->getBookingEnd()->getValue();
        $end = (clone($endWithoutBuffer))->modify($bufferTimeAfter . 'seconds');

        $title = $appointment->getService()->getName()->getValue();
        if (!empty($queryParams['showEmployeeName'])) {
            $title = $appointment->getProvider()->getFullName();
        }

        return [
            'uuid'                    => $appointment->getId()->getValue(),
            'id'                      => $appointment->getId()->getValue(),
            'bookings'                => $appointment->getBookings()->toArray(),
            'title'                   => $title,
            'start'                   => $start->format('Y-m-d H:i:s'),
            'end'                     => $end->format('Y-m-d H:i:s'),
            'startWithoutBuffer'      => $startWithoutBuffer->format('Y-m-d H:i:s'),
            'endWithoutBuffer'        => $endWithoutBuffer->format('Y-m-d H:i:s'),
            'mainColor'               => $appointment->getService()->getColor()->getValue(),
            'numberOfSlots'           => $appointmentDurationInSeconds / $timeSlotStep,
            'serviceId'               => $appointment->getService()->getId()->getValue(),
            'employeeId'              => $appointment->getProvider()->getId()->getValue(),
            'locationId'              => $appointment->getLocationId() ?: null,
            'bufferTimeBefore'        => $bufferTimeBefore,
            'bufferTimeAfter'         => $bufferTimeAfter,
            'notes'                   => $appointment->getInternalNotes()->getValue(),
            'integrationCalendarType' => false,
            'type'                    => $appointment->getBookings()->length() === 1
                ? 'singleAppointment'
                : 'groupAppointment',
            'editable'                => $user->getType() === Entities::CUSTOMER
                ? $settingsService->getSetting('roles', 'allowCustomerReschedule')
                : (
                    $user->getType() === Entities::PROVIDER ?
                        $settingsService->getSetting('roles', 'allowWriteAppointments')
                        : true
                ),
        ];
    }

    private function eventFormatter(Event $eventEntity, EventPeriod $eventPeriod, array $queryParams): array
    {
        $periodStartDate = clone $eventPeriod->getPeriodStart()->getValue();
        $periodEndDate   = clone $eventPeriod->getPeriodEnd()->getValue();

        $title = $eventEntity->getName()->getValue();
        if (!empty($queryParams['showEmployeeName'])) {
            $title = $eventEntity->getOrganizer() ? $eventEntity->getOrganizer()->getFullName() : $title;
        }

        $event = [
            'uuid'               => $eventEntity->getId()->getValue(),
            'title'              => $title,
            'mainColor'          => $eventEntity->getColor() ? $eventEntity->getColor()->getValue() : '#1788FB',
            'type'               => 'event',
            'editable'           => false,
            'startWithoutBuffer' => $periodStartDate->format('Y-m-d H:i:s'),
            'endWithoutBuffer'   => $periodEndDate->format('Y-m-d H:i:s'),
            'notes'              => '',
        ];

        if (in_array($queryParams['view'], ['dayGridMonthSevenDays', 'dayGridMonth', 'dayGridMonthMobile'])) {
            $event['start'] = $periodStartDate->format('Y-m-d');
            $event['end']   = $periodEndDate->modify('+1 day')->format('Y-m-d');
        } else {
            $event['groupId']    = $eventPeriod->getId()->getValue();
            $event['startRecur'] = $periodStartDate->format('Y-m-d');
            $event['endRecur']   = $periodEndDate->modify('+1 day')->format('Y-m-d');
            $event['startTime']  = $periodStartDate->format('H:i:s');
            $event['endTime']    = $periodEndDate->format('H:i:s') === '00:00:00'
                ? '23:59:59'
                : $periodEndDate->format('H:i:s');
        }

        return $event;
    }
}
