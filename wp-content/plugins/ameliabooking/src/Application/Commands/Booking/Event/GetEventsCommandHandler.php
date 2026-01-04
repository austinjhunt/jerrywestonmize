<?php

namespace AmeliaBooking\Application\Commands\Booking\Event;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Booking\Event\EventPeriodFactory;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use DateTimeZone;
use Exception;

/**
 * Class GetEventsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Event
 */
class GetEventsCommandHandler extends CommandHandler
{
    /**
     * @param GetEventsCommand $command
     *
     * @return CommandResult
     *
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function handle(GetEventsCommand $command)
    {
        $result = new CommandResult();

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->container->get('domain.booking.event.repository');
        /** @var UserApplicationService $userAS */
        $userAS = $this->container->get('application.user.service');
        /** @var EventApplicationService $eventAS */
        $eventAS = $this->container->get('application.booking.event.service');
        /** @var ProviderApplicationService $providerAS */
        $providerAS = $this->container->get('application.user.provider.service');


        $params = $command->getField('params');

        /** @var AbstractUser|null $user */
        $user = null;

        $isFrontEnd = isset($params['page']) && empty($params['group']);

        $isCalendarPage = $isFrontEnd && (int)$params['page'] === 0;

        $isCabinetPage = $command->getPage() === 'cabinet';

        if (!$isFrontEnd) {
            try {
                /** @var AbstractUser $user */
                $user = $command->getUserApplicationService()->authorization(
                    $isCabinetPage ? $command->getToken() : null,
                    $command->getCabinetType()
                );
            } catch (AuthorizationException $e) {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setData(
                    [
                        'reauthorize' => true
                    ]
                );

                return $result;
            }

            if ($userAS->isAmeliaUser($user) && $userAS->isCustomer($user)) {
                $params['customerId'] = $user->getId()->getValue();
            }

            if ($user && $user->getType() === AbstractUser::USER_ROLE_PROVIDER) {
                $params['providers'] = [$user->getId()->getValue()];
            }
        }

        if ($isFrontEnd && !empty($params['providers'])) {
            $params['providers'] = array_values($params['providers']);
        }

        if (isset($params['dates'][0])) {
            $params['dates'][0] ? $params['dates'][0] .= ' 00:00:00' : null;
        }

        if (isset($params['dates'][1])) {
            $params['dates'][1] ? $params['dates'][1] .= ' 23:59:59' : null;
        }

        if ($isFrontEnd) {
            $params['show'] = 1;

            if (!empty($params['tag'])) {
                $params['tag'] = str_replace('___', ' ', $params['tag']);
            }
        }

        $criteria = [
            'fetchEventsPeriods'    => true,
            'fetchEventsTickets'    => true,
            'fetchEventsTags'       => $isFrontEnd,
            'fetchEventsProviders'  => true,
            'fetchEventsOrganizer'  => true,
            'fetchEventsImages'     => true,
            'fetchBookings'         => true,
            'fetchBookingsTickets'  => true,
            'fetchBookingsCoupons'  => $isCabinetPage,
            'fetchBookingsPayments' => $isCabinetPage,
            'fetchBookingsUsers'    => $isCabinetPage,
        ];

        /** @var Collection $events */
        $events = $eventAS->getEventsByCriteria(
            $params,
            $criteria,
            !empty($params['limit'])
                ? $params['limit']
                : ($isFrontEnd ? $settingsDS->getSetting('general', 'itemsPerPage') : 10)
        );

        $popupEventId = !empty($params['idPopup']) ? $params['idPopup'] : null;

        /** @var Collection $requestedEvents */
        $requestedEvents = $popupEventId && !$events->keyExists($popupEventId) ? $eventAS->getEventsByCriteria(
            array_merge($params, ['id' => [$popupEventId]]),
            $criteria,
            1
        ) : new Collection();

        foreach ($requestedEvents->getItems() as $event) {
            $events->placeItem($event, $event->getId()->getValue(), true);
        }

        $eventsArray = [];

        $customersNoShowCountIds = [];

        $noShowTagEnabled = $settingsDS->isFeatureEnabled('noShowTag');

