<?php

namespace AmeliaBooking\Infrastructure\Services\Google;

use AmeliaBooking\Application\Services\Calendar\CalendarApplicationService;
use AmeliaBooking\Application\Services\CustomField\AbstractCustomFieldApplicationService;
use AmeliaBooking\Application\Services\Placeholder\PlaceholderService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\Google\GoogleCalendar;
use AmeliaBooking\Domain\Entity\Calendar\CalendarSync;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Booking\Appointment\AppointmentFactory;
use AmeliaBooking\Domain\Factory\User\ProviderFactory;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Domain\ValueObjects\String\Token;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventPeriodsRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use AmeliaBooking\Infrastructure\Repository\Calendar\CalendarSyncLockRepository;
use AmeliaBooking\Infrastructure\Repository\Calendar\CalendarSyncRepository;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\User\CustomerRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentAddedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentDeletedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentEditedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentStatusUpdatedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\AppointmentTimeUpdatedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingAddedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingApprovedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingCanceledEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Appointment\BookingRejectedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventAddedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventEditedEventHandler;
use AmeliaBooking\Infrastructure\WP\EventListeners\Booking\Event\EventStatusUpdatedEventHandler;
use AmeliaVendor\Google\Client;
use AmeliaVendor\Google\Model;
use AmeliaVendor\Google\Service\Calendar;
use AmeliaVendor\Google\Service\Calendar\CalendarListEntry;
use AmeliaVendor\Google\Service\Calendar\Channel;
use Exception;

/**
 * Class GoogleCalendarService
 *
 * @package AmeliaBooking\Infrastructure\Services\Google
 */
class GoogleCalendarService extends AbstractGoogleCalendarService
{
    /** Renew a channel once it is inside this window of expiring, in seconds. */
    private const CHANNEL_RENEW_MARGIN = 86400;

    /** @var Client|null $client */
    private $client;

    /** @var Calendar|null $service */
    private $service;

    /** @var mixed */
    private $googleCalendarSettings;

    /** @var string */
    private $timeZone;

    /** @var SettingsService */
    private $settings;

    /** @var LoggerInterface */
    private $logger;

    /**
     * GoogleClientService constructor.
     *
     * @param Container $container
     *
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->settings = $this->container->get('domain.settings.service');
        $this->logger = $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC);
        $this->googleCalendarSettings = $this->settings->getCategorySettings('googleCalendar');
        $this->client = new Client();
        $this->client->setClientId($this->googleCalendarSettings['clientID']);
        $this->client->setClientSecret($this->googleCalendarSettings['clientSecret']);
    }

    /**
     * @return bool
     */
    private function isCalendarEnabled()
    {
        return $this->settings->isFeatureEnabled('googleCalendar') &&
            $this->hasCalendarCredentials();
    }

    /**
     * Whether there are credentials that can reach the Calendar API - which is not the same question as
     * whether the customer entered an OAuth application of their own.
     *
     * The check above used to require clientID + clientSecret, which predates the global connection on
     * the Settings page: entering an application was then the only way to connect, so the pair doubled as
     * a proxy for "connected at all". Amelia's own application leaves both empty and keeps the token in
     * the settings instead, so that proxy silently disabled channel creation, push handling and renewal
     * for every employee connected that way, including employees who had connected their own account
     * through it.
     *
     * @return bool
     */
    private function hasCalendarCredentials()
    {
        if (
            !empty($this->googleCalendarSettings['clientID']) &&
            !empty($this->googleCalendarSettings['clientSecret'])
        ) {
            return true;
        }

        return !empty($this->googleCalendarSettings['accessToken']);
    }

    private function isAccessTokenSet()
    {
        return (
            (!array_key_exists('accessToken', $this->googleCalendarSettings) ||
                $this->googleCalendarSettings['accessToken']) &&
            $this->settings->isFeatureEnabled('googleCalendar')
        );
    }

    /**
     * Create a URL to obtain user authorization.
     *
     * @param $providerId
     * @param $redirectUri
     *
     * @return string
     */
    public function createAuthUrl($providerId, $redirectUri)
    {
        // TODO: Redesign back to '/wp-admin/admin.php?page=wpamelia-employees' after redesign will be finished
        $this->client->setRedirectUri(
            empty($redirectUri) ?
            AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-employees' :
            explode('?', $redirectUri)[0]
        );
        $this->client->setState($providerId);
        $this->client->addScope('https://www.googleapis.com/auth/calendar');
        $this->client->setApprovalPrompt('force');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        return $this->client->createAuthUrl();
    }

    /**
     * Exchange a code for a valid authentication token.
     *
     * @param $authCode
     * @param $redirectUri
     * @return array
     */
    public function fetchAccessTokenWithAuthCode($authCode, $redirectUri)
    {
        $this->client->setRedirectUri($redirectUri);

        return $this->client->fetchAccessTokenWithAuthCode($authCode);
    }

