<?php

namespace AmeliaBooking\Application\Services\Calendar;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\Integration\ApplicationIntegrationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Factory\Booking\Event\EventFactory;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\BooleanValueObject;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventPeriodsRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentStatusUpdatedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentTimeUpdatedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventEditedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventStatusUpdatedEventHandler;
use Exception;
use Microsoft\Graph\Exception\GraphException;

/**
 * Class CalendarApplicationService
 *
 * @package AmeliaBooking\Application\Services\Calendar
 */
class CalendarApplicationService extends AbstractCalendarApplicationService
{
    /**
     * @param string $type Source of the change (e.g. googleCalendar) used for skip flags in integrations.
     *
     * @return string|null Key in command result data, e.g. ApplicationIntegrationService::SKIP_GOOGLE_CALENDAR
     */
    private function getSkipIntegrationKeyForCalendarSource($type)
    {
        switch ($type) {
            case 'googleCalendar':
                return ApplicationIntegrationService::SKIP_GOOGLE_CALENDAR;
            case 'outlookCalendar':
                return ApplicationIntegrationService::SKIP_OUTLOOK_CALENDAR;
            case 'appleCalendar':
                return ApplicationIntegrationService::SKIP_APPLE_CALENDAR;
            default:
                return null;
        }
    }

    /**
     * Drop the external calendar references from the appointment, because the event no longer exists in that calendar.
     *
     * @param Appointment $appointment
     * @param string      $type
     *
     * @return array Fields that have to be nulled in the appointments table
     */
    private function clearAppointmentCalendarFields($appointment, $type)
    {
        switch ($type) {
            case 'googleCalendar':
                $appointment->setGoogleCalendarEventId(null);
                $appointment->setGoogleMeetUrl(null);

                return [
                    'googleCalendarEventId' => null,
                    'googleMeetUrl'         => null,
                ];
            case 'outlookCalendar':
                $appointment->setOutlookCalendarEventId(null);
                $appointment->setMicrosoftTeamsUrl(null);

                return [
                    'outlookCalendarEventId' => null,
                    'microsoftTeamsUrl'      => null,
                ];
            case 'appleCalendar':
                $appointment->setAppleCalendarEventId(null);

                return [
                    'appleCalendarEventId' => null,
                ];
        }

        return [];
    }

    /**
     * Drop the external calendar references from the event period, because the event no longer exists in that calendar.
     *
     * @param EventPeriod $eventPeriod
     * @param string      $type
     *
     * @return array Fields that have to be nulled in the events periods table
     */
    private function clearEventPeriodCalendarFields($eventPeriod, $type)
    {
        switch ($type) {
            case 'googleCalendar':
                $eventPeriod->setGoogleCalendarEventId(null);
                $eventPeriod->setGoogleMeetUrl(null);

                return [
                    'googleCalendarEventId' => null,
                    'googleMeetUrl'         => null,
                ];
            case 'outlookCalendar':
                $eventPeriod->setOutlookCalendarEventId(null);
                $eventPeriod->setMicrosoftTeamsUrl(null);

                return [
                    'outlookCalendarEventId' => null,
                    'microsoftTeamsUrl'      => null,
                ];
            case 'appleCalendar':
                $eventPeriod->setAppleCalendarEventId(null);

                return [
                    'appleCalendarEventId' => null,
                ];
        }

        return [];
    }

    /**
     * Whether buffer times were applied when the event was written to the external calendar.
     *
     * @param string         $type
     * @param Appointment    $appointment
     * @param SettingsService $settingsService
     *
     * @return bool
     */
    private function getIncludeBufferForExternalCalendarChange($type, Appointment $appointment, SettingsService $settingsService)
    {
        if ($type === 'googleCalendar' || $type === 'outlookCalendar') {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');
            $provider = $providerRepository->getById($appointment->getProviderId()->getValue());

            if ($type === 'googleCalendar' && $provider->getGoogleCalendar()) {
                return $provider->getGoogleCalendar()->getIncludeBufferTime();
            }

            if ($type === 'outlookCalendar' && $provider->getOutlookCalendar()) {
                return $provider->getOutlookCalendar()->getIncludeBufferTime();
            }
        }

        return (bool)$settingsService->getSetting($type, 'includeBufferTime' . ucfirst($type));
    }