        /** @var Event $event */
        foreach ($events->getItems() as $event) {
            // this would affect paging on frontend, should be done in the database?
            if ($isFrontEnd && !$event->getShow()->getValue()) {
                continue;
            }

            if (
                ($isFrontEnd && $settingsDS->getSetting('general', 'showClientTimeZone')) ||
                $isCabinetPage || ($user && $user->getType() === AbstractUser::USER_ROLE_PROVIDER)
            ) {
                $timeZone = 'UTC';

                if (!empty($params['timeZone'])) {
                    $timeZone = $params['timeZone'];
                } elseif (
                    $user instanceof Provider &&
                    empty($user->getTimeZone()) && !empty(get_option('timezone_string'))
                ) {
                    $timeZone = get_option('timezone_string');
                }

                /** @var EventPeriod $period */
                foreach ($event->getPeriods()->getItems() as $period) {
                    $period->getPeriodStart()->getValue()->setTimezone(new DateTimeZone($timeZone));
                    $period->getPeriodEnd()->getValue()->setTimezone(new DateTimeZone($timeZone));
                }
            }

            $eventsInfo = $eventAS->getEventInfo($event, $isFrontEnd);

            if ($isFrontEnd) {
                $event->setBookings(new Collection());

                /** @var EventPeriod $eventPeriod */
                foreach ($event->getPeriods()->getItems() as $key => $eventPeriod) {
                    /** @var EventPeriod $newEventPeriod **/
                    $newEventPeriod = EventPeriodFactory::create(
                        array_merge(
                            $eventPeriod->toArray(),
                            ['zoomMeeting' => null]
                        )
                    );

                    $event->getPeriods()->placeItem($newEventPeriod, $key, true);
                }
            }

            $ameliaUserId = $userAS->isAmeliaUser($user) && $user->getId() ? $user->getId()->getValue() : null;

            // Delete other bookings if user is customer
            if ($userAS->isCustomer($user)) {
                /** @var CustomerBooking $booking */
                foreach ($event->getBookings()->getItems() as $bookingKey => $booking) {
                    if ($booking->getCustomerId()->getValue() !== $ameliaUserId) {
                        $event->getBookings()->deleteItem($bookingKey);
                    }
                }
            }

            if (!$isFrontEnd && $userAS->isCustomer($user) && $event->getBookings()->length() === 0) {
                continue;
            }

            /** @var CustomerBooking $booking */
            foreach ($event->getBookings()->getItems() as $booking) {
                if ($noShowTagEnabled) {
                    $customersNoShowCountIds[] = $booking->getCustomerId()->getValue();
                }
            }

            $eventArray = $event->toArray();

            $eventArray['staff'] = array_map(
                function ($provider) use ($providerAS) {
                    return [
                        'id' => $provider['id'],
                        'firstName' => $provider['firstName'],
                        'lastName' => $provider['lastName'],
                        'picture' => $provider['pictureThumbPath'],
                        'badge' => $providerAS->getBadge($provider['badgeId'])
                    ];
                },
                $eventArray['providers']
            );

            // TODO - Redesign: Check if providers can be removed
            // unset($eventArray['providers']);

            if (!empty($eventArray['organizerId'])) {
                $eventArray['organizer'] = [
                    'id' => $eventArray['organizerId'],
                    'firstName' => !empty($eventArray['organizer']) ? $eventArray['organizer']['firstName'] : '',
                    'lastName' => !empty($eventArray['organizer']) ? $eventArray['organizer']['lastName'] : '',
                    'picture' => !empty($eventArray['organizer']) ? $eventArray['organizer']['pictureThumbPath'] : '',
                    'badge' => !empty($eventArray['organizer']) ? $providerAS->getBadge($eventArray['organizer']['badgeId']) : null
                ];
            }

            usort(
                $eventArray['gallery'],
                function ($picture1, $picture2) {
                    return $picture1['position'] <=> $picture2['position'];
                }
            );

            $eventsArray[] = array_merge($eventArray, $eventsInfo);
        }

        $customersNoShowCount = [];

        if ($noShowTagEnabled && $customersNoShowCountIds) {
            /** @var CustomerBookingRepository $bookingRepository */
            $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

            $customersNoShowCount = $bookingRepository->countByNoShowStatus($customersNoShowCountIds);
        }

        $eventsArray = apply_filters('amelia_get_events_filter', $eventsArray);

        do_action('amelia_get_events', $eventsArray);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved events');
        $result->setData(
            [
                Entities::EVENTS       => $eventsArray,
                'count'                => !$isCalendarPage && empty($params['skipCount']) ? (int)sizeof($eventRepository->getFilteredIds($params, 0)) : null,
                'countTotal'           => !$isCalendarPage && empty($params['skipCount']) ? (int)sizeof($eventRepository->getFilteredIds([], 0)) : null,
                'customersNoShowCount' => $customersNoShowCount ? array_values($customersNoShowCount) : []
            ]
        );

        return $result;
    }
}