    /**
     * Returns entries on the user's calendar list.
     *
     * @param Provider $provider
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function listCalendarList($provider)
    {
        $calendars = [];

        if ($provider && $this->isCalendarEnabled()) {
            if (!$this->authorizeProvider($provider)) {
                return $calendars;
            }

            $calendarList = $this->service->calendarList->listCalendarList(['minAccessRole' => 'writer']);

            /** @var CalendarListEntry $calendar */
            foreach ($calendarList->getItems() as $calendar) {
                $calendars[] = [
                    'id'      => $calendar->getId(),
                    'primary' => $calendar->getPrimary(),
                    'summary' => $calendar->getSummary()
                ];
            }
        }

        return $calendars;
    }

    /**
     * Get calendar lists for multiple Google accounts
     *
     * @param array $accounts
     *
     * @return array
     */
    public function getCalendarListsForAccounts(array $accounts, $provider): array
    {
        foreach ($accounts as &$account) {
            if (isset($account['token']) && $account['token']) {
                try {
                    $client = new Client();
                    $client->setClientId($this->googleCalendarSettings['clientID']);
                    $client->setClientSecret($this->googleCalendarSettings['clientSecret']);
                    $client->setAccessToken($account['token']);

                    if ($client->isAccessTokenExpired()) {
                        $tokenData = json_decode($account['token'], true);
                        $refreshToken = $tokenData['refresh_token'] ?? $client->getRefreshToken();

                        $client->fetchAccessTokenWithRefreshToken($refreshToken);

                        $newAccessToken = $client->getAccessToken();
                        if (is_array($newAccessToken) && empty($newAccessToken['refresh_token']) && $refreshToken) {
                            $newAccessToken['refresh_token'] = $refreshToken;
                        }

                        $newTokenJson = json_encode($newAccessToken);
                        /** @var ProviderRepository $providerRepository */
                        $providerRepository = $this->container->get('domain.users.providers.repository');
                        $providerRepository->updateGoogleCalendarAccountToken($account['id'], $newTokenJson);

                        $account['token'] = $newTokenJson;
                    }

                    $service = new Calendar($client);
                    $calendarList = $service->calendarList->listCalendarList(['minAccessRole' => 'writer']);

                    $calendars = [];
                    foreach ($calendarList->getItems() as $calendar) {
                        $calendars[] = [
                            'id'      => $calendar->getId(),
                            'primary' => $calendar->getPrimary(),
                            'summary' => $calendar->getSummary()
                        ];
                    }

                    $account['calendarList'] = $calendars;
                } catch (\Exception $e) {
                    $this->logger->error(
                        'GoogleCalendar: Error fetching calendar list for account',
                        [
                            'exception' => $e,
                            'accountId' => $account['id'],
                        ]
                    );
                    $account['calendarList'] = [];
                }
            } else {
                $account['calendarList'] = [];
            }
        }

        return $accounts;
    }

    /**
     * Get Provider's Google Calendar ID.
     *
     * @param Provider $provider
     *
     * @return null|string
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function getProviderGoogleCalendarId($provider)
    {
        if (!$this->isCalendarEnabled()) {
            return null;
        }

        // If Google Calendar ID is not set, take the primary calendar and save it as Provider's Google Calendar ID
        if ($provider && $provider->getGoogleCalendar() && empty($provider->getGoogleCalendar()->getCalendarId()->getValue())) {
            $calendarList = $this->listCalendarList($provider);

            /** @var ProviderApplicationService $providerApplicationService */
            $providerApplicationService = $this->container->get('application.user.provider.service');

            $provider->getGoogleCalendar()->setCalendarId(new Name($calendarList[0]['id']));

            $providerApplicationService->updateProviderGoogleCalendar($provider);

            return $provider->getGoogleCalendar()->getCalendarId()->getValue();
        }

        // If Google Calendar is set, return it
        if ($provider && $provider->getGoogleCalendar() && !empty($provider->getGoogleCalendar()->getCalendarId()->getValue())) {
            return $provider->getGoogleCalendar()->getCalendarId()->getValue();
        }

        return null;
    }

    /**
     * Handle Google Calendar Event's.
     *
     * @param Appointment|Event $appointment
     * @param string      $commandSlug
     *
     * @return void
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handleEvent($appointment, $commandSlug)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        try {
            $this->handleEventAction($appointment, $commandSlug);
        } catch (Exception $e) {
            /** @var AppointmentRepository $appointmentRepository */
            $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

            $appointmentRepository->updateErrorColumn($appointment->getId()->getValue(), $e->getMessage());
        }
    }

    /**
     * Get insertPendingAppointments setting
     *
     * @param Provider $provider
     * @return bool
     */
    private function getInsertPendingAppointments($provider)
    {
        if ($provider && $provider->getGoogleCalendar()) {
            return $provider->getGoogleCalendar()->getInsertPendingAppointments();
        }

        return (bool)($this->googleCalendarSettings['insertPendingAppointments'] ?? false);
    }

    /**
     * Get includeBufferTime setting
     *
     * @param Provider $provider
     * @return bool
     */
    private function getIncludeBufferTime($provider)
    {
        if ($provider && $provider->getGoogleCalendar()) {
            return $provider->getGoogleCalendar()->getIncludeBufferTime();
        }

        return (bool)($this->googleCalendarSettings['includeBufferTimeGoogleCalendar'] ?? false);
    }

    /**
     * Handle Google Calendar Events.
     *
     * @param Event $event
     * @param string $commandSlug
     * @param Collection $periods
     * @param array $providers
     *
     * @return void
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handleEventPeriodsChange($event, $commandSlug, $periods, $providers = null, $providersRemove = null)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        try {
            $this->handleEventPeriodsChangeAction($event, $commandSlug, $periods, $providers, $providersRemove);
        } catch (Exception $e) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $this->container->get('domain.booking.event.repository');

            $eventRepository->updateErrorColumn($event->getId()->getValue(), $e->getMessage());
        }
    }

    /**
     * Handle Google Calendar Event's.
     *
     * @param Appointment|Event $appointment
     * @param string      $commandSlug
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function handleEventAction($appointment, $commandSlug)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $appointmentStatus = $appointment->getStatus()->getValue();
        $provider          = $providerRepository->getById($appointment->getProviderId()->getValue());
        if (
            $provider && (
                ($provider->getGoogleCalendar() && $provider->getGoogleCalendar()->getCalendarId()->getValue()) ||
                ($provider->getGoogleCalendarId() && $provider->getGoogleCalendarId()->getValue())
            )
        ) {
            if (!$this->authorizeProvider($provider)) {
                return;
            }

            switch ($commandSlug) {
                case AppointmentAddedEventHandler::APPOINTMENT_ADDED:
                case BookingAddedEventHandler::BOOKING_ADDED:
                    // Add new appointment or update existing one
                    if (!$appointment->getGoogleCalendarEventId()) {
                        $this->insertEvent($appointment, $provider);
                    } else {
                        $this->updateEvent($appointment, $provider);
                    }

                    // When status is pending we must first insert the event to get event ID
                    // because if we update the status later to 'Approved' we must have ID of the event
                    if ($appointmentStatus === 'pending' && $this->getInsertPendingAppointments($provider) === false) {
                        $this->deleteEvent($appointment, $provider);
                    }
                    break;
                case AppointmentEditedEventHandler::APPOINTMENT_EDITED:
                case AppointmentTimeUpdatedEventHandler::TIME_UPDATED:
                case AppointmentStatusUpdatedEventHandler::APPOINTMENT_STATUS_UPDATED:
                case BookingCanceledEventHandler::BOOKING_CANCELED:
                case BookingApprovedEventHandler::BOOKING_APPROVED:
                case BookingRejectedEventHandler::BOOKING_REJECTED:
                    if (
                        $appointmentStatus === 'canceled' || $appointmentStatus === 'rejected' ||
                        ($appointmentStatus === 'pending' && $this->getInsertPendingAppointments($provider) === false)
                    ) {
                        $this->deleteEvent($appointment, $provider);
                        break;
                    }

                    if (!$appointment->getGoogleCalendarEventId()) {
                        $this->insertEvent($appointment, $provider);
                        break;
                    }

                    $this->updateEvent($appointment, $provider);
                    break;
                case AppointmentDeletedEventHandler::APPOINTMENT_DELETED:
                    $this->deleteEvent($appointment, $provider);
                    break;
            }
        }
    }

    /**
     * Handle Google Calendar Events.
     *
     * @param Event $event
     * @param string $commandSlug
     * @param Collection $periods
     * @param array $providers
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function handleEventPeriodsChangeAction($event, $commandSlug, $periods, $providers = null, $providersRemove = null)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        if ($event->getOrganizerId()) {
            $provider = $providerRepository->getById($event->getOrganizerId()->getValue());

            if (
                $provider && (
                    ($provider->getGoogleCalendar() && $provider->getGoogleCalendar()->getCalendarId()->getValue()) ||
                    ($provider->getGoogleCalendarId() && $provider->getGoogleCalendarId()->getValue())
                )
            ) {
                if (!$this->authorize($provider->getGoogleCalendar())) {
                    return;
                }

                /** @var EventPeriod $period */
                foreach ($periods->getItems() as $period) {
                    switch ($commandSlug) {
                        case EventAddedEventHandler::EVENT_ADDED:
                        case EventEditedEventHandler::TIME_UPDATED:
                        case EventEditedEventHandler::PROVIDER_CHANGED:
                            if (!$period->getGoogleCalendarEventId()) {
                                $this->insertEvent($event, $provider, $period);
                                break;
                            }

                            $this->updateEvent($event, $provider, $period, $providers, $providersRemove);
                            break;
                        case EventEditedEventHandler::EVENT_PERIOD_DELETED:
                            $this->deleteEvent($period, $provider);
                            $this->deleteEventPeriodEvent($period);
                            break;
                        case BookingAddedEventHandler::BOOKING_ADDED:
                        case BookingCanceledEventHandler::BOOKING_CANCELED:
                            if (!$period->getGoogleCalendarEventId()) {
                                $this->insertEvent($event, $provider, $period);
                            } else {
                                $this->patchEvent($event, $provider, $period);
                            }
                            break;
                        case EventStatusUpdatedEventHandler::EVENT_STATUS_UPDATED:
                            if ($event->getStatus()->getValue() === 'rejected') {
                                $this->deleteEvent($period, $provider);
                                $this->deleteEventPeriodEvent($period);
                            } elseif ($event->getStatus()->getValue() === 'approved') {
                                $this->insertEvent($event, $provider, $period);
                            }

                            break;
                        case EventEditedEventHandler::EVENT_PERIOD_ADDED:
                            $this->insertEvent($event, $provider, $period);
                            break;
                    }
                }
            }
        }
    }

    /**
     * Get providers events within date range
     *
     * @param array $providerArr
     * @param string $dateStart
     * @param string $dateStartEnd
     * @param string $dateEnd
     * @param array $eventIds

     * @return array
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function getEvents($providerArr, $dateStart, $dateStartEnd, $dateEnd, $eventIds)
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return [];
        }

        $finalEvents = [];
        $provider    = ProviderFactory::create($providerArr);
        if (
            $provider && (
                ($provider->getGoogleCalendar() && $provider->getGoogleCalendar()->getCalendarId()->getValue()) ||
                ($provider->getGoogleCalendarId() && $provider->getGoogleCalendarId()->getValue())
            )
        ) {
            if (!$this->authorize($provider->getGoogleCalendar())) {
                return $finalEvents;
            }

            $googleCalendarId = $provider->getGoogleCalendar() ?
                $provider->getGoogleCalendar()->getCalendarId()->getValue() :
                $provider->getGoogleCalendarId()->getValue();

            try {
                $this->timeZone = $this->service->calendars->get($googleCalendarId)->getTimeZone() ?: 'UTC';
            } catch (Exception $e) {
                $this->timeZone = 'UTC';
            }

            $events = $this->service->events->listEvents(
                $googleCalendarId,
                [
                    'maxResults'   => $this->googleCalendarSettings['maximumNumberOfEventsReturned'],
                    'orderBy'      => 'startTime',
                    'singleEvents' => true,
                    'timeMin'      => $dateStart,
                    'timeMax'      => $dateEnd
                ]
            );

            $rangeStart = DateTimeService::getCustomDateTimeObject($dateStart);
            $rangeEnd   = DateTimeService::getCustomDateTimeObject($dateEnd);

            foreach ($events->getItems() as $event) {
                $eventStart = $event->getStart();
                $eventEnd   = $event->getEnd();
                if ($eventStart === null || $eventEnd === null) {
                    continue;
                }

                if ($eventStart->getDateTime()) {
                    $eventStartDt = DateTimeService::getCustomDateTimeObject($eventStart->getDateTime());
                    $eventEndDt   = DateTimeService::getCustomDateTimeObject($eventEnd->getDateTime());
                } elseif ($eventStart->getDate()) {
                    $eventStartDt = DateTimeService::getCustomDateTimeObject(
                        $this->getEventDateTimeStringFromEventDate($eventStart->getDate())
                    );
                    $eventEndDt = DateTimeService::getCustomDateTimeObject(
                        $this->getEventDateTimeStringFromEventDate($eventEnd->getDate())
                    );
                } else {
                    continue;
                }

                if ($eventEndDt <= $rangeStart || $eventStartDt >= $rangeEnd) {
                    continue;
                }

                $finalEvents[] = $event;
            }
        }
        return $finalEvents;
    }


    /**
     * Create fake appointments in provider's list so that these slots will not be available for booking
     *
     * @param Collection $providers
     * @param int        $excludeAppointmentId
     * @param \DateTime  $startDateTime
     * @param \DateTime  $endDateTime
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     * @throws ContainerException
     */
    public function removeSlotsFromGoogleCalendar(
        $providers,
        $excludeAppointmentId,
        $startDateTime,
        $endDateTime
    ): void {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        if ($this->googleCalendarSettings['removeGoogleCalendarBusySlots'] === true) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            foreach ($providers->keys() as $providerKey) {
                /** @var Provider $provider */
                $provider = $providers->getItem($providerKey);

                if ($provider && ($provider->getGoogleCalendar() || $provider->getGoogleCalendarId())) {
                    $startDateTimeCopy = clone $startDateTime;

                    $startDateTimeCopy->modify('-1 days');

                    $endDateTimeCopy = clone $endDateTime;
                    $endDateTimeCopy->modify('+1 days');

                    $rangeStart = $startDateTimeCopy->format('Y-m-d');
                    $rangeEnd   = $endDateTimeCopy->format('Y-m-d');

                    // Only the fetch is memoized. The events must be applied to the provider on every
                    // call, because each availability computation loads a fresh Provider entity with an
                    // empty appointment list. The key includes the requested range so that a different
                    // range does not reuse events fetched for another one.
                    $cacheKey = $provider->getId()->getValue() . '_' . $rangeStart . '_' . $rangeEnd;

                    if (!array_key_exists($cacheKey, self::$providersGoogleEvents)) {
                        if (!$this->authorizeProvider($provider)) {
                            continue;
                        }

                        $this->timeZone = $this->service->calendars->get('primary')->getTimeZone();

                        $googleCalendarId = $provider->getGoogleCalendar() ?
                            $provider->getGoogleCalendar()->getCalendarId()->getValue() :
                            $provider->getGoogleCalendarId()->getValue();

                        $events = $this->service->events->listEvents(
                            $googleCalendarId,
                            [
                                'maxResults' => $this->googleCalendarSettings['maximumNumberOfEventsReturned'],
                                'orderBy' => 'startTime',
                                'singleEvents' => true,
                                'timeMin' => DateTimeService::getCustomDateTimeRFC3339($rangeStart),
                                'timeMax' => DateTimeService::getCustomDateTimeRFC3339($rangeEnd),
                            ]
                        );

                        self::$providersGoogleEvents[$cacheKey] = [
                            'events'   => $events,
                            'timeZone' => $this->timeZone,
                        ];
                    }

                    $cachedEvents = self::$providersGoogleEvents[$cacheKey];
                    $this->timeZone = $cachedEvents['timeZone'];

                    $this->processGoogleCalendarEvents(
                        $cachedEvents['events'],
                        $provider,
                        $excludeAppointmentId
                    );

                    // Process blocked calendars events
                    $this->processBlockedCalendarsEvents(
                        $provider,
                        $providerRepository,
                        $startDateTimeCopy,
                        $endDateTimeCopy,
                        $excludeAppointmentId
                    );
                }
            }
        }
    }

    /**
     * Process events from blocked calendars and add them as fake appointments
     *
     * @param Provider           $provider
     * @param ProviderRepository $providerRepository
     * @param \DateTime          $startDateTime
     * @param \DateTime          $endDateTime
     * @param int|null           $excludeAppointmentId
     *
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    private function processBlockedCalendarsEvents(
        $provider,
        $providerRepository,
        $startDateTime,
        $endDateTime,
        $excludeAppointmentId
    ): void {
        $providerId = $provider->getId()->getValue();

        // The key includes the requested range so that a different range does not reuse
        // events fetched for another one.
        $cacheKey = $providerId . '_blocked_' . $startDateTime->format('Y-m-d') . '_' .
            $endDateTime->format('Y-m-d');

        if (!array_key_exists($cacheKey, self::$providersGoogleEvents)) {
            $accounts = $providerRepository->getGoogleCalendarAccounts($providerId);

            self::$providersGoogleEvents[$cacheKey] = $this->fetchBlockedCalendarEventsFromAccounts(
                $accounts,
                $startDateTime,
                $endDateTime
            );
        }

        foreach (self::$providersGoogleEvents[$cacheKey] as $events) {
            $this->processGoogleCalendarEvents($events, $provider, $excludeAppointmentId);
        }
    }

    /**
     * Fetch events from blocked calendars for each account
     *
     * @param array     $accounts
     * @param \DateTime $startDateTime
     * @param \DateTime $endDateTime
     *
     * @return Calendar\Events[]
     *
     * @throws InvalidArgumentException
     */
    private function fetchBlockedCalendarEventsFromAccounts(
        $accounts,
        $startDateTime,
        $endDateTime
    ): array {
        $eventListParams = [
            'maxResults'   => $this->googleCalendarSettings['maximumNumberOfEventsReturned'],
            'orderBy'      => 'startTime',
            'singleEvents' => true,
            'timeMin'      => DateTimeService::getCustomDateTimeRFC3339($startDateTime->format('Y-m-d')),
            'timeMax'      => DateTimeService::getCustomDateTimeRFC3339($endDateTime->format('Y-m-d')),
        ];

        $blockedEvents = [];

        foreach ($accounts as $account) {
            if (empty($account['blockedCalendars']) || empty($account['token'])) {
                continue;
            }

            $service = $this->createCalendarServiceForAccount($account);

            if ($service === null) {
                continue;
            }

            foreach ($account['blockedCalendars'] as $calendarId) {
                try {
                    $blockedEvents[] = $service->events->listEvents($calendarId, $eventListParams);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $blockedEvents;
    }

    /**
     * Create a Calendar service for a given account, handling token refresh if needed
     *
     * @param array $account
     *
     * @return Calendar|null
     */
    private function createCalendarServiceForAccount($account): ?Calendar
    {
        try {
            // Check if using global connection (middleware) or direct connection (clientId/clientSecret)
            if ($this->googleCalendarSettings['accessToken']) {
                $client = new Client();
                $client->setAccessToken($account['token']);

                if ($client->isAccessTokenExpired()) {
                    $tokenData = json_decode($account['token'], true);

                    if (!isset($tokenData['refresh_token'])) {
                        return null;
                    }

                    /** @var GoogleCalendarMiddlewareService $middlewareService */
                    $middlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');
                    $newAccessToken = $middlewareService->refreshAccessToken($tokenData['refresh_token']);

                    if ($newAccessToken === null) {
                        return null;
                    }

                    if (empty($newAccessToken['refresh_token'])) {
                        $newAccessToken['refresh_token'] = $tokenData['refresh_token'];
                    }

                    $newTokenJson = json_encode($newAccessToken);
                    $client->setAccessToken($newTokenJson);
                    $this->persistGoogleCalendarAccountToken($account['id'], $newTokenJson);
                }
            } else {
                $client = new Client();
                $client->setClientId($this->googleCalendarSettings['clientID']);
                $client->setClientSecret($this->googleCalendarSettings['clientSecret']);
                $client->setAccessToken($account['token']);

                if ($client->isAccessTokenExpired()) {
                    $tokenData = json_decode($account['token'], true);
                    $refreshToken = $tokenData['refresh_token'] ?? $client->getRefreshToken();

                    $client->fetchAccessTokenWithRefreshToken($refreshToken);

                    $newAccessToken = $client->getAccessToken();
                    if (is_array($newAccessToken) && empty($newAccessToken['refresh_token']) && $refreshToken) {
                        $newAccessToken['refresh_token'] = $refreshToken;
                    }

                    $newTokenJson = json_encode($newAccessToken);
                    $this->persistGoogleCalendarAccountToken($account['id'], $newTokenJson);
                }
            }

            return new Calendar($client);
        } catch (\Exception $e) {
            $this->logger->error(
                'GoogleCalendar: Error creating service for account',
                [
                    'exception' => $e,
                    'accountId' => $account['id'],
                ]
            );
            return null;
        }
    }

    /**
     * Persist a refreshed Google Calendar account token without failing the request.
     *
     * @param int    $accountId
     * @param string $token
     *
     * @return void
     */
    private function persistGoogleCalendarAccountToken($accountId, $token)
    {
        try {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');
            $providerRepository->updateGoogleCalendarAccountToken($accountId, $token);
        } catch (\Exception $e) {
            error_log(
                'GoogleCalendar: Failed to persist refreshed account token for account '
                . $accountId . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Process Google Calendar events and add them as fake appointments to block slots
     *
     * @param Calendar\Events $events
     * @param Provider        $provider
     * @param int|null        $excludeAppointmentId
     *
     * @throws InvalidArgumentException
     */
    private function processGoogleCalendarEvents($events, $provider, $excludeAppointmentId)
    {
        /** @var Calendar\Event $event */
        foreach ($events->getItems() as $event) {
            // Continue if event is set to "Free"
            if ($event->getTransparency() === 'transparent') {
                continue;
            }

            $extendedProperties = $event->getExtendedProperties();
            if ($extendedProperties !== null) {
                $shared = $extendedProperties->shared;
                if (
                    is_array($shared) &&
                    array_key_exists('ameliaEvent', $shared) &&
                    $excludeAppointmentId !== null &&
                    array_key_exists('ameliaAppointmentId', $shared) &&
                    $shared['ameliaAppointmentId'] !== null &&
                    (int)$shared['ameliaAppointmentId'] === (int)$excludeAppointmentId
                ) {
                    continue;
                }
            }

            if ($event->getStart()->dateTime === null) {
                $eventStartString = $this->getEventDateTimeStringFromEventDate($event->getStart()->date);

                $eventEndString = $this->getEventDateTimeStringFromEventDate($event->getEnd()->date);
            } else {
                $eventStartString = $this->getEventDateTimeStringFromEventDateTime($event->getStart()->dateTime);

                $eventEndString = $this->getEventDateTimeStringFromEventDateTime($event->getEnd()->dateTime);
            }

            /** @var Appointment $appointment */
            $appointment = AppointmentFactory::create(
                [
                    'bookingStart'       => $eventStartString,
                    'bookingEnd'         => $eventEndString,
                    'notifyParticipants' => false,
                    'serviceId'          => 0,
                    'providerId'         => $provider->getId()->getValue(),
                ]
            );

            $provider->getAppointmentList()->addItem($appointment);
        }
    }


    /**
     * Delete Event period google calendar id
     *
     * @param EventPeriod $period
     *
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function deleteEventPeriodEvent($period)
    {
        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

        $period->setGoogleCalendarEventId(null);
        $period->setGoogleMeetUrl(null);

        $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), null, 'googleCalendarEventId');
        $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), null, 'googleMeetUrl');
    }

        /**
     * Insert an Event in Google Calendar.
     *
     * @param Appointment|Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function insertEvent($appointment, $provider, $period = null)
    {
        $queryParams = ['sendUpdates' => $this->googleCalendarSettings['sendEventInvitationEmail'] ? 'all' : 'none'];

        /** @var SettingsService $settingsService */
        $settingsService  = $this->container->get('domain.settings.service');
        $enabledForEntity = $settingsService
            ->getEntitySettings($period ? $appointment->getSettings() : $appointment->getService()->getSettings())
            ->getGoogleMeetSettings()
            ->getEnabled();

        if ($enabledForEntity) {
            $queryParams['conferenceDataVersion'] = 1;
        }

        $event = $this->createEvent($appointment, $provider, $period);

        $event = apply_filters('amelia_before_google_calendar_event_added_filter', $event, $appointment->toArray(), $provider->toArray());

        do_action('amelia_before_google_calendar_event_added', $event, $appointment->toArray(), $provider->toArray());

        $googleCalendarId = $provider->getGoogleCalendar() ?
            $provider->getGoogleCalendar()->getCalendarId()->getValue() :
            $provider->getGoogleCalendarId()->getValue();

        $event = $this->service->events->insert(
            $googleCalendarId,
            $event,
            $queryParams
        );

        if ($period) {
            /** @var EventPeriodsRepository $eventPeriodsRepository */
            $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

            $period->setGoogleCalendarEventId(new Token($event->getId()));
            $period->setGoogleMeetUrl($event->getHangoutLink());

            $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), $period->getGoogleCalendarEventId()->getValue(), 'googleCalendarEventId');
            $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), $period->getGoogleMeetUrl(), 'googleMeetUrl');
        } else {
            /** @var AppointmentRepository $appointmentRepository */
            $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

            $appointment->setGoogleCalendarEventId(new Token($event->getId()));
            $appointment->setGoogleMeetUrl($event->getHangoutLink());

            $appointmentRepository->update($appointment->getId()->getValue(), $appointment);
        }

        do_action('amelia_after_google_calendar_event_added', $event, $appointment->toArray(), $provider->toArray());
    }

    /**
     * Update an Event in Google Calendar.
     *
     * @param Appointment|Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     *
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function updateEvent($appointment, $provider, $period = null, $providers = null, $providersRemove = null)
    {
        /** @var SettingsService $settingsService */
        $settingsService  = $this->container->get('domain.settings.service');
        $enabledForEntity = $settingsService
            ->getEntitySettings($period ? $appointment->getSettings() : $appointment->getService()->getSettings())
            ->getGoogleMeetSettings()
            ->getEnabled();

        $event = $this->createEvent($appointment, $provider, $period, $providers, $providersRemove);

        $entity = $period ?: $appointment;
        if ($entity->getGoogleCalendarEventId()) {
            $event = apply_filters('amelia_before_google_calendar_event_updated_filter', $event, $appointment->toArray(), $provider->toArray());

            do_action('amelia_before_google_calendar_event_updated', $event, $appointment->toArray(), $provider->toArray());

            $googleCalendarId = $provider->getGoogleCalendar() ?
                $provider->getGoogleCalendar()->getCalendarId()->getValue() :
                $provider->getGoogleCalendarId()->getValue();

            $queryParams = [
                'sendUpdates' => $this->googleCalendarSettings['sendEventInvitationEmail'] ? 'all' : 'none',
            ];

            // conferenceDataVersion is required both to create and to clear Meet on the remote event.
            $queryParams['conferenceDataVersion'] = 1;
            if (!$enabledForEntity) {
                // null is stripped by the Google client; NULL_VALUE sends conferenceData: null.
                // Use ArrayAccess — setConferenceData() type-hints ConferenceData and rejects the sentinel.
                $event['conferenceData'] = Model::NULL_VALUE;
            }

            $updatedEvent = $this->service->events->update(
                $googleCalendarId,
                $entity->getGoogleCalendarEventId()->getValue(),
                $event,
                $queryParams
            );

            $this->syncGoogleMeetUrl($appointment, $period, $enabledForEntity, $updatedEvent);

            do_action('amelia_after_google_calendar_event_updated', $updatedEvent, $appointment->toArray(), $provider->toArray());
        }
    }

    /**
     * Persist or clear Google Meet URL after calendar create/update.
     *
     * @param Appointment|Event $appointment
     * @param EventPeriod|null $period
     * @param bool $enabledForEntity
     * @param Calendar\Event $googleEvent
     *
     * @return void
     *
     * @throws QueryExecutionException
     */
    private function syncGoogleMeetUrl($appointment, $period, $enabledForEntity, $googleEvent): void
    {
        if ($period) {
            /** @var EventPeriodsRepository $eventPeriodsRepository */
            $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

            if (!$enabledForEntity) {
                $period->setGoogleMeetUrl(null);
                $eventPeriodsRepository->updateFieldById(
                    $period->getId()->getValue(),
                    null,
                    'googleMeetUrl'
                );

                return;
            }

            if ($googleEvent && $googleEvent->getHangoutLink()) {
                $period->setGoogleMeetUrl($googleEvent->getHangoutLink());
                $eventPeriodsRepository->updateFieldById(
                    $period->getId()->getValue(),
                    $period->getGoogleMeetUrl(),
                    'googleMeetUrl'
                );
            }

            return;
        }

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        if (!$enabledForEntity) {
            $appointment->setGoogleMeetUrl(null);
            $appointmentRepository->updateFieldById(
                $appointment->getId()->getValue(),
                null,
                'googleMeetUrl'
            );

            return;
        }

        if ($googleEvent && $googleEvent->getHangoutLink()) {
            $appointment->setGoogleMeetUrl($googleEvent->getHangoutLink());
            $appointmentRepository->updateFieldById(
                $appointment->getId()->getValue(),
                $appointment->getGoogleMeetUrl(),
                'googleMeetUrl'
            );
        }
    }

    /**
     * Patch an Event in Google Calendar.
     *
     * @param Appointment|Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     *
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function patchEvent($appointment, $provider, $period = null)
    {
        $event = $this->createEvent($appointment, $provider, $period);
        $event->setAttendees($this->getAttendees($appointment, $period));

        $entity = $period ?: $appointment;
        if ($entity->getGoogleCalendarEventId()) {
            $event = apply_filters('amelia_before_google_calendar_event_patched_filter', $event, $appointment->toArray(), $provider->toArray());

            do_action('amelia_before_google_calendar_event_patched', $event, $appointment->toArray(), $provider->toArray());

            $googleCalendarId = $provider->getGoogleCalendar() ?
                $provider->getGoogleCalendar()->getCalendarId()->getValue() :
                $provider->getGoogleCalendarId()->getValue();

            $this->service->events->patch(
                $googleCalendarId,
                $entity->getGoogleCalendarEventId()->getValue(),
                $event,
                ['sendUpdates' => $this->googleCalendarSettings['sendEventInvitationEmail'] ? 'all' : 'none']
            );

            do_action('amelia_after_google_calendar_event_patched', $event, $appointment->toArray(), $provider->toArray());
        }
    }

    /**
     * Delete an Event from Google Calendar.
     *
     * @param Appointment|EventPeriod $appointment
     * @param Provider $provider
     *
     * @throws ContainerException
     */
    private function deleteEvent($appointment, $provider)
    {
        if ($appointment->getGoogleCalendarEventId()) {
            do_action('amelia_before_google_calendar_event_deleted', $appointment->toArray(), $provider->toArray());

            $googleCalendarId = $provider->getGoogleCalendar() ?
                $provider->getGoogleCalendar()->getCalendarId()->getValue() :
                $provider->getGoogleCalendarId()->getValue();

            $this->service->events->delete(
                $googleCalendarId,
                $appointment->getGoogleCalendarEventId()->getValue()
            );

            do_action('amelia_after_google_calendar_event_deleted', $appointment->toArray(), $provider->toArray());
        }
    }

    /**
     * Get employee level "Event title and description" templates.
     *
     * Raw nullable values are read from the database so empty employee templates stay null and
     * hasEventTemplate() can fall back to global settings. Hydrated GoogleCalendar fields fill in
     * hardcoded defaults when no template is stored, which would skip that fallback.
     *
     * @param Provider $provider
     *
     * @return array
     *
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function getProviderCalendarSettings($provider): array
    {
        if (!$provider->getId()) {
            return [];
        }

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        return $providerRepository->getGoogleCalendarSettings($provider->getId()->getValue());
    }

    /**
     * Resolve the template that will be used for the event title or description.
     *
     * Employee level template has priority, the global one is used as a fallback, and the hardcoded
     * default is used only when neither of them is configured.
     *
     * @param array|null $providerSettings
     * @param string     $settingsKey
     * @param string     $eventType
     * @param array      $default
     *
     * @return array
     */
    private function getEventTemplateSettings($providerSettings, $settingsKey, $eventType, $default)
    {
        if ($this->hasEventTemplate($providerSettings, $eventType)) {
            return $providerSettings;
        }

        $globalSettings = isset($this->googleCalendarSettings[$settingsKey]) ?
            $this->googleCalendarSettings[$settingsKey] :
            null;

        if ($this->hasEventTemplate($globalSettings, $eventType)) {
            return $globalSettings;
        }

        return $default;
    }

    /**
     * @param array|null $settings
     * @param string     $eventType
     *
     * @return bool
     */
    private function hasEventTemplate($settings, $eventType)
    {
        return is_array($settings) &&
            array_key_exists($eventType, $settings) &&
            $settings[$eventType] !== '' &&
            $settings[$eventType] !== null;
    }

    /**
     * Create and return Google Calendar Event Object filled with appointments data.
     *
     * @param Appointment|Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     *
     * @return Calendar\Event
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws Exception
     */
    private function createEvent($appointment, $provider, $period = null, $providers = null, $providersRemove = null)
    {
        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');

        /** @var AbstractCustomFieldApplicationService $customFieldService */
        $customFieldService = $this->container->get('application.customField.service');

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get("application.placeholder.{$type}.service");

        $appointmentLocationId = $appointment->getLocationId() ? $appointment->getLocationId()->getValue() : null;

        $providerLocationId = $provider->getLocationId() ? $provider->getLocationId()->getValue() : null;

        $locationId = $appointmentLocationId ?: $providerLocationId;

        $location = $locationId ? $locationRepository->getById($locationId) : null;

        $address = $customFieldService->getCalendarEventLocation($appointment);
        $address = $address ?: ($location ? $location->getAddress()->getValue() : null);

        $attendees = $this->getAttendees($appointment, $period, $providers, $providersRemove);

        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $start = $period ? clone $period->getPeriodStart()->getValue() : clone $appointment->getBookingStart()->getValue();

        if ($period) {
            $time = (int)$period->getPeriodEnd()->getValue()->format('H') * 60 + (int)$period->getPeriodEnd()->getValue()->format('i');

            $end = DateTimeService::getCustomDateTimeObject(
                $start->format('Y-m-d')
            )->add(new \DateInterval('PT' . $time . 'M'));
        } else {
            $end = clone $appointment->getBookingEnd()->getValue();
        }

        if ($this->getIncludeBufferTime($provider) === true && $type === Entities::APPOINTMENT) {
            $timeBefore = $appointment->getService()->getTimeBefore() ?
                $appointment->getService()->getTimeBefore()->getValue() : 0;

            $timeAfter = $appointment->getService()->getTimeAfter() ?
                $appointment->getService()->getTimeAfter()->getValue() : 0;

            $start->modify('-' . $timeBefore . ' second');
            $end->modify('+' . $timeAfter . ' second');
        }

        $eventType = $period ? 'event' : 'appointment';

        $providerCalendarSettings = $this->getProviderCalendarSettings($provider);

        $titleSettings = $this->getEventTemplateSettings(
            isset($providerCalendarSettings['title']) ? $providerCalendarSettings['title'] : null,
            'title',
            $eventType,
            [
                'appointment' => '%service_name%',
                'event' => '%event_name%'
            ]
        );

        $descriptionSettings = $this->getEventTemplateSettings(
            isset($providerCalendarSettings['description']) ? $providerCalendarSettings['description'] : null,
            'description',
            $eventType,
            [
                'appointment' => '',
                'event' => ''
            ]
        );

        $eventData = [
            'start'                   => [
                'dateTime' => DateTimeService::getCustomDateTimeRFC3339($start->format('Y-m-d H:i:s')),
                'timeZone' => $start->getTimezone()->getName()
            ],
            'end'                     => [
                'dateTime' => DateTimeService::getCustomDateTimeRFC3339($end->format('Y-m-d H:i:s')),
                'timeZone' => $end->getTimezone()->getName()
            ],
            'guestsCanSeeOtherGuests' => $this->googleCalendarSettings['showAttendees'],
            'attendees'               => $attendees,
            'description'             => $placeholderService->applyPlaceholders(
                $period ? $descriptionSettings['event'] : $descriptionSettings['appointment'],
                $placeholderData
            ),
            'extendedProperties'      => [
                'shared' => [
                    'ameliaEvent'         => true,
                    'ameliaAppointmentId' => $type === Entities::APPOINTMENT ? $appointment->getId()->getValue() : null,
                    'ameliaEventPeriodId' => $type === Entities::EVENT ? $period->getId()->getValue() : null,
                    'ameliaEventId'       => $type === Entities::EVENT ? $appointment->getId()->getValue() : null,
                ],
            ],
            'location'                => $address,
            'locked'                  => true,
            'status'                  => $this->googleCalendarSettings['status'],
            'summary'                 => $placeholderService->applyPlaceholders(
                $period ? $titleSettings['event'] : $titleSettings['appointment'],
                $placeholderData
            )
        ];

        /** @var SettingsService $settingsService */
        $settingsService  = $this->container->get('domain.settings.service');
        $enabledForEntity = $settingsService
            ->getEntitySettings($period ? $appointment->getSettings() : $appointment->getService()->getSettings())
            ->getGoogleMeetSettings()
            ->getEnabled();

        if ($enabledForEntity) {
            $token = new Token();

            $eventData['conferenceData'] = [
                'createRequest' => [
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                    'requestId' => $appointment->getId()->getValue() . '_' . $token->getValue(),
                ]
            ];
        }

        if ($period && $period->getPeriodStart()->getValue()->diff($period->getPeriodEnd()->getValue())->format('%a') !== '0') {
            $eventData['recurrence'] = [
                'RRULE:FREQ=DAILY;UNTIL=' .
                $period->getPeriodEnd()->getValue()->format('Ymd\THis\Z')
            ];
        }

        return new Calendar\Event($eventData);
    }

    /**
     * Get All Attendees that need to be added in Google Calendar Event based on "addAttendees" Settings.
     *
     * @param Appointment|Event $appointment
     *
     * @return array
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    private function getAttendees($appointment, $period = null, $providersNew = null, $providersRemove = null)
    {
        $attendees = [];

        if ($this->googleCalendarSettings['addAttendees'] === true) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            $provider =
                $period ?
                    $providerRepository->getById($appointment->getOrganizerId()->getValue()) :
                    $providerRepository->getById($appointment->getProviderId()->getValue());

            $organizerId = null;

            if ($provider && $provider->getGoogleCalendar() && $provider->getGoogleCalendar()->getCalendarId()) {
                $attendees[] = [
                    'displayName'    => $provider->getFirstName()->getValue() . ' ' . $provider->getLastName()->getValue(),
                    'email'          => $provider->getGoogleCalendar()->getCalendarId()->getValue(),
                    'responseStatus' => 'accepted',
                    'organizer'      => true
                ];

                $organizerId = $provider->getId()->getValue();
            } elseif ($provider && $provider->getGoogleCalendarId()) {
                $attendees[] = [
                    'displayName'    => $provider->getFirstName()->getValue() . ' ' . $provider->getLastName()->getValue(),
                    'email'          => $provider->getGoogleCalendarId()->getValue(),
                    'responseStatus' => 'accepted',
                    'organizer'      => true
                ];

                $organizerId = $provider->getId()->getValue();
            }

            if ($period) {
                $providers = $appointment->getProviders()->getItems();

                if ($providersNew) {
                    $providers = array_merge($providers, $providersNew);
                }
                if ($providersRemove) {
                    $providersRemoveIds = array_map(
                        function ($value) {
                            return $value->getId()->getValue();
                        },
                        $providersRemove
                    );
                }

                /** @var Provider $provider */
                foreach ($providers as $provider) {
                    if (
                        $provider->getId()->getValue() !== $organizerId &&
                        (empty($providersRemoveIds) || !in_array($provider->getId()->getValue(), $providersRemoveIds))
                    ) {
                        $attendees[] = [
                            'displayName'    => $provider->getFirstName()->getValue() . ' ' . $provider->getLastName()->getValue(),
                            'email'          =>
                                $provider->getGoogleCalendar()
                                    ? $provider->getGoogleCalendar()->getCalendarId()->getValue()
                                    : (
                                        $provider->getGoogleCalendarId()
                                            ? $provider->getGoogleCalendarId()->getValue()
                                            : $provider->getEmail()->getValue()
                                ),
                            'responseStatus' => 'accepted'
                        ];
                    }
                }
            }

            /** @var CustomerRepository $customerRepository */
            $customerRepository = $this->container->get('domain.users.customers.repository');

            $bookings = $appointment->getBookings()->getItems();

            /** @var CustomerBooking $booking */
            foreach ($bookings as $booking) {
                $bookingStatus = $booking->getStatus()->getValue();

                if (
                    $bookingStatus === 'approved' ||
                    ($bookingStatus === 'pending' && $this->getInsertPendingAppointments($provider) === true)
                ) {
                    $customer = $customerRepository->getById($booking->getCustomerId()->getValue());

                    if ($customer->getEmail()->getValue()) {
                        $attendees[] = [
                            'displayName'    =>
                                $customer->getFirstName()->getValue() . ' ' . $customer->getLastName()->getValue(),
                            'email'          => $customer->getEmail()->getValue(),
                            'responseStatus' => 'needsAction'
                        ];
                    }
                }
            }
        }

        return $attendees;
    }

    /**
     * Authorize Provider and create Google Calendar service
     *
     * @param Provider $provider
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function authorizeProvider($provider)
    {
        if ($this->googleCalendarSettings['accessToken']) {
            $googleCalendarMiddlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');

            $providerGoogleCalendar = $provider->getGoogleCalendar() ? $provider->getGoogleCalendar()->toArray() : null;
            $client = $googleCalendarMiddlewareService->getClient($providerGoogleCalendar);

            if ($client === null) {
                $this->client = null;
                $this->service = null;
                return false;
            }

            $this->client = $client;
            $this->service = new Calendar($this->client);
            return true;
        }

        if ($provider->getGoogleCalendar()) {
            $token = $provider->getGoogleCalendar()->getToken()
                ? $provider->getGoogleCalendar()->getToken()->getValue()
                : null;

            if (empty($token)) {
                $this->logger->warning(
                    'GoogleCalendar: Provider has no valid token for legacy auth',
                    [
                        'providerId' => $provider->getId()->getValue(),
                    ]
                );
                $this->client = null;
                $this->service = null;
                return false;
            }

            $this->client = new Client();
            $this->client->setClientId($this->googleCalendarSettings['clientID']);
            $this->client->setClientSecret($this->googleCalendarSettings['clientSecret']);

            $this->client->setAccessToken($token);

            try {
                if ($this->client->isAccessTokenExpired()) {
                    $this->refreshToken($provider->getGoogleCalendar());
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'GoogleCalendar: Failed to refresh provider token',
                    [
                        'exception'  => $e,
                        'providerId' => $provider->getId()->getValue(),
                    ]
                );
                $this->client = null;
                $this->service = null;
                return false;
            }

            $this->service = new Calendar($this->client);

            return true;
        }

        $this->client = null;
        $this->service = null;
        return false;
    }

    /**
     * Authorize and create Google Calendar service
     *
     * @param GoogleCalendar $googleCalendar
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function authorize($googleCalendar)
    {
        if ($this->googleCalendarSettings['accessToken']) {
            $googleCalendarMiddlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');

            $providerGoogleCalendar = $googleCalendar ? $googleCalendar->toArray() : null;
            $client = $googleCalendarMiddlewareService->getClient($providerGoogleCalendar);

            if ($client === null) {
                $this->client = null;
                $this->service = null;
                return false;
            }

            $this->client = $client;
            $this->service = new Calendar($this->client);
            return true;
        }

        if ($googleCalendar) {
            $token = $googleCalendar->getToken()
                ? $googleCalendar->getToken()->getValue()
                : null;

            if (empty($token)) {
                $this->logger->warning(
                    'GoogleCalendar: Provider has no valid token for legacy auth',
                    [
                        'calendarId' => $googleCalendar->getId()
                            ? $googleCalendar->getId()->getValue()
                            : null,
                    ]
                );
                $this->client = null;
                $this->service = null;
                return false;
            }

            $this->client = new Client();
            $this->client->setClientId($this->googleCalendarSettings['clientID']);
            $this->client->setClientSecret($this->googleCalendarSettings['clientSecret']);

            $this->client->setAccessToken($token);

            try {
                if ($this->client->isAccessTokenExpired()) {
                    $this->refreshToken($googleCalendar);
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'GoogleCalendar: Failed to refresh provider token',
                    [
                        'exception'  => $e,
                        'calendarId' => $googleCalendar->getId()
                            ? $googleCalendar->getId()->getValue()
                            : null,
                    ]
                );
                $this->client = null;
                $this->service = null;
                return false;
            }

            $this->service = new Calendar($this->client);

            return true;
        }

        $this->client = null;
        $this->service = null;
        return false;
    }

    /**
     * Refresh Authorization Token if it is expired and update it in database.
     *
     * @param GoogleCalendar $googleCalendar
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    private function refreshToken(GoogleCalendar $googleCalendar)
    {
        $this->client->refreshToken($this->client->getRefreshToken());

        $accessToken = $this->client->getAccessToken();

        if (is_array($accessToken)) {
            $accessToken = json_encode($accessToken);
        }

        $googleCalendar->setToken(new Token($accessToken));

        if (!$googleCalendar->getId()) {
            $this->logger->warning(
                'GoogleCalendar: refreshToken skipped DB update — calendar entity has no id'
            );
            return;
        }

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');
        $googleCalendarRepository->update($googleCalendar, $googleCalendar->getId()->getValue());
    }

    /**
     * @param string $eventDateTimeString
     *
     * @return string
     *
     * @throws Exception
     */
    private function getEventDateTimeStringFromEventDateTime($eventDateTimeString)
    {
        $googleEventDateTimeString = \DateTime::createFromFormat("Y-m-d\TH:i:sP", $eventDateTimeString);

        return DateTimeService::getCustomDateTimeFromUtc(
            $googleEventDateTimeString->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );
    }

    /**
     * @param string $eventDateString
     *
     * @return string
     *
     * @throws Exception
     */
    private function getEventDateTimeStringFromEventDate($eventDateString)
    {
        $eventDateTimeInUtc = (new \DateTime($eventDateString . ' 00:00:00', new \DateTimeZone($this->timeZone)))
            ->setTimezone(new \DateTimeZone('UTC'));

        return DateTimeService::getCustomDateTimeFromUtc($eventDateTimeInUtc->format('Y-m-d H:i:s'));
    }

    /**
     * Release the watch a connection owns. Never throws: callers release one connection after another and
     * the rows go whether or not the calendar can be reached, so a failure here must not stop the rest.
     *
     * @param GoogleCalendar $googleCalendar
     *
     * @return void
     */
    public function disconnect($googleCalendar): void
    {
        if (!$googleCalendar->getId() || !$googleCalendar->getSyncId()) {
            return;
        }

        $connectionId = (int)$googleCalendar->getId()->getValue();
        $syncId       = (int)$googleCalendar->getSyncId()->getValue();

        try {
            // Authorizing is best effort: it is what allows the channel to be stopped remotely, but the
            // local attachment has to be released either way or the watch is left owning a row that is
            // about to go.
            $this->authorize($googleCalendar);

            /** @var GoogleCalendarRepository $googleCalendarRepository */
            $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

            $googleCalendarRepository->updateSyncId($connectionId, null);

            $this->detachConnectionFromCalendarSync($connectionId, $syncId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'GoogleCalendar: releasing watch on disconnect failed',
                [
                    'exception'    => $e,
                    'connectionId' => $connectionId,
                    'syncId'       => $syncId,
                    'userId'       => $googleCalendar->getUserId()
                        ? $googleCalendar->getUserId()->getValue()
                        : null,
                ]
            );
        }
    }

    /**
     * Attach a connection to the watch for its calendar, creating both if they do not exist yet.
     *
     * Google delivers every change to every channel watching a calendar, so N connections on one calendar
     * used to mean N pushes and N full events.list calls for the same change. The registry row is what
     * makes them share a single channel and a single cursor.
     *
     * @param GoogleCalendar $newGoogleCalendar
     *
     * @return void
     * @throws ContainerException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function manageChannel($newGoogleCalendar): void
    {
        if (!$this->isCalendarEnabled() || !$newGoogleCalendar->getCalendarId()) {
            return;
        }

        if (!$this->authorize($newGoogleCalendar)) {
            return;
        }

        $calendarSync = $this->attachConnectionToCalendarSync($newGoogleCalendar);

        if (!$calendarSync) {
            return;
        }

        // Someone else's connection already watches this calendar, and its pushes cover this one too.
        if ($calendarSync->hasLiveSubscription(self::CHANNEL_RENEW_MARGIN)) {
            return;
        }

        $this->startChannel($calendarSync, $newGoogleCalendar);
    }

    /**
     * Resolve the registry row for a connection's calendar and point the connection at it.
     *
     * @param GoogleCalendar $googleCalendar
     *
     * @return CalendarSync|null
     * @throws ContainerException
     * @throws QueryExecutionException
     */
    private function attachConnectionToCalendarSync($googleCalendar)
    {
        if (
            !$googleCalendar->getId() ||
            !$googleCalendar->getCalendarId() ||
            !$googleCalendar->getCalendarId()->getValue()
        ) {
            return null;
        }

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        $connectionId = (int)$googleCalendar->getId()->getValue();
        $calendarId   = $googleCalendar->getCalendarId()->getValue();
        $accountId    = $this->resolveAccountId($googleCalendar);

        $calendarSync = $calendarSyncRepository->getOrCreate(
            CalendarSync::TYPE_GOOGLE,
            $accountId,
            $calendarId,
            $connectionId
        );

        if (!$calendarSync || !$calendarSync->getId()) {
            return null;
        }

        $currentSyncId = $googleCalendar->getSyncId() ? (int)$googleCalendar->getSyncId()->getValue() : null;
        $syncId        = (int)$calendarSync->getId()->getValue();

        if ($currentSyncId !== $syncId) {
            // The connection may have been watching a different calendar until now, in which case the old
            // watch has to be released before the new one is taken up.
            if ($currentSyncId) {
                $this->detachConnectionFromCalendarSync($connectionId, $currentSyncId);
            }

            $googleCalendarRepository->updateSyncId($connectionId, $syncId);
            $googleCalendar->setSyncId(new Id($syncId));
        }

        return $calendarSync;
    }

    /**
     * The Google account a calendar id belongs to.
     *
     * Google calendar ids are globally unique, so the account is not needed to tell two calendars apart -
     * it is recorded because it decides who can renew a shared watch. Keying on it means two employees on
     * one shared calendar through separate Google accounts get a channel each rather than one channel that
     * dies when the wrong employee disconnects.
     *
     * When the account cannot be read, the connection gets an identity of its own, which degrades to a
     * channel per connection instead of merging unrelated accounts.
     *
     * @param GoogleCalendar $googleCalendar
     *
     * @return string
     * @throws ContainerException
     * @throws QueryExecutionException
     */
    private function resolveAccountId($googleCalendar)
    {
        if ($googleCalendar->getAccountId()) {
            return $googleCalendar->getAccountId();
        }

        $connectionId = (int)$googleCalendar->getId()->getValue();

        $accountId = $this->fetchPrimaryCalendarAddress();

        if ($accountId === '') {
            $accountId = 'connection:' . $connectionId;
        }

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        $googleCalendarRepository->updateAccountId($connectionId, $accountId);
        $googleCalendar->setAccountId($accountId);

        return $accountId;
    }

    /**
     * The signed-in account's address, which for Google is the id of its primary calendar. Requires an
     * already authorized client.
     *
     * @return string
     */
    private function fetchPrimaryCalendarAddress()
    {
        try {
            $calendarList = $this->service->calendarList->listCalendarList();

            /** @var CalendarListEntry $calendar */
            foreach ($calendarList->getItems() as $calendar) {
                if ($calendar->getPrimary() && $calendar->getId()) {
                    return strtolower(trim((string)$calendar->getId()));
                }
            }
        } catch (Exception $e) {
            $this->logger->warning(
                'GoogleCalendar: could not resolve account for calendar sync',
                ['exception' => $e]
            );
        }

        return '';
    }

    /**
     * Release a connection's claim on a watch, stopping the channel only when nothing else needs it.
     *
     * @param int $connectionId
     * @param int $syncId
     *
     * @return void
     * @throws ContainerException
     */
    private function detachConnectionFromCalendarSync($connectionId, $syncId)
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        try {
            /** @var CalendarSync|null $calendarSync */
            $calendarSync = $calendarSyncRepository->getById($syncId);
        } catch (\Throwable $e) {
            return;
        }

        if (!$calendarSync) {
            return;
        }

        try {
            $remaining = $googleCalendarRepository->getConnectionsBySyncId($syncId, $connectionId);

            if ($remaining) {
                if ((int)$calendarSync->getOwnerConnectionId() === (int)$connectionId) {
                    $calendarSyncRepository->updateOwnerConnectionId($syncId, $remaining[0]['id']);
                }

                return;
            }

            // Employees on the global connection reach a calendar through their user row rather than a
            // connection row, so counting connections alone would tear down a watch they still rely on.
            // The row outlives the last connection and reverts to being owned by the global token.
            if ($providerRepository->getProviderIdsByCalendarId($calendarSync->getCalendarId(), 'googleCalendarId')) {
                if ($calendarSync->getOwnerConnectionId() !== null) {
                    $calendarSyncRepository->updateOwnerConnectionId($syncId, null);
                }

                return;
            }

            $this->stopChannel($calendarSync);

            $calendarSyncRepository->delete($syncId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'GoogleCalendar: releasing watch failed',
                [
                    'exception' => $e,
                    'syncId'    => $syncId,
                ]
            );
        }
    }

    /**
     * The calendar scoped guard: a change may only be applied to a booking whose employee is actually
     * connected to the calendar the push came from.
     *
     * This replaces a comparison against a single employee id, which cannot work once one channel serves
     * several employees sharing a calendar. Employees reach a calendar either through a connection row or,
     * on the global connection, through their user row.
     *
     * @param CalendarSync $calendarSync
     * @param Id|null      $providerId
     *
     * @return bool
     */
    private function isChangeAllowedForCalendar(CalendarSync $calendarSync, $providerId)
    {
        if (!$providerId || !$calendarSync->getId()) {
            return false;
        }

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        try {
            $attached = $googleCalendarRepository->isProviderAttachedToSync(
                (int)$calendarSync->getId()->getValue(),
                (int)$providerId->getValue()
            );

            if ($attached) {
                return true;
            }

            return $providerRepository->isProviderUsingCalendarId(
                (int)$providerId->getValue(),
                $calendarSync->getCalendarId(),
                'googleCalendarId'
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'GoogleCalendar: could not verify calendar ownership for change',
                [
                    'exception'  => $e,
                    'providerId' => $providerId->getValue(),
                ]
            );

            return false;
        }
    }

    /**
     * Value Google echoes on each push as the X-Goog-Channel-Token header (must match watch request).
     *
     * @param string $channelId
     *
     * @return string
     */
    public static function buildGooglePushChannelVerificationToken($channelId)
    {
        $salt = function_exists('wp_salt') ? wp_salt('amelia_google_calendar_push') : 'amelia';

        return hash_hmac('sha256', (string)$channelId, $salt);
    }

    /**
     * @param string      $channelId
     * @param string|null $headerToken Raw X-Goog-Channel-Token header value
     *
     * @return bool
     */
    public static function isGooglePushChannelTokenValid($channelId, $headerToken)
    {
        $expected = self::buildGooglePushChannelVerificationToken($channelId);

        return $headerToken !== null && $headerToken !== '' && hash_equals($expected, (string)$headerToken);
    }

    /**
     * URL Google Calendar calls for push notifications (must be HTTPS where required).
     *
     * In dev (AMELIA_DEV), upgrade http:// on the action URL so local setups can register a watch.
     *
     * @return string
     */
    private function getGoogleCalendarWebhookAddress()
    {
        $actionUrl = AMELIA_ACTION_URL;

        if (AMELIA_NGROK_URL && defined('AMELIA_DEV') && AMELIA_DEV) {
            $ngrok = preg_match('#^https?://#', AMELIA_NGROK_URL) ? AMELIA_NGROK_URL : 'https://' . AMELIA_NGROK_URL;

            $actionUrl = preg_replace('#^https?://[^/]+#', rtrim($ngrok, '/'), $actionUrl);
        }

        return preg_replace('#^http://#', 'https://', $actionUrl) . '/google/watch';
    }

    /**
     * Open a push channel for a calendar and record it on the registry row.
     *
     * @param CalendarSync        $calendarSync
     * @param GoogleCalendar|null $googleCalendar connection driving it, null for the global token
     *
     * @return void
     *
     * @throws Exception
     */
    private function startChannel(CalendarSync $calendarSync, $googleCalendar)
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        $syncId = (int)$calendarSync->getId()->getValue();

        if ($calendarSync->getSubscriptionId() && $calendarSync->getResourceId()) {
            $this->stopChannel($calendarSync);
        }

        // The channel id used to carry the employee id, which no longer means anything for a channel that
        // several employees can share.
        $channelId = (new Token())->getValue() . '-' . $syncId;

        $channel = new Channel();

        $channel->setId($channelId);

        $channel->setType('webhook');

        $channel->setAddress($this->getGoogleCalendarWebhookAddress());

        $channel->setToken(self::buildGooglePushChannelVerificationToken($channelId));

        try {
            $response = $this->service->events->watch($calendarSync->getCalendarId(), $channel);

            $calendarSync->setSubscriptionId($channelId);
            $calendarSync->setResourceId($response->getResourceId());
            $calendarSync->setExpiresAt(gmdate('Y-m-d H:i:s', (int)($response->getExpiration() / 1000)));
        } catch (Exception $e) {
            if ($googleCalendar && $googleCalendar->getUserId()) {
                /** @var ProviderRepository $providerRepository */
                $providerRepository = $this->container->get('domain.users.providers.repository');

                $providerRepository->updateErrorColumn($googleCalendar->getUserId()->getValue(), $e->getMessage());
            }

            $calendarSyncRepository->clearSubscriptionState($syncId);

            return;
        }

        $ownerConnectionId = $googleCalendar && $googleCalendar->getId()
            ? (int)$googleCalendar->getId()->getValue()
            : null;

        $calendarSync->setOwnerConnectionId($ownerConnectionId);

        $calendarSyncRepository->updateSubscriptionState(
            $syncId,
            $calendarSync->getSubscriptionId(),
            $calendarSync->getExpiresAt(),
            null,
            $calendarSync->getResourceId()
        );

        $calendarSyncRepository->updateOwnerConnectionId($syncId, $ownerConnectionId);

        // A fresh channel starts from a clean cursor, and the first listing establishes it.
        $calendarSync->setSyncToken(null);

        $this->getChangedGoogleEvents($calendarSync);

        $calendarSyncRepository->updateSyncToken($syncId, $calendarSync->getSyncToken());
    }

    /**
     * @param CalendarSync $calendarSync
     *
     * @return void
     *
     * @throws Exception
     */
    private function stopChannel(CalendarSync $calendarSync)
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        if (!$calendarSync->getSubscriptionId() || !$calendarSync->getResourceId()) {
            return;
        }

        // Callers are responsible for authorizing the right account first. Without a client there is
        // nothing to send the stop through, and dereferencing one would raise an Error that callers
        // catching Exception would not contain - so the local state is cleared and the remote channel is
        // left to expire on its own.
        if (!$this->service) {
            $this->logger->warning(
                'GoogleCalendar: cannot stop channel without an authorized client, leaving it to expire',
                [
                    'syncId' => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );
        } else {
            $channel = new Channel();

            $channel->setId($calendarSync->getSubscriptionId());

            $channel->setResourceId($calendarSync->getResourceId());

            try {
                $this->service->channels->stop($channel);
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'GoogleCalendar: stopping channel failed',
                    [
                        'exception' => $e,
                        'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                    ]
                );
            }
        }

        $calendarSync->setSubscriptionId(null);
        $calendarSync->setResourceId(null);
        $calendarSync->setExpiresAt(null);
        $calendarSync->setSyncToken(null);

        if ($calendarSync->getId()) {
            $calendarSyncRepository->clearSubscriptionState($calendarSync->getId()->getValue());
            $calendarSyncRepository->updateSyncToken($calendarSync->getId()->getValue(), null);
        }
    }

    /**
     * Watch the calendar an employee selected on the global connection.
     *
     * These employees have no connection row, so the registry row carries no owner and the watch runs on
     * the access token from the plugin settings. Employees are found back from it through their user row.
     *
     * @param int $providerId
     *
     * @return void
     */
    public function ensureTwoWaySyncForGlobalProvider($providerId): void
    {
        if (!$this->isCalendarEnabled() || !$this->isAccessTokenSet()) {
            return;
        }

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        try {
            $provider = $providerRepository->getById($providerId);
        } catch (\Throwable $e) {
            return;
        }

        // A connection of their own takes precedence; that path is handled by manageChannel().
        try {
            if ($providerRepository->getGoogleCalendarAccounts($providerId)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $calendarId = $provider->getGoogleCalendarId() && $provider->getGoogleCalendarId()->getValue()
            ? $provider->getGoogleCalendarId()->getValue()
            : null;

        if (!$calendarId) {
            $this->releaseUnusedGlobalCalendarSyncRows();

            return;
        }

        if (!$this->authorizeProvider($provider)) {
            return;
        }

        $accountId = $this->resolveGlobalAccountId();

        if ($accountId === '') {
            return;
        }

        $calendarSync = $calendarSyncRepository->getOrCreate(
            CalendarSync::TYPE_GOOGLE,
            $accountId,
            $calendarId,
            null
        );

        if (!$calendarSync || !$calendarSync->getId()) {
            return;
        }

        // Anything this employee used to point at may now be unused.
        $this->releaseUnusedGlobalCalendarSyncRows();

        if ($calendarSync->hasLiveSubscription(self::CHANNEL_RENEW_MARGIN)) {
            return;
        }

        try {
            $this->startChannel($calendarSync, null);
        } catch (Exception $e) {
            $this->logger->error(
                'GoogleCalendar: global channel create failed',
                [
                    'exception'  => $e,
                    'providerId' => $providerId,
                    'calendarId' => $calendarId,
                ]
            );
        }
    }

    /**
     * The account behind the global access token. There is no connection row to cache it on, so it is
     * kept in the plugin settings next to the token it belongs to.
     *
     * @return string
     */
    private function resolveGlobalAccountId()
    {
        $stored = !empty($this->googleCalendarSettings['accountId'])
            ? (string)$this->googleCalendarSettings['accountId']
            : '';

        if ($stored !== '') {
            return $stored;
        }

        $accountId = $this->fetchPrimaryCalendarAddress();

        if ($accountId === '') {
            return '';
        }

        $this->settings->setSetting('googleCalendar', 'accountId', $accountId);
        $this->googleCalendarSettings['accountId'] = $accountId;

        return $accountId;
    }

    /**
     * Drop global watches nothing points at any more, which is how a cleared or changed calendar
     * selection gets cleaned up - there is no connection row whose removal would signal it.
     *
     * @return void
     */
    private function releaseUnusedGlobalCalendarSyncRows()
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        try {
            $rows = $calendarSyncRepository->getAllByType(CalendarSync::TYPE_GOOGLE);
        } catch (\Throwable $e) {
            return;
        }

        /** @var CalendarSync $calendarSync */
        foreach ($rows->getItems() as $calendarSync) {
            if ($calendarSync->getOwnerConnectionId() || !$calendarSync->getId()) {
                continue;
            }

            $syncId = (int)$calendarSync->getId()->getValue();

            try {
                $employees = $providerRepository->getProviderIdsByCalendarId(
                    $calendarSync->getCalendarId(),
                    'googleCalendarId'
                );

                if ($employees || $googleCalendarRepository->getConnectionsBySyncId($syncId)) {
                    continue;
                }

                // Authorize per row rather than relying on the caller: this runs before any authorization
                // when a calendar selection is cleared, and the rows in this loop can belong to different
                // accounts, so an ambient credential would be missing or the wrong one.
                $this->authorizeForTeardown($calendarSync);

                $this->stopChannel($calendarSync);

                $calendarSyncRepository->delete($syncId);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'GoogleCalendar: releasing unused global watch failed',
                    [
                        'exception' => $e,
                        'syncId'    => $syncId,
                    ]
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * Iterates watched calendars rather than connections, so a calendar shared by several employees is
     * renewed once instead of once per employee attached to it.
     */
    public function renewChannels(): void
    {
        if (!$this->isCalendarEnabled()) {
            return;
        }

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var Collection $calendarSyncRows */
        $calendarSyncRows = $calendarSyncRepository->getAllByType(CalendarSync::TYPE_GOOGLE);

        /** @var CalendarSync $calendarSync */
        foreach ($calendarSyncRows->getItems() as $calendarSync) {
            $syncId = $calendarSync->getId() ? (int)$calendarSync->getId()->getValue() : null;

            if (!$syncId) {
                continue;
            }

            try {
                $connections = $googleCalendarRepository->getConnectionsBySyncId($syncId);

                // Employees on the global connection reach a calendar through their user row rather than
                // a connection row, so they are counted before anything is torn down. Asking only when
                // ownerConnectionId is null would miss the row whose owning connection has been deleted:
                // it looks unused although those employees still rely on it.
                if (!$connections) {
                    $globalEmployees = $providerRepository->getProviderIdsByCalendarId(
                        $calendarSync->getCalendarId(),
                        'googleCalendarId'
                    );

                    if (!$globalEmployees) {
                        // Nothing uses this calendar any more. Authorize before the stop: at this point in
                        // the loop $this->service is either unset or still pointing at the previous
                        // calendar's account.
                        $this->authorizeForTeardown($calendarSync);

                        $this->stopChannel($calendarSync);

                        $calendarSyncRepository->delete($syncId);

                        continue;
                    }

                    // The row outlives the last connection and reverts to being owned by the global
                    // token, which is also what lets authorizeForCalendarSync() renew it below.
                    if ($calendarSync->getOwnerConnectionId() !== null) {
                        $calendarSyncRepository->updateOwnerConnectionId($syncId, null);
                        $calendarSync->setOwnerConnectionId(null);
                    }
                }

                // Checked after the orphan case, so a watch nobody uses is released promptly rather
                // than lingering until it comes up for renewal.
                if ($calendarSync->hasLiveSubscription(self::CHANNEL_RENEW_MARGIN)) {
                    continue;
                }

                if ($connections) {
                    $owner = $this->pickOwnerConnection($calendarSync, $connections);

                    if ($owner && (int)$owner['id'] !== (int)$calendarSync->getOwnerConnectionId()) {
                        $calendarSyncRepository->updateOwnerConnectionId($syncId, $owner['id']);
                        $calendarSync->setOwnerConnectionId($owner['id']);
                    }
                }

                $googleCalendar = $this->authorizeForCalendarSync($calendarSync);

                if ($googleCalendar === false) {
                    continue;
                }

                $this->startChannel($calendarSync, $googleCalendar);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'GoogleCalendar: renew channel failed',
                    [
                        'exception' => $e,
                        'syncId'    => $syncId,
                    ]
                );
            }
        }
    }

    /**
     * Prefer the connection that already drives the watch, falling back to any other still attached.
     *
     * @param CalendarSync $calendarSync
     * @param array        $connections list of ['id' => int, 'userId' => int]
     *
     * @return array|null
     */
    private function pickOwnerConnection(CalendarSync $calendarSync, array $connections)
    {
        $ownerConnectionId = $calendarSync->getOwnerConnectionId();

        if ($ownerConnectionId) {
            foreach ($connections as $connection) {
                if ((int)$connection['id'] === (int)$ownerConnectionId) {
                    return $connection;
                }
            }
        }

        return $connections ? $connections[0] : null;
    }

    /**
     * Authorize purely in order to tear a watch down.
     *
     * This is not the same question as authorizing to renew: a watch being removed has, by definition,
     * nobody left using it, so authorizeForCalendarSync() would refuse it. Any credential for the right
     * account will do to send the stop - the owning connection if its row still exists, otherwise the
     * global token.
     *
     * @param CalendarSync $calendarSync
     *
     * @return bool whether a client is now authorized
     */
    private function authorizeForTeardown(CalendarSync $calendarSync)
    {
        try {
            $ownerConnectionId = $calendarSync->getOwnerConnectionId();

            if ($ownerConnectionId) {
                $googleCalendar = $this->loadConnectionOrNull($ownerConnectionId);

                if ($googleCalendar) {
                    return (bool)$this->authorize($googleCalendar);
                }
            }

            // No owning connection left. The global token belongs to the same account for a watch that
            // was created on it, and is the only credential still available for one that was not.
            return $this->isAccessTokenSet() ? (bool)$this->authorize(null) : false;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'GoogleCalendar: could not authorize to stop a channel',
                [
                    'exception' => $e,
                    'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );

            return false;
        }
    }

    /**
     * @param int $connectionId
     *
     * @return GoogleCalendar|null null when the row has since been deleted
     */
    private function loadConnectionOrNull($connectionId)
    {
        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        try {
            return $googleCalendarRepository->getById($connectionId);
        } catch (NotFoundException $e) {
            return null;
        }
    }

    /**
     * Authorize with whatever credential drives a watch: the owning connection's token, or the global one
     * from the plugin settings when the watch has no owner.
     *
     * @param CalendarSync $calendarSync
     *
     * @return GoogleCalendar|null|false the connection driving the watch, null when it runs on the global
     *                                   token, false when authorization failed
     */
    private function authorizeForCalendarSync(CalendarSync $calendarSync)
    {
        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $ownerConnectionId = $calendarSync->getOwnerConnectionId();
        $syncId            = (int)$calendarSync->getId()->getValue();

        try {
            if ($ownerConnectionId) {
                $googleCalendar = $this->loadConnectionOrNull($ownerConnectionId);

                if ($googleCalendar) {
                    return $this->authorize($googleCalendar) ? $googleCalendar : false;
                }

                // The owning connection is gone - a disconnect that removed the row without releasing the
                // watch. Hand the watch to another connection still attached, or let it fall through to
                // the global token below.
                $remaining = $googleCalendarRepository->getConnectionsBySyncId($syncId);

                if ($remaining) {
                    /** @var CalendarSyncRepository $calendarSyncRepository */
                    $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

                    $calendarSyncRepository->updateOwnerConnectionId($syncId, $remaining[0]['id']);
                    $calendarSync->setOwnerConnectionId($remaining[0]['id']);

                    $googleCalendar = $this->loadConnectionOrNull($remaining[0]['id']);

                    if (!$googleCalendar) {
                        return false;
                    }

                    return $this->authorize($googleCalendar) ? $googleCalendar : false;
                }

                $this->logger->warning(
                    'GoogleCalendar: watch had no surviving connection, falling back to the global token',
                    ['syncId' => $syncId]
                );
            }

            if (!$this->isAccessTokenSet()) {
                return false;
            }

            $employees = $providerRepository->getProviderIdsByCalendarId(
                $calendarSync->getCalendarId(),
                'googleCalendarId'
            );

            if (!$employees) {
                return false;
            }

            $provider = $providerRepository->getById($employees[0]);

            return $this->authorizeProvider($provider) ? null : false;
        } catch (\Throwable $e) {
            $this->logger->error(
                'GoogleCalendar: authorize for calendar sync failed',
                [
                    'exception' => $e,
                    'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );

            return false;
        }
    }

    /**
     * @param CalendarSync $calendarSync
     *
     * @return array
     *
     * @throws Exception
     */
    private function getChangedGoogleEvents(CalendarSync $calendarSync)
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        $list = [];

        $params = [
            'singleEvents' => true,
            'showDeleted'  => true,
            'maxResults'   => 2500,
        ];

        if ($calendarSync->getSyncToken()) {
            $params['syncToken'] = $calendarSync->getSyncToken();
        } else {
            $params['timeMin'] = (new \DateTime('now', new \DateTimeZone('UTC')))->format('c');
        }

        try {
            $events = $this->service->events->listEvents($calendarSync->getCalendarId(), $params);

            $list = $events->getItems();

            while (true) {
                $pageToken = $events->getNextPageToken();

                if (!$pageToken) {
                    break;
                }

                $events = $this->service->events->listEvents(
                    $calendarSync->getCalendarId(),
                    array_merge($params, ['pageToken' => $pageToken])
                );

                $list = array_merge($list, $events->getItems());
            }

            $calendarSync->setSyncToken($events->getNextSyncToken() ?: null);
        } catch (\Exception $e) {
            // 410 means the cursor is too old to be useful; a full listing rebuilds it.
            if ($e->getCode() === 410) {
                $calendarSync->setSyncToken(null);

                $list = $this->getChangedGoogleEvents($calendarSync);

                if ($calendarSync->getId() && $calendarSync->getSyncToken()) {
                    $calendarSyncRepository->updateSyncToken(
                        $calendarSync->getId()->getValue(),
                        $calendarSync->getSyncToken()
                    );
                }

                return $list;
            }

            $this->logger->error(
                'GoogleCalendar: listing changed events failed',
                [
                    'exception' => $e,
                    'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );
        }

        return $list;
    }

    /**
     * @param CalendarSync $calendarSync
     * @param string       $resourceId
     *
     * @return void
     *
     * @throws Exception
     */
    public function manageChangedEvents($calendarSync, $resourceId): void
    {
        if (!$this->isCalendarEnabled() || !$calendarSync->getId()) {
            return;
        }

        if ($this->authorizeForCalendarSync($calendarSync) === false) {
            return;
        }

        /** @var CalendarSyncLockRepository $syncLockRepository */
        $syncLockRepository = $this->container->get('domain.calendar.sync.lock.repository');

        // Google notifies every channel watching the calendar, and the change set is read from a cursor stored on
        // the row, so two overlapping deliveries would list from the same cursor and replay the same events.
        $lockKey = 'google:calendar:' . $calendarSync->getId()->getValue();

        $lock = $syncLockRepository->acquire($lockKey);

        if ($lock === CalendarSyncLockRepository::RESULT_CONTENDED) {
            // Another delivery has been working on this calendar for longer than the wait allows, so it is
            // still mid-flight. Running alongside it would apply the same change twice, and re-reading the
            // cursor cannot help because it has not written one yet. The change is not lost: the cursor
            // still predates it, so the next delivery for this calendar picks it up.
            $this->logger->info(
                'GoogleCalendar: skipped push, another delivery holds the calendar',
                [
                    'syncId'     => $calendarSync->getId()->getValue(),
                    'resourceId' => $resourceId,
                ]
            );

            return;
        }

        try {
            $this->processChangedGoogleEvents($calendarSync, $resourceId);
        } finally {
            if ($lock === CalendarSyncLockRepository::RESULT_ACQUIRED) {
                $syncLockRepository->release($lockKey);
            }
        }
    }

    /**
     * Amelia-initiated deletes (pending without insertPending, cancel, reject) must not be treated
     * as a Google-side cancellation of an approved appointment.
     */
    private function shouldIgnoreGoogleCancellationForAppointment(Appointment $appointment): bool
    {
        return in_array(
            $appointment->getStatus()->getValue(),
            [BookingStatus::PENDING, BookingStatus::CANCELED, BookingStatus::REJECTED],
            true
        );
    }

    /**
     * @param CalendarSync $calendarSync
     * @param string       $resourceId
     *
     * @return void
     *
     * @throws Exception
     */
    private function processChangedGoogleEvents(CalendarSync $calendarSync, $resourceId)
    {
        /** @var CalendarApplicationService $calendarApplicationService */
        $calendarApplicationService = $this->container->get('application.calendar.service');

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        $syncId = (int)$calendarSync->getId()->getValue();

        // The in memory cursor was read before the lock was held, so a delivery that ran in the meantime may
        // already have advanced it. Reading it again keeps this run from replaying events it just handled.
        $calendarSync->setSyncToken($calendarSyncRepository->getSyncTokenById($syncId));

        $this->timeZone = $this->service->calendars->get($calendarSync->getCalendarId())->getTimeZone() ?: 'UTC';

        $googleEvents = $this->getChangedGoogleEvents($calendarSync);

        if ($calendarSync->getSyncToken()) {
            $calendarSyncRepository->updateSyncToken($syncId, $calendarSync->getSyncToken());
        }

        $type = 'googleCalendar';

        foreach ($googleEvents as $googleEvent) {
            if ($googleEvent->getStatus() === 'cancelled') {
                $appointment = $calendarApplicationService->getChangedAppointment(
                    $googleEvent->getId(),
                    'googleCalendarEventId'
                );

                if ($appointment && $this->shouldIgnoreGoogleCancellationForAppointment($appointment)) {
                    continue;
                }

                if (
                    $appointment &&
                    $this->isChangeAllowedForCalendar($calendarSync, $appointment->getProviderId())
                ) {
                    $calendarApplicationService->handleAppointmentReject($appointment, $type);
                    continue;
                }

                $eventPeriod = $calendarApplicationService->getChangedEventPeriod(
                    $googleEvent->getId(),
                    'googleCalendarEventId'
                );

                $event = $eventPeriod ? $calendarApplicationService->getChangedEvent($eventPeriod) : null;

                if (
                    $event &&
                    $this->isChangeAllowedForCalendar($calendarSync, $event->getOrganizerId())
                ) {
                    $calendarApplicationService->handleEventPeriodDelete($event, $eventPeriod, $type);
                }

                continue;
            }

            /** @var Appointment|null $appointment */
            $appointment = $calendarApplicationService->getChangedAppointment(
                $googleEvent->getId(),
                'googleCalendarEventId'
            );

            /** @var EventPeriod|null $eventPeriod */
            $eventPeriod = null;
            if (!$appointment) {
                $eventPeriod = $calendarApplicationService->getChangedEventPeriod(
                    $googleEvent->getId(),
                    'googleCalendarEventId'
                );
            }

            if (!$appointment && !$eventPeriod) {
                // Intentional no-op for Google-native events; hook for metrics or future import.
                do_action('amelia_google_calendar_push_unmatched_event', $googleEvent, $calendarSync, $resourceId);

                $this->logger->info(
                    'GoogleCalendar: push sync skipped non-Amelia event (by design)',
                    [
                        'eventId'    => $googleEvent->getId(),
                        'syncId'     => $syncId,
                        'resourceId' => $resourceId,
                    ]
                );

                continue;
            }

            if ($googleEvent->getStart()->dateTime === null) {
                $startString = $this->getEventDateTimeStringFromEventDate($googleEvent->getStart()->date);

                $endString = $this->getEventDateTimeStringFromEventDate($googleEvent->getEnd()->date);
            } else {
                $startString = $this->getEventDateTimeStringFromEventDateTime($googleEvent->getStart()->dateTime);

                $endString = $this->getEventDateTimeStringFromEventDateTime($googleEvent->getEnd()->dateTime);
            }

            /** @var Event|null $event */
            $event = $eventPeriod ? $calendarApplicationService->getChangedEvent($eventPeriod) : null;

            if (
                $appointment &&
                $this->isChangeAllowedForCalendar($calendarSync, $appointment->getProviderId())
            ) {
                $calendarApplicationService->handleAppointmentReschedule(
                    $appointment,
                    $startString,
                    $endString,
                    $type
                );
            }

            if (
                $event &&
                $this->isChangeAllowedForCalendar($calendarSync, $event->getOrganizerId())
            ) {
                $calendarApplicationService->handleEventPeriodReschedule(
                    $event,
                    $eventPeriod,
                    $startString,
                    $endString,
                    $type
                );
            }
        }
    }
}