    /**
     * @param string $id
     * @param string $calendarField
     *
     * @return Appointment|null
     *
     * @throws Exception
     */
    public function getChangedAppointment($id, $calendarField)
    {
        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        /** @var Collection $appointments */
        $appointments = $appointmentRepository->getByFieldValue(
            $calendarField,
            $id
        );

        if ($appointments->length()) {
            return $appointments->getItem($appointments->keys()[0]);
        }

        if ($calendarField === 'googleCalendarEventId') {
            return $appointmentRepository->getByGoogleCalendarEventId($id);
        }

        return null;
    }

    /**
     * Graph / Google still notify after Amelia has already canceled or rejected the
     * appointment (the write echoing back). Those must not look like a new change.
     *
     * @param Appointment $appointment
     *
     * @return bool
     */
    private function isAppointmentCanceledOrRejected(Appointment $appointment): bool
    {
        if (!$appointment->getStatus()) {
            return false;
        }

        $status = $appointment->getStatus()->getValue();

        return $status === BookingStatus::CANCELED || $status === BookingStatus::REJECTED;
    }

    /**
     * @param Appointment $appointment
     * @param string      $startString
     * @param string      $endString
     * @param string      $type
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function handleAppointmentReschedule($appointment, $startString, $endString, $type): void
    {
        if ($this->isAppointmentCanceledOrRejected($appointment)) {
            return;
        }

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        /** @var ServiceRepository $serviceRepository */
        $serviceRepository = $this->container->get('domain.bookable.service.repository');

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $haveBuffer = $this->getIncludeBufferForExternalCalendarChange($type, $appointment, $settingsService);

        $bookingStart = clone $appointment->getBookingStart()->getValue();

        $bookingEnd = clone $appointment->getBookingEnd()->getValue();

        $bufferTimeBefore = 0;

        $bufferTimeAfter = 0;

        if ($haveBuffer) {
            /** @var Service $service */
            $service = $serviceRepository->getById($appointment->getServiceId()->getValue());

            $bufferTimeBefore = $service->getTimeBefore() ? $service->getTimeBefore()->getValue() : 0;

            $bufferTimeAfter = $service->getTimeAfter() ? $service->getTimeAfter()->getValue() : 0;
        }

        $bookingStart->modify('-' . $bufferTimeBefore . ' seconds');

        $bookingEnd->modify('+' . $bufferTimeAfter . ' seconds');

