<?php

namespace AmeliaBooking\Application\Services\WaitingList;

use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\Notification\ApplicationNotificationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventTicket;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class WaitingListService
 *
 * Waiting-list helpers for appointments and events (joinability, capacity,
 * and whether a booking should skip the regular slot availability check).
 */
class WaitingListService
{
    /** @var Container */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Notify waiting-list customers that a spot is available for the given appointment.
     *
     * Builds the waiting bookings collection from the appointment entity and
     * dispatches notifications through all enabled channels.
     *
     * @param Appointment $appointment
     *
     * @throws ContainerException
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     */
    public function sendAvailableSpotNotifications($appointment)
    {
        $waitingBookings = new Collection();

        foreach ($appointment->getBookings()->getItems() as $booking) {
            if ($booking->getStatus()->getValue() === BookingStatus::WAITING) {
                $waitingBookings->addItem($booking);
            }
        }

        if ($waitingBookings->length()) {
            /** @var ApplicationNotificationService $applicationNotificationService */
            $applicationNotificationService = $this->container->get('application.notification.service');

            $applicationNotificationService->sendWaitingListAvailableSpotNotifications(
                $appointment,
                $waitingBookings
            );
        }
    }

    /**
    * Determine if current booking qualifies as a waiting list booking.
     *
     * @param Service         $service
     * @param array           $appointmentData (expects bookingStart, serviceId, providerId, bookings array)
     * @param CustomerBooking $booking
     *
     * @return bool  True if booking is legitimate waiting list booking (skip slot check), false otherwise.
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     * @throws QueryExecutionException
     */
    public function isWaitingListBooking($service, $appointmentData, $booking)
    {
        if (empty($appointmentData['bookings'][0]['status']) || $appointmentData['bookings'][0]['status'] !== BookingStatus::WAITING) {
            return false;
        }

        // Extract waiting list settings from service settings JSON
        $rawSettings = null;
        if ($service->getSettings()) {
            $rawSettings = $service->getSettings()->getValue() ?? $service->getSettings();
        }

        $decoded = [];
        if (is_string($rawSettings)) {
            $decoded = json_decode($rawSettings, true) ?: [];
        } elseif (is_array($rawSettings)) {
            $decoded = $rawSettings;
        }

        $waitingSettings = $decoded['waitingList'] ?? [];
        $waitingEnabled = !empty($waitingSettings['enabled']);
        $waitingCapacity = isset($waitingSettings['maxCapacity']) ? (int)$waitingSettings['maxCapacity'] : 0;

        if (!$waitingEnabled || $waitingCapacity <= 0) {
            return false; // feature not enabled / no capacity defined
        }

        // Retrieve existing appointment(s) at same slot
        /** @var AppointmentRepository $appointmentRepo */
        $appointmentRepo = $this->container->get('domain.booking.appointment.repository');

        $existingAppointments = $appointmentRepo->getFiltered([
            'dates'         => [$appointmentData['bookingStart'], $appointmentData['bookingStart']],
            'services'      => [$appointmentData['serviceId']],
            'providers'     => [$appointmentData['providerId']],
            'skipServices'  => true,
            'skipProviders' => true,
            'skipCustomers' => true,
        ]);

        if (!$existingAppointments->length()) {
            return false;
        }

        $currentWaitingPersons = 0;
        foreach ($existingAppointments->getItems() as $existingAppointment) {
            foreach ($existingAppointment->getBookings()->getItems() as $existingBooking) {
                if ($existingBooking->getStatus()->getValue() === BookingStatus::WAITING) {
                    $currentWaitingPersons += $existingBooking->getPersons()->getValue();
                }
            }
        }

        $newPersons = $booking->getPersons() ? $booking->getPersons()->getValue() : 1;

        if ($currentWaitingPersons + $newPersons <= $waitingCapacity) {
            return true;
        }

        return false;
    }

    /**
     * Waiting-list joinability for an event (parity with frontend useWaitingListAvailability).
     *
     * Uses EventApplicationService::getEventInfo() occupancy fields (`full`, `closed`, `waiting`)
     * plus event waiting-list settings.
     *
     * @param Event      $event
     * @param array|null $info Result of getEventInfo(); computed when omitted.
     *
     * @return array{
     *     available: bool,
     *     maxCapacity: int,
     *     peopleWaiting: int,
     *     spotsLeft: int,
     *     maxExtraPeople: int|null
     * }|null Null when waiting list feature/settings are unavailable.
     *
     * @throws ContainerException
     * @throws ContainerValueNotFoundException
     * @throws InvalidArgumentException
     */
    public function getEventWaitingListAvailability($event, $info = null)
    {
        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');

        if (!$settingsDS->isFeatureEnabled('waitingList')) {
            return null;
        }

        $eventSettings = $event->getSettings() && $event->getSettings()->getValue()
            ? json_decode($event->getSettings()->getValue(), true)
            : null;

        if (!is_array($eventSettings) || empty($eventSettings['waitingList']['enabled'])) {
            return null;
        }

        if ($info === null) {
            /** @var EventApplicationService $eventApplicationService */
            $eventApplicationService = $this->container->get('application.booking.event.service');
            $info = $eventApplicationService->getEventInfo($event, true);
        }

        $waitingList = $eventSettings['waitingList'];
        $peopleWaiting = isset($info['waiting']) ? (int) $info['waiting'] : 0;
        $maxCapacity = isset($waitingList['maxCapacity']) ? (int) $waitingList['maxCapacity'] : 0;
        $maxExtraPeople = null;

        if (!empty($waitingList['maxExtraPeopleEnabled']) && isset($waitingList['maxExtraPeople'])) {
            $maxExtraPeople = (int) $waitingList['maxExtraPeople'];
        }

        $capacityRule = false;
        $waitingAlreadyStarted = 0;

        if ($event->getCustomPricing() && $event->getCustomPricing()->getValue()) {
            $capacityRulePerTicket = [];
            $maxCustomCapacity = $event->getMaxCustomCapacity() && $event->getMaxCustomCapacity()->getValue();
            $ticketWaitingCapacity = 0;

            /** @var EventTicket $ticket */
            foreach ($event->getCustomTickets()->getItems() as $ticket) {
                $waiting = $ticket->getWaiting() ? $ticket->getWaiting()->getValue() : 0;
                $waitingAlreadyStarted += $waiting;
                $waitingListSpots = $ticket->getWaitingListSpots() ? $ticket->getWaitingListSpots()->getValue() : 0;
                $ticketWaitingCapacity += $waitingListSpots;

                if (!$maxCustomCapacity) {
                    $capacityRulePerTicket[] = $waitingListSpots > $waiting;
                }
            }

            if (!$maxCustomCapacity) {
                $capacityRule = in_array(true, $capacityRulePerTicket, true);
                $maxCapacity = $ticketWaitingCapacity;
            } else {
                $capacityRule = $maxCapacity > $peopleWaiting;
            }
        } else {
            $capacityRule = $maxCapacity > $peopleWaiting;
            $waitingAlreadyStarted = $peopleWaiting;
        }

        $spotsLeft = max(0, $maxCapacity - $peopleWaiting);
        $available = empty($info['closed'])
            && $capacityRule
            && (!empty($info['full']) || $waitingAlreadyStarted !== 0);

        return [
            'available'      => $available,
            'maxCapacity'    => $maxCapacity,
            'peopleWaiting'  => $peopleWaiting,
            'spotsLeft'      => $spotsLeft,
            'maxExtraPeople' => $maxExtraPeople,
        ];
    }
}