        if (
            $bookingStart->format('Y-m-d H:i:s') !== $startString ||
            $bookingEnd->format('Y-m-d H:i:s') !== $endString
        ) {
            $initialBookingStart = $appointment->getBookingStart()->getValue();

            $initialBookingEnd = $appointment->getBookingEnd()->getValue();

            $appointment->setBookingStart(new DateTimeValue(DateTimeService::getCustomDateTimeObject($startString)));

            $appointment->setBookingEnd(new DateTimeValue(DateTimeService::getCustomDateTimeObject($endString)));

            // modify booking start time and end time if buffer time is set
            if ($haveBuffer) {
                $appointment->getBookingStart()->getValue()->modify('+' . $bufferTimeBefore . ' seconds');

                $appointment->getBookingEnd()->getValue()->modify('-' . $bufferTimeAfter . ' seconds');

                // set old duration because in case possible negative appointment length
                if ($appointment->getBookingStart()->getValue() >= $appointment->getBookingEnd()->getValue()) {
                    $interval = $initialBookingStart->diff($initialBookingEnd);

                    $duration = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

                    $newBookingStart = clone $appointment->getBookingStart()->getValue();

                    $appointment->setBookingEnd(
                        new DateTimeValue($newBookingStart->modify('+' . $duration . ' minutes'))
                    );
                }
            }

            $appointmentRepository->updateFieldsById(
                $appointment->getId()->getValue(),
                [
                    'bookingStart' => (clone $appointment->getBookingStart()->getValue())
                        ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'bookingEnd'   => (clone $appointment->getBookingEnd()->getValue())
                        ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ]
            );

            // The notification reads the previous time off the entity, and the handler downstream rebuilds
            // the appointment from its array alone, so the sibling key below never reaches the placeholders.
            $appointment->setInitialBookingStart(new DateTimeValue($initialBookingStart));

            $appointment->setInitialBookingEnd(new DateTimeValue($initialBookingEnd));

            $result = new CommandResult();

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully updated appointment time');
            $skipData = [];
            $skipKey = $this->getSkipIntegrationKeyForCalendarSource($type);
            if ($skipKey !== null) {
                $skipData[$skipKey] = true;
            }

            $result->setData(
                array_merge(
                    [
                        Entities::APPOINTMENT        => $appointment->toArray(),
                        'initialAppointmentDateTime' => [
                            'bookingStart' => $initialBookingStart->format('Y-m-d H:i:s'),
                            'bookingEnd'   => $initialBookingEnd->format('Y-m-d H:i:s'),
                        ],
                    ],
                    $skipData
                )
            );

            AppointmentTimeUpdatedEventHandler::handle($result, $this->container);
        }
    }

    /**
     * @param Appointment $appointment
     * @param string      $type
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function handleAppointmentReject($appointment, $type): void
    {
        if ($this->isAppointmentCanceledOrRejected($appointment)) {
            return;
        }

        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');

        $oldAppointmentArray = $appointment->toArray();

        $oldStatus = $appointment->getStatus()->getValue();

        $appointment->setStatus(new BookingStatus(BookingStatus::REJECTED));

        $bookingIdsToReject = [];

        /** @var CustomerBooking $booking */
        foreach ($appointment->getBookings()->getItems() as $booking) {
            if (!$bookingAS->isBookingCanceledOrRejectedOrNoShow($booking->getStatus()->getValue())) {
                $booking->setStatus(new BookingStatus(BookingStatus::REJECTED));

                $booking->setChangedStatus(new BooleanValueObject(true));

                if ($booking->getId()) {
                    $bookingIdsToReject[] = (int)$booking->getId()->getValue();
                }
            }
        }

        if ($bookingIdsToReject !== []) {
            $bookingRepository->updateFieldByIds(
                $bookingIdsToReject,
                BookingStatus::REJECTED,
                'status'
            );
        }

        $fieldsForUpdate = array_merge(
            [
                'status' => BookingStatus::REJECTED,
            ],
            $this->clearAppointmentCalendarFields($appointment, $type)
        );

        $appointmentRepository->updateFieldsById($appointment->getId()->getValue(), $fieldsForUpdate);

        $appointmentArray = $appointment->toArray();

        $bookingsWithChangedStatus = $bookingAS->getBookingsWithChangedStatus($appointmentArray, $oldAppointmentArray);

        $result = new CommandResult();

        $skipData = [];
        $skipKey = $this->getSkipIntegrationKeyForCalendarSource($type);
        if ($skipKey !== null) {
            $skipData[$skipKey] = true;
        }

        $result->setData(
            array_merge(
                [
                    Entities::APPOINTMENT       => $appointmentArray,
                    'bookingsWithChangedStatus' => $bookingsWithChangedStatus,
                    'status'                    => BookingStatus::REJECTED,
                    'oldStatus'                 => $oldStatus,
                ],
                $skipData
            )
        );

        AppointmentStatusUpdatedEventHandler::handle($result, $this->container);
    }

    /**
     * @param string $id
     * @param string $calendarField
     *
     * @return EventPeriod|null
     *
     * @throws Exception
     */
    public function getChangedEventPeriod($id, $calendarField)
    {
        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

        /** @var Collection $eventsPeriods */
        $eventsPeriods = $eventPeriodsRepository->getByFieldValue(
            $calendarField,
            $id
        );

        return $eventsPeriods->length()
            ? $eventsPeriods->getItem($eventsPeriods->keys()[0])
            : null;
    }

    /**
     * @param EventPeriod $eventPeriod
     *
     * @return Event
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function getChangedEvent($eventPeriod)
    {
        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        return $eventApplicationService->getEventById(
            $eventPeriod->getEventId()->getValue(),
            [
                'fetchEventsPeriods'    => true,
                'fetchEventsTickets'    => true,
                'fetchEventsTags'       => true,
                'fetchEventsProviders'  => true,
                'fetchEventsImages'     => true,
                'fetchBookings'         => true,
                'fetchBookingsTickets'  => true,
                'fetchBookingsUsers'    => true,
                'fetchBookingsPayments' => true,
                'fetchBookingsCoupons'  => true,
            ]
        );
    }

    /**
     * @param Event       $event
     * @param EventPeriod $eventPeriod
     * @param string      $type
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws GraphException
     */
    public function handleEventPeriodDelete($event, $eventPeriod, $type)
    {
        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');

        if ($event->getPeriods()->length() === 1) {
            /** @var SettingsService $settingsService */
            $settingsService = $this->container->get('domain.settings.service');

            /** @var Collection $updatedEvents */
            $updatedEvents = new Collection();

            // Deleting the event in the external calendar leaves the Amelia event alone unless the
            // calendar is configured otherwise, because its bookings do not belong to the organizer.
            if ($settingsService->getSetting($type, 'updateEventStatusOnDelete', false)) {
                $events = new Collection();
                $events->addItem($event, $event->getId()->getValue());

                $updatedEvents = $eventApplicationService->updateStatus(
                    $events,
                    BookingStatus::REJECTED,
                    false
                );
            }

            $fieldsForUpdate = $this->clearEventPeriodCalendarFields($eventPeriod, $type);

            $eventPeriodsRepository->updateFieldsById($eventPeriod->getId()->getValue(), $fieldsForUpdate);

            $eventRepository->updateFieldById($event->getId()->getValue(), null, 'organizerId');

            /** @var Event $updatedEvent */
            foreach ($updatedEvents->getItems() as $updatedEvent) {
                $updatedEvent->setOrganizerId(null);

                /** @var EventPeriod $updatedEventPeriod */
                foreach ($updatedEvent->getPeriods()->getItems() as $updatedEventPeriod) {
                    if ($updatedEventPeriod->getId()->getValue() === $eventPeriod->getId()->getValue()) {
                        $this->clearEventPeriodCalendarFields($updatedEventPeriod, $type);
                    }
                }
            }

            if ($updatedEvents->length()) {
                $result = new CommandResult();

                $skipData = [];
                $skipKey = $this->getSkipIntegrationKeyForCalendarSource($type);
                if ($skipKey !== null) {
                    $skipData[$skipKey] = true;
                }

                $result->setData(
                    array_merge(
                        [
                            'status'         => BookingStatus::REJECTED,
                            Entities::EVENTS => $updatedEvents->toArray(),
                        ],
                        $skipData
                    )
                );

                EventStatusUpdatedEventHandler::handle($result, $this->container);
            }
        } else {
            /** @var Event $newEvent */
            $newEvent = EventFactory::create($event->toArray());

            $newEvent->getPeriods()->deleteItem($eventPeriod->getId()->getValue());

            $this->handleEventUpdate($event, $newEvent, $type);
        }
    }

    /**
     * @param Event       $event
     * @param EventPeriod $eventPeriod
     * @param string      $startString
     * @param string      $endString
     * @param string      $type
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function handleEventPeriodReschedule($event, $eventPeriod, $startString, $endString, $type)
    {
        if (
            $eventPeriod->getPeriodStart()->getValue()->format('Y-m-d H:i:s') !== $startString ||
            $eventPeriod->getPeriodEnd()->getValue()->format('Y-m-d H:i:s') !== $endString
        ) {
            /** @var Event $newEvent */
            $newEvent = EventFactory::create($event->toArray());

            foreach ($newEvent->getPeriods()->getItems() as $period) {
                if ($period->getId()->getValue() === $eventPeriod->getId()->getValue()) {
                    $period->setPeriodStart(new DateTimeValue(DateTimeService::getCustomDateTimeObject($startString)));

                    $period->setPeriodEnd(new DateTimeValue(DateTimeService::getCustomDateTimeObject($endString)));
                }
            }

            $this->handleEventUpdate($event, $newEvent, $type);
        }
    }

    /**
     * @param Event  $event
     * @param Event  $newEvent
     * @param string $type
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    private function handleEventUpdate($event, $newEvent, $type)
    {
        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        $parsedEvents = $eventApplicationService->update(
            $event,
            $newEvent,
            false
        );

        $result = new CommandResult();

        $skipData = [];
        $skipKey = $this->getSkipIntegrationKeyForCalendarSource($type);
        if ($skipKey !== null) {
            $skipData[$skipKey] = true;
        }

        $result->setData(
            array_merge(
                [
                    Entities::EVENTS        => $parsedEvents,
                    'zoomUserChanged'       => null,
                    'zoomUsersLicenced'     => false,
                    'newInfo'               => null,
                    'newProviders'          => [],
                    'removeProviders'       => [],
                    'organizerChanged'      => false,
                    'newOrganizer'          => null,
                    'notifyParticipants'    => $event->isNotifyParticipants(),
                ],
                $skipData
            )
        );

        EventEditedEventHandler::handle($result, $this->container);
    }
}
