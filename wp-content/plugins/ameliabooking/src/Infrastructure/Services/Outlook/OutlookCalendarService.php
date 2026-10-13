<?php

namespace AmeliaBooking\Infrastructure\Services\Outlook;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Calendar\CalendarApplicationService;
use AmeliaBooking\Application\Services\CustomField\AbstractCustomFieldApplicationService;
use AmeliaBooking\Application\Services\Placeholder\PlaceholderService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Entity\Calendar\CalendarSync;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\EventPeriod;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Booking\Appointment\AppointmentFactory;
use AmeliaBooking\Domain\Factory\Outlook\OutlookCalendarFactory;
use AmeliaBooking\Domain\Factory\User\ProviderFactory;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\Label;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Calendar\CalendarSyncLockRepository;
use AmeliaBooking\Infrastructure\Repository\Calendar\CalendarSyncRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventPeriodsRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Event\EventRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\Outlook\OutlookCalendarRepository;
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
use DateInvalidTimeZoneException;
use DateTime;
use Exception;
use Interop\Container\Exception\ContainerException;
use Microsoft\Kiota\Abstractions\ApiException as GraphException;
use Microsoft\Graph\Generated\Models\Attendee;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\Calendar;
use Microsoft\Graph\Generated\Models\DateTimeTimeZone;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\FreeBusyStatus;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Location;
use Microsoft\Graph\Generated\Models\OnlineMeetingProviderType;
use Microsoft\Graph\Generated\Models\OutlookGeoCoordinates;
use Microsoft\Graph\Generated\Models\PatternedRecurrence;
use Microsoft\Graph\Generated\Models\PhysicalAddress;
use Microsoft\Graph\Generated\Models\SingleValueLegacyExtendedProperty;
use Microsoft\Graph\Generated\Models\Subscription;
use Microsoft\Graph\Generated\Models\User;
use WP_Error;

/**
 * Class OutlookCalendarService
 *
 * @package AmeliaBooking\Infrastructure\Services\Outlook
 */
class OutlookCalendarService extends AbstractOutlookCalendarService
{
    /** @var OutlookGraphClient */
    private $graph;

    /**
     * Whether $graph currently carries a usable access token.
     *
     * The client object itself is built in the constructor and never replaced with null, so its presence
     * says nothing about authorization - this is what remote calls have to consult.
     *
     * @var bool
     */
    private $graphAuthorized = false;

    /** @var mixed */
    private $outlookCalendarSettings;

    /** Renew a subscription once it is inside this window of expiring, in seconds. */
    private const SUBSCRIPTION_RENEW_MARGIN = 86400;

    /** @var SettingsService */
    private $settings;

    /** @var LoggerInterface */
    private $logger;

    /**
     * OutlookCalendarService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $this->container->get('domain.settings.service');
        $this->logger = $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC);
        $this->outlookCalendarSettings = $this->settings->getCategorySettings('outlookCalendar');

        $this->graph = new OutlookGraphClient();
    }

    /**
     * @return void
     */
    public function getOutlookAction(): void
    {
        add_action('init', function () {
            $path = isset($_SERVER['REQUEST_URI'])
                ? (string)wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
                : '';

            // The notification URL is built from the home URL, so on a subdirectory
            // install the path arrives prefixed (e.g. /ameliaDev/wpamelia/...).
            // Accept the root-relative form too, in case a filter overrides the URL.
            $homePath = (string)wp_parse_url(AMELIA_HOME_URL, PHP_URL_PATH);

            $accepted = array_unique(
                [
                    rtrim($homePath, '/') . '/wpamelia/outlook/notification',
                    '/wpamelia/outlook/notification',
                ]
            );

            if (!in_array(untrailingslashit($path), $accepted, true)) {
                return;
            }

            if (isset($_GET['validationToken'])) {
                header('Content-Type: text/plain; charset=utf-8');
                echo (string)$_GET['validationToken'];
                exit;
            }

            $_GET['action'] = 'wpamelia_api';
            $_GET['call'] = '/outlook/notification';
            $_REQUEST['action'] = 'wpamelia_api';
            $_REQUEST['call'] = '/outlook/notification';
            $_SERVER['QUERY_STRING'] = AMELIA_ACTION_SLUG . '/outlook/notification';

            do_action('wp_ajax_wpamelia_api');
            do_action('wp_ajax_nopriv_wpamelia_api');
            exit;
        });
    }

    /**
     * Create a URL to obtain user authorization.
     *
     * @param $providerId
     *
     * @return string
     *
     * @throws ContainerException
     */
    public function createAuthUrl($providerId): string
    {
        $params = [
            'client_id'     => $this->outlookCalendarSettings['clientID'],
            'response_type' => 'code',
            'redirect_uri'  => !AMELIA_DEV
                ? str_replace('http://', 'https://', $this->outlookCalendarSettings['redirectURI'])
                : $this->outlookCalendarSettings['redirectURI'],
            'scope'         => $providerId
                ? 'offline_access calendars.readwrite'
                : 'offline_access calendars.readwrite mail.send',
            'response_mode' => 'query',
            'state'         => 'amelia-outlook-calendar-auth-' . $providerId,
            'prompt'        => 'select_account',
        ];

        return add_query_arg(
            urlencode_deep($params),
            'https://login.microsoftonline.com/common/oauth2/v2.0/authorize'
        );
    }

    /**
     * @return void
     */
    public static function handleCallback(): void
    {
        if (isset($_REQUEST['code'], $_REQUEST['state']) && !isset($_REQUEST['scope']) && !isset($_REQUEST['type']) && !isset($_REQUEST['response_type'])) {
            $id = (int)substr($_REQUEST['state'], strrpos($_REQUEST['state'], '-') + 1);

            wp_redirect(
                add_query_arg(
                    urlencode_deep(
                        [
                        'code'  => esc_attr($_REQUEST['code']),
                        'state' => esc_attr($_REQUEST['state']),
                        'type'  => 'outlook'
                        ]
                    ),
                    admin_url($id ? 'admin.php?page=wpamelia-employees#/manage/' . $id . '/integrations/outlook-calendar' : 'admin.php?page=wpamelia-settings')
                )
            );
        }
    }

    /**
     * @param $authCode
     * @param $redirectUri
     * @param $providerId
     *
     * @return array
     */
    public function fetchAccessTokenWithAuthCode($authCode, $redirectUri, $providerId): array
    {
        $redirectUrl = empty($redirectUri) ? $this->outlookCalendarSettings['redirectURI'] : explode('?', $redirectUri)[0];

        $response = wp_remote_post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'timeout' => 25,
                'body'    => [
                    'client_id'     => $this->outlookCalendarSettings['clientID'],
                    'client_secret' => $this->outlookCalendarSettings['clientSecret'],
                    'grant_type'    => 'authorization_code',
                    'code'          => $authCode,
                    'redirect_uri'  => !AMELIA_DEV
                        ? str_replace('http://', 'https://', $redirectUrl)
                        : $redirectUrl,
                    'scope'         => $providerId
                        ? 'offline_access calendars.readwrite'
                        : 'offline_access calendars.readwrite mail.send',
                ]
            ]
        );

        if ($response instanceof WP_Error) {
            return [];
        }

        if ($response['response']['code'] !== 200) {
            $error = json_decode($response['body'], true);
            return [
                'outcome' => false,
                'result'  => $error['error_description']
            ];
        }

        $decodedToken = json_decode($response['body'], true);

        $decodedToken['created'] = time();

        return ['outcome' => true, 'result' => json_encode($decodedToken)];
    }

    private function isAccessTokenSet(): bool
    {
        return (
            array_key_exists('accessToken', $this->outlookCalendarSettings) &&
            !empty($this->outlookCalendarSettings['accessToken']) &&
            $this->settings->isFeatureEnabled('outlookCalendar'));
    }

    /**
     * @param string $token
     *
     * @return string
     * @throws ContainerException
     * @throws Exception
     */
    private function authorize($token): string
    {
        try {
            if (empty($token)) {
                $this->logger->warning(
                    'OutlookCalendar: Empty token provided to authorize()'
                );
                return '';
            }

            if ($expiredToken = $this->isAccessTokenExpired($token)) {
                $token = $this->refreshToken($token);

                if (empty($token)) {
                    $this->logger->warning(
                        'OutlookCalendar: Failed to refresh expired token'
                    );
                    return '';
                }
            }

            $tokenArray = json_decode($token, true);

            if (!$tokenArray || !isset($tokenArray['access_token'])) {
                $this->logger->warning(
                    'OutlookCalendar: Invalid token format - missing access_token'
                );
                return '';
            }

            $this->graph->setAccessToken($tokenArray['access_token']);

            $this->graphAuthorized = true;

            return $expiredToken ? $token : '';
        } catch (\Exception $e) {
            $this->logger->error(
                'OutlookCalendar: Error in authorize()',
                [
                    'exception' => $e,
                ]
            );
            return '';
        }
    }

    /**
     * @return void
     * @throws ContainerException
     * @throws Exception
     */
    private function authorizeAdmin(): void
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $outlookSettings */
        $outlookSettings = $settingsService->getCategorySettings('outlookCalendar');

        if ($token = $this->authorize(json_encode($outlookSettings['token']))) {
            $settings = $settingsService->getAllSettingsCategorized();

            $settings['outlookCalendar']['token'] = json_decode($token, true);

            $settingsService->setAllSettings($settings);
        }
    }

    /**
     * @param Provider $provider
     *
     * @return bool
     * @throws ContainerException
     * @throws Exception
     */
    private function authorizeProvider(Provider $provider): bool
    {
        // Any exit from here decides whether remote calls may run, so the flag is reset up front rather
        // than left holding the previous mailbox's outcome.
        $this->graphAuthorized = false;

        if ($this->isAccessTokenSet()) {
            /** @var OutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
            $outlookCalendarMiddlewareService = $this->container->get(
                'infrastructure.outlook.calendar.middleware.service'
            );

            // Pass provider's calendar data - middleware will use provider's token if available
            $providerOutlookCalendar = $provider->getOutlookCalendar() ? $provider->getOutlookCalendar()->toArray() : null;
            $graph = $outlookCalendarMiddlewareService->getGraph($providerOutlookCalendar);

            if ($graph === null) {
                $this->graphAuthorized = false;

                return false;
            }

            $this->graph = $graph;
            $this->graphAuthorized = true;

            return true;
        }

        if ($this->isCalendarEnabled() && $provider->getOutlookCalendar()) {
            try {
                $token = $provider->getOutlookCalendar()->getToken()->getValue();

                if (empty($token)) {
                    $this->logger->warning(
                        'OutlookCalendar: Empty token for provider',
                        [
                            'providerId' => $provider->getId()->getValue(),
                        ]
                    );
                    return false;
                }

                if ($authorizedToken = $this->authorize($token)) {
                    /** @var ProviderApplicationService $providerApplicationService */
                    $providerApplicationService = $this->container->get('application.user.provider.service');

                    $outlookCalendarData = $provider->getOutlookCalendar()->toArray();
                    $outlookCalendarData['token'] = $authorizedToken;

                    $provider->setOutlookCalendar(
                        OutlookCalendarFactory::create($outlookCalendarData)
                    );

                    $providerApplicationService->updateProviderOutlookCalendar($provider);
                }

                // authorize() returns '' both when it succeeds without needing a refresh and when it
                // fails, so its return value cannot be tested for success. The flag is the only thing
                // that distinguishes them, and callers must agree with what remote calls will see.
                return $this->graphAuthorized;
            } catch (\Exception $e) {
                $this->logger->error(
                    'OutlookCalendar: Failed to authorize provider',
                    [
                        'exception'  => $e,
                        'providerId' => $provider->getId()->getValue(),
                    ]
                );
                return false;
            }
        }

        return false;
    }

    /**
     * Get insertPendingAppointments
     *
     * @param Provider $provider
     * @return bool
     */
    private function getInsertPendingAppointments($provider): bool
    {
        if ($provider && $provider->getOutlookCalendar()) {
            return (bool)$provider->getOutlookCalendar()->getInsertPendingAppointments();
        }

        return (bool)($this->outlookCalendarSettings['insertPendingAppointments'] ?? false);
    }

    /**
     * Get includeBufferTime setting
     *
     * @param Provider $provider
     * @return bool
     */
    private function getIncludeBufferTime($provider): bool
    {
        if ($provider && $provider->getOutlookCalendar()) {
            return (bool)$provider->getOutlookCalendar()->getIncludeBufferTime();
        }

        return (bool)($this->outlookCalendarSettings['includeBufferTimeOutlookCalendar'] ?? false);
    }

    /**
     * @param Provider $provider
     *
     * @return array
     * @throws ContainerException
     * @throws GraphException
     * @throws QueryExecutionException
     */
    public function listCalendarList($provider): array
    {
        $calendars = [];

        if ($provider && $provider->getOutlookCalendar() && ($this->isCalendarEnabled() || $this->isAccessTokenSet())) {
            if (!$this->authorizeProvider($provider)) {
                return $calendars;
            }

            $outlookCalendars = $this->graph
                ->createCollectionRequest('GET', '/me/calendars')
                ->setReturnType(Calendar::class)
                ->setPageSize(100)
                ->getPage();

            /** @var Calendar $outlookCalendar */
            foreach ($outlookCalendars as $outlookCalendar) {
                if ($outlookCalendar->getCanEdit() !== false) {
                    $calendars[] = [
                        'id'        => $outlookCalendar->getId(),
                        'name'      => $outlookCalendar->getName(),
                        'owner'     => $outlookCalendar->getOwner() ? $outlookCalendar->getOwner()->getName() : '',
                        'isDefault' => $outlookCalendar->getIsDefaultCalendar() === true,
                    ];
                }
            }
        }

        return $calendars;
    }

    /**
     * Get Outlook account user info
     *
     * @param Provider $provider
     *
     * @return array
     * @throws Exception
     */
    public function getUserInfo($provider): array
    {
        if (!$provider || !$provider->getOutlookCalendar()) {
            return [];
        }

        if (!$this->authorizeProvider($provider)) {
            return [];
        }

        ['name' => $name, 'email' => $email] = $this->fetchMailboxIdentity();

        if (!$name && !$email) {
            return [];
        }

        // Keep the same shape as OutlookCalendarMiddlewareService::getUserInfo(),
        // whose callers read a "picture" key
        return [
            'name'    => $name,
            'email'   => $email,
            'picture' => null,
        ];
    }

    /**
     * Name and address of the mailbox the current Graph credential belongs to.
     *
     * Kept separate from getUserInfo() because that one is about a provider's own connection, while the
     * mailbox behind a token also has to be resolvable for employees who have no connection row at all.
     * Requires an already authorized $this->graph.
     *
     * @return array{name: string|null, email: string|null}
     */
    private function fetchMailboxIdentity(): array
    {
        $name  = null;
        $email = null;

        // Attempt 1: /me — lightweight, but requires the User.Read scope, which the
        // connect flow does not request (see createAuthUrl), so this usually fails.
        try {
            $user = $this->graph->createRequest('GET', '/me')
                ->setReturnType(User::class)
                ->execute();

            $name  = $user->getDisplayName();
            $email = $user->getMail() ?? $user->getUserPrincipalName();
        } catch (GraphException $e) {
            $this->logger->warning(
                'OutlookCalendar: /me failed (token may lack User.Read), falling back to /me/calendars',
                ['exception' => $e]
            );
        }

        // Attempt 2: /me/calendars — works with the Calendars.ReadWrite scope alone.
        if (!$name || !$email) {
            try {
                $outlookCalendars = $this->graph
                    ->createCollectionRequest('GET', '/me/calendars')
                    ->setReturnType(Calendar::class)
                    ->setPageSize(10)
                    ->getPage();

                /** @var Calendar $outlookCalendar */
                foreach ($outlookCalendars as $outlookCalendar) {
                    $owner = $outlookCalendar->getOwner();

                    if (!$owner) {
                        continue;
                    }

                    if (!$name && $owner->getName()) {
                        $name = $owner->getName();
                    }

                    if (!$email && $owner->getAddress()) {
                        $email = $owner->getAddress();
                    }

                    if ($name && $email) {
                        break;
                    }
                }
            } catch (GraphException $e) {
                $this->logger->error(
                    'OutlookCalendar: Failed to fetch user info',
                    ['exception' => $e]
                );
            }
        }

        return ['name' => $name, 'email' => $email];
    }

    /**
     * Get Provider's Outlook Calendar ID.
     *
     * @param Provider $provider
     *
     * @return null|string
     * @throws GraphException|ContainerException|QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function getProviderOutlookCalendarId($provider): ?string
    {
        if (!$this->isCalendarEnabled()) {
            return null;
        }

        // If Outlook Calendar ID is not set, take the primary calendar and save it as Provider's Outlook Calendar ID
        if ($provider && $provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue() === null) {
            $calendarList = $this->listCalendarList($provider);

            /** @var ProviderApplicationService $providerApplicationService */
            $providerApplicationService = $this->container->get('application.user.provider.service');

            $provider->getOutlookCalendar()->setCalendarId(new Label($calendarList[0]['id']));

            $providerApplicationService->updateProviderOutlookCalendar($provider);

            return $provider->getOutlookCalendar()->getCalendarId()->getValue();
        }

        // If Outlook Calendar is set, return it
        if ($provider && $provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue() !== null) {
            return $provider->getOutlookCalendar()->getCalendarId()->getValue();
        }

        return null;
    }


    /**
     * Handle Google Calendar Event's.
     *
     * @param Appointment $appointment
     * @param string      $commandSlug
     *
     * @return void
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handleEvent($appointment, $commandSlug, $oldStatus = null): void
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
     * Handle Google Calendar Events.
     *
     * @param \AmeliaBooking\Domain\Entity\Booking\Event\Event $event
     * @param string $commandSlug
     * @param Collection $periods
     * @param array $newProviders
     * @param array $removeProviders
     *
     * @return void
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handleEventPeriod($event, $commandSlug, $periods, $newProviders = null, $removeProviders = null): void
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        try {
            $this->handleEventPeriodAction($event, $commandSlug, $periods, $newProviders, $removeProviders);
        } catch (Exception $e) {
            /** @var EventRepository $eventRepository */
            $eventRepository = $this->container->get('domain.booking.event.repository');

            $eventRepository->updateErrorColumn($event->getId()->getValue(), $e->getMessage());
        }
    }

    /**
     * @param Appointment $appointment
     * @param string      $commandSlug
     * @param null|string $oldStatus
     *
     * @return void
     * @throws ContainerException
     * @throws GraphException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    private function handleEventAction($appointment, $commandSlug, $oldStatus = null): void
    {
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $appointmentStatus = $appointment->getStatus()->getValue();

        $provider = $providerRepository->getById($appointment->getProviderId()->getValue());

        if (
            $provider && (
                ($provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue()) ||
                ($provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue())
            )
        ) {
            if (!$this->authorizeProvider($provider)) {
                return;
            }

            switch ($commandSlug) {
                case AppointmentAddedEventHandler::APPOINTMENT_ADDED:
                case BookingAddedEventHandler::BOOKING_ADDED:
                    if ($appointmentStatus === 'pending' && $this->getInsertPendingAppointments($provider) === false) {
                        break;
                    }

                    // Add new appointment or update existing one
                    if (!$appointment->getOutlookCalendarEventId()) {
                        $this->insertEvent($appointment, $provider);
                    } else {
                        $this->updateEvent($appointment, $provider);
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

                    if (
                        $appointmentStatus === 'approved' && $oldStatus && $oldStatus !== 'approved' &&
                        $this->getInsertPendingAppointments($provider) === false
                    ) {
                        $this->insertEvent($appointment, $provider);
                        break;
                    }

                    if (!$appointment->getOutlookCalendarEventId()) {
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
     * @param \AmeliaBooking\Domain\Entity\Booking\Event\Event $event
     * @param string $commandSlug
     * @param Collection $periods
     *
     * @return void
     * @throws ContainerException
     * @throws GraphException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    private function handleEventPeriodAction($event, $commandSlug, $periods, $newProviders = null, $removeProviders = null): void
    {
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        if ($event->getOrganizerId()) {
            $provider = $providerRepository->getById($event->getOrganizerId()->getValue());

            if (
                $provider && (
                    ($provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue()) ||
                    ($provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue())
                )
            ) {
                if (!$this->authorizeProvider($provider)) {
                    return;
                }

                /** @var EventPeriod $period */
                foreach ($periods->getItems() as $period) {
                    switch ($commandSlug) {
                        case EventAddedEventHandler::EVENT_ADDED:
                        case EventEditedEventHandler::TIME_UPDATED:
                        case EventEditedEventHandler::PROVIDER_CHANGED:
                            if (!$period->getOutlookCalendarEventId()) {
                                $this->insertEvent($event, $provider, $period);
                                break;
                            }

                            $this->updateEvent($event, $provider, $period, $newProviders, $removeProviders);
                            break;
                        case EventEditedEventHandler::EVENT_PERIOD_DELETED:
                            $this->deleteEvent($period, $provider);
                            break;
                        case BookingAddedEventHandler::BOOKING_ADDED:
                        case BookingCanceledEventHandler::BOOKING_CANCELED:
                            if (!$period->getOutlookCalendarEventId()) {
                                $this->insertEvent($event, $provider, $period);
                            } else {
                                $this->updateEvent($event, $provider, $period);
                            }
                            break;
                        case EventStatusUpdatedEventHandler::EVENT_STATUS_UPDATED:
                            if ($event->getStatus()->getValue() === 'rejected') {
                                $this->deleteEvent($period, $provider);
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
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws GraphException
     * @throws Exception
     */
    public function getEvents($providerArr, $dateStart, $dateStartEnd, $dateEnd, $eventIds): array
    {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return [];
        }

        $eventIds = is_array($eventIds) ? $eventIds : [];

        $finalEvents = [];
        $provider    = ProviderFactory::create($providerArr);
        if (
            $provider && (
                ($provider->getOutlookCalendar() && $provider->getOutlookCalendar()->getCalendarId()->getValue()) ||
                ($provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue())
            )
        ) {
            if (!$this->authorizeProvider($provider)) {
                return $finalEvents;
            }
            $startDate = DateTimeService::getCustomDateTimeObject($dateStart);
            $endDate   = DateTimeService::getCustomDateTimeObject($dateEnd);

            $outlookCalendarId = $provider->getOutlookCalendar() ?
                $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                $provider->getOutlookCalendarId()->getValue();

            $request = $this->graph->createCollectionRequest(
                'GET',
                sprintf(
                    '/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$expand=%s&$orderby=%s',
                    $outlookCalendarId,
                    rawurlencode($startDate->format('c')),
                    rawurlencode($endDate->format('c')),
                    rawurlencode(
                        'singleValueExtendedProperties($filter=id eq \'Integer ' .
                        self::GUID . ' Name appointmentId\')'
                    ),
                    rawurlencode('start/dateTime')
                )
            )
                ->setReturnType(Event::class)
                ->setPageSize($this->outlookCalendarSettings['maximumNumberOfEventsReturned']);

            $events = $request->getPage();

            $ameliaAppointmentPropertyId = 'Integer ' . self::GUID . ' Name appointmentId';
            $ignoreAmeliaEvents          = !empty($this->outlookCalendarSettings['ignoreAmeliaEvents']);

            /** @var Event $event */
            foreach ($events as $event) {
                $showAs = $event->getShowAs();
                if ($showAs !== null && $showAs->value() === 'free') {
                    continue;
                }
                $extendedProperties = $event->getSingleValueExtendedProperties();
                if ($extendedProperties !== null && !$ignoreAmeliaEvents) {
                    foreach ($extendedProperties as $extendedProperty) {
                        $propId    = null;
                        $propValue = null;
                        if (is_array($extendedProperty)) {
                            $propId    = $extendedProperty['id'] ?? null;
                            $propValue = $extendedProperty['value'] ?? null;
                        } elseif (is_object($extendedProperty)) {
                            if (method_exists($extendedProperty, 'getId')) {
                                $propId = $extendedProperty->getId();
                            }
                            if (method_exists($extendedProperty, 'getValue')) {
                                $propValue = $extendedProperty->getValue();
                            }
                        }
                        if ($propId !== $ameliaAppointmentPropertyId) {
                            continue;
                        }
                        if ($eventIds === []) {
                            break;
                        }
                        if (in_array((int)$propValue, $eventIds, true)) {
                            continue 2;
                        }
                    }
                }
                $eventStart = DateTimeService::getCustomDateTimeObject($event->getStart()->getDateTime());
                $eventEnd   = DateTimeService::getCustomDateTimeObject($event->getEnd()->getDateTime());

                // calendarView already bounds by startDateTime/endDateTime; overlap with the requested range only.
                if ($eventEnd <= $startDate || $eventStart >= $endDate) {
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
     * @param DateTime  $startDateTime
     * @param DateTime  $endDateTime
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ContainerException
     */
    public function removeSlotsFromOutlookCalendar(
        $providers,
        $excludeAppointmentId,
        $startDateTime,
        $endDateTime
    ): void {
        if (!$this->isCalendarEnabled() && !$this->isAccessTokenSet()) {
            return;
        }

        if ($this->outlookCalendarSettings['removeOutlookCalendarBusySlots'] === true) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            foreach ($providers->keys() as $providerKey) {
                /** @var Provider $provider */
                $provider = $providers->getItem($providerKey);

                if ($provider && ($provider->getOutlookCalendar() || $provider->getOutlookCalendarId())) {
                    $startDateTimeCopy = clone $startDateTime;
                    $startDateTimeCopy->modify('-1 days');

                    $endDateTimeCopy = clone $endDateTime;
                    $endDateTimeCopy->modify('+1 days');

                    // Process main calendar events
                    if (!array_key_exists($provider->getId()->getValue(), self::$providersOutlookEvents)) {
                        if (!$this->authorizeProvider($provider)) {
                            continue;
                        }

                        $outlookCalendarId = $provider->getOutlookCalendar() ?
                            $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                            $provider->getOutlookCalendarId()->getValue();

                        try {
                            $request = $this->graph->createCollectionRequest(
                                'GET',
                                sprintf(
                                    '/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$expand=%s&$orderby=%s',
                                    $outlookCalendarId,
                                    rawurlencode($startDateTimeCopy->format('c')),
                                    rawurlencode($endDateTimeCopy->format('c')),
                                    rawurlencode(
                                        'singleValueExtendedProperties($filter=id eq \'Integer ' .
                                        self::GUID . ' Name appointmentId\')'
                                    ),
                                    rawurlencode('start/dateTime')
                                )
                            )
                                ->setReturnType(Event::class)
                                ->setPageSize($this->outlookCalendarSettings['maximumNumberOfEventsReturned']);

                            $events = $request->getPage();
                            self::$providersOutlookEvents[$provider->getId()->getValue()] = $events;
                        } catch (\Exception $e) {
                            $errorMessage = $e->getMessage();
                            $isNotFound = strpos($errorMessage, '404') !== false ||
                                          strpos($errorMessage, 'ErrorItemNotFound') !== false;

                            if ($isNotFound) {
                                $this->container->getLoggerService()->channel('sync')->warning(
                                    'Outlook Calendar: Calendar not found. The calendar may have been deleted or the connection needs to be re-established.',
                                    [
                                        'exception'         => $e,
                                        'outlookCalendarId' => $outlookCalendarId,
                                        'providerId'        => $provider->getId()->getValue(),
                                    ]
                                );
                            } else {
                                $this->logger->error(
                                    'Outlook Calendar: Failed to fetch events for calendar',
                                    [
                                        'exception'         => $e,
                                        'outlookCalendarId' => $outlookCalendarId,
                                        'providerId'        => $provider->getId()->getValue(),
                                    ]
                                );
                            }

                            self::$providersOutlookEvents[$provider->getId()->getValue()] = [];
                            continue;
                        }
                    } else {
                        $events = self::$providersOutlookEvents[$provider->getId()->getValue()];
                    }

                    $this->processOutlookCalendarEvents($events, $provider, $excludeAppointmentId);

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
     * @param DateTime          $startDateTime
     * @param DateTime          $endDateTime
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
        $cacheKey = $providerId . '_blocked';

        if (array_key_exists($cacheKey, self::$providersOutlookEvents)) {
            return;
        }

        $accounts = $providerRepository->getOutlookCalendarAccounts($providerId);

        $this->fetchBlockedCalendarEventsFromAccounts(
            $accounts,
            $startDateTime,
            $endDateTime,
            $provider,
            $excludeAppointmentId
        );

        self::$providersOutlookEvents[$cacheKey] = true;
    }

    /**
     * Fetch events from blocked calendars for each account
     *
     * @param array     $accounts
     * @param DateTime $startDateTime
     * @param DateTime $endDateTime
     * @param Provider  $provider
     * @param int|null  $excludeAppointmentId
     *
     * @throws InvalidArgumentException
     */
    private function fetchBlockedCalendarEventsFromAccounts(
        $accounts,
        $startDateTime,
        $endDateTime,
        $provider,
        $excludeAppointmentId
    ): void {
        foreach ($accounts as $account) {
            if (empty($account['blockedCalendars']) || empty($account['token'])) {
                continue;
            }

            $graph = $this->createGraphForAccount($account);

            if ($graph === null) {
                continue;
            }

            foreach ($account['blockedCalendars'] as $calendarId) {
                try {
                    $request = $graph->createCollectionRequest(
                        'GET',
                        sprintf(
                            '/me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$expand=%s&$orderby=%s',
                            $calendarId,
                            rawurlencode($startDateTime->format('c')),
                            rawurlencode($endDateTime->format('c')),
                            rawurlencode(
                                'singleValueExtendedProperties($filter=id eq \'Integer ' .
                                self::GUID . ' Name appointmentId\')'
                            ),
                            rawurlencode('start/dateTime')
                        )
                    )
                        ->setReturnType(Event::class)
                        ->setPageSize($this->outlookCalendarSettings['maximumNumberOfEventsReturned']);

                    $events = $request->getPage();
                    $this->processOutlookCalendarEvents($events, $provider, $excludeAppointmentId);
                } catch (\Exception $e) {
                    $this->logger->error(
                        'OutlookCalendar: Error fetching events from blocked calendar',
                        [
                            'exception'  => $e,
                            'calendarId' => $calendarId,
                        ]
                    );
                    continue;
                }
            }
        }
    }

    /**
     * Create a Graph instance for a given account
     *
     * @param array $account
     *
     * @return OutlookGraphClient|null
     */
    private function createGraphForAccount($account): ?OutlookGraphClient
    {
        try {
            $token = $account['token'];

            if ($this->isAccessTokenExpired($token)) {
                $token = $this->refreshTokenForAccount($token, $account['id']);

                if ($token === null) {
                    $this->logger->warning(
                        'OutlookCalendar: Failed to refresh token for account',
                        [
                            'accountId' => $account['id'],
                        ]
                    );
                    return null;
                }
            }

            $tokenArray = json_decode($token, true);

            $graph = new OutlookGraphClient();
            $graph->setAccessToken($tokenArray['access_token']);

            return $graph;
        } catch (\Exception $e) {
            $this->logger->error(
                'OutlookCalendar: Error creating graph for account',
                [
                    'exception' => $e,
                    'accountId' => $account['id'],
                ]
            );
            return null;
        }
    }

    /**
     * Refresh token for a specific account and update it in the database
     *
     * @param string $token
     * @param int    $accountId
     *
     * @return string|null
     */
    private function refreshTokenForAccount($token, $accountId): ?string
    {
        try {
            if ($this->isAccessTokenSet()) {
                /** @var OutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
                $outlookCalendarMiddlewareService = $this->container->get(
                    'infrastructure.outlook.calendar.middleware.service'
                );

                $decodedToken = json_decode($token, true);

                if (!isset($decodedToken['refresh_token'])) {
                    return null;
                }

                $newToken = $outlookCalendarMiddlewareService->refreshAccessToken($decodedToken['refresh_token']);

                if ($newToken === null) {
                    return null;
                }

                $newTokenJson = json_encode($newToken);
            } else {
                $newTokenJson = $this->refreshToken($token);
            }

            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');
            $providerRepository->updateOutlookCalendarAccountToken($accountId, $newTokenJson);

            return $newTokenJson;
        } catch (\Exception $e) {
            $this->logger->error(
                'OutlookCalendar: Error refreshing token for account',
                [
                    'exception' => $e,
                    'accountId' => $accountId,
                ]
            );
            return null;
        }
    }

    /**
     * Process Outlook Calendar events and add them as fake appointments to block slots
     *
     * @param array    $events
     * @param Provider $provider
     * @param int|null $excludeAppointmentId
     *
     * @throws InvalidArgumentException
     */
    private function processOutlookCalendarEvents($events, $provider, $excludeAppointmentId): void
    {
        /** @var Event $event */
        foreach ($events as $event) {
            if ($event->getShowAs() !== null && $event->getShowAs()->is(FreeBusyStatus::FREE)) {
                continue;
            }

            $extendedProperties = $event->getSingleValueExtendedProperties();
            if ($extendedProperties !== null && !$this->outlookCalendarSettings['ignoreAmeliaEvents']) {
                foreach ($extendedProperties as $extendedProperty) {
                    if (
                        $this->getGraphPropertyId($extendedProperty) === 'Integer ' . self::GUID . ' Name appointmentId' &&
                        $excludeAppointmentId && (int)$this->getGraphPropertyValue($extendedProperty) === $excludeAppointmentId
                    ) {
                        continue 2;
                    }
                }
            }

            $eventStartString = DateTimeService::getCustomDateTimeFromUtc($event->getStart()->getDateTime());

            $eventEndString = DateTimeService::getCustomDateTimeFromUtc($event->getEnd()->getDateTime());

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
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider $provider
     * @param EventPeriod $period
     *
     * @return bool
     *
     * @throws ContainerException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function insertEvent($appointment, $provider, $period = null, $newProviders = null, $removeProviders = null): bool
    {
        $event = $this->getEventPreview($appointment, $provider, $period);

        if (!$event) {
            return false;
        }

        $location = $event->getLocation();
        if ($location && !$location->getDisplayName()) {
            $newLocation = new Location();
            $newLocation->setDisplayName($location->getDisplayName());
            $event->setLocation($newLocation);
        }

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService         = $this->container->get("application.placeholder.{$type}.service");
        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $eventId = $event->getId();

        $event->setAttendees($this->createGraphAttendees($this->getAttendees($appointment, $provider, $newProviders, $removeProviders)));

        $eventType = $period ? 'event' : 'appointment';

        if ($provider->getOutlookCalendar()) {
            $providerDescription = $provider->getOutlookCalendar()->getDescription();

            if (
                is_array($providerDescription) &&
                array_key_exists($eventType, $providerDescription) &&
                $providerDescription[$eventType] !== '' &&
                $providerDescription[$eventType] !== null
            ) {
                $descriptionSettings = $providerDescription;
            } else {
                $descriptionSettings = $this->outlookCalendarSettings['description'];
            }
        } else {
            $descriptionSettings = $this->outlookCalendarSettings['description'];
        }

        $body = $event->getBody();

        $joinUrl = $event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null;

        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent($this->getDescriptionForInsert($placeholderService, $placeholderData, $period, $joinUrl, $descriptionSettings));

        $event = apply_filters('amelia_before_outlook_calendar_event_added_filter', $event, $appointment->toArray(), $provider->toArray());
        $event->setBody($body);

        do_action('amelia_before_outlook_calendar_event_added', $event, $appointment->toArray(), $provider->toArray());

        $outlookCalendarId = $provider->getOutlookCalendar() ?
            $provider->getOutlookCalendar()->getCalendarId()->getValue() :
            $provider->getOutlookCalendarId()->getValue();

        try {
            $event = $this->graph->createRequest(
                'PATCH',
                sprintf(
                    '/me/calendars/%s/events/%s',
                    $outlookCalendarId,
                    $eventId
                )
            )->attachBody($event)->setReturnType(Event::class)->execute();
        } catch (GraphException $e) {
            return false;
        }

        if ($period) {
            /** @var EventPeriodsRepository $eventPeriodsRepository */
            $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');
            $period->setOutlookCalendarEventId(new Label($event->getId()));
            $period->setMicrosoftTeamsUrl($event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null);
            $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), $period->getOutlookCalendarEventId()->getValue(), 'outlookCalendarEventId');
            $eventPeriodsRepository->updateFieldById($period->getId()->getValue(), $period->getMicrosoftTeamsUrl(), 'microsoftTeamsUrl');
        } else {
            /** @var AppointmentRepository $appointmentRepository */
            $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
            $appointment->setOutlookCalendarEventId(new Label($event->getId()));
            $appointment->setMicrosoftTeamsUrl($event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null);
            $appointmentRepository->update($appointment->getId()->getValue(), $appointment);
        }

        do_action('amelia_after_outlook_calendar_event_added', $event, $appointment->toArray(), $provider->toArray());

        return true;
    }

    /**
     * Update an Event in Outlook Calendar.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     * @param array $newProviders
     * @param array $removeProviders
     *
     * @return bool
     * @throws ContainerException
     * @throws QueryExecutionException
     */
    private function updateEvent($appointment, $provider, $period = null, $newProviders = null, $removeProviders = null): bool
    {
        $entity = $period ?: $appointment;
        if ($entity->getOutlookCalendarEventId()) {
            /** @var SettingsService $settingsService */
            $settingsService = $this->container->get('domain.settings.service');
            $enabledForEntity = $settingsService
                ->getEntitySettings($period ? $appointment->getSettings() : $appointment->getService()->getSettings())
                ->getMicrosoftTeamsSettings()
                ->getEnabled();

            $event = $this->createEvent($appointment, $provider, $period);

            if ($enabledForEntity) {
                $event->setIsOnlineMeeting(true);
                $event->setOnlineMeetingProvider(new OnlineMeetingProviderType('teamsForBusiness'));
            } else {
                $event->setIsOnlineMeeting(false);
            }

            $eventId = $entity->getOutlookCalendarEventId()->getValue();

            $event->setAttendees($this->createGraphAttendees($this->getAttendees($appointment, $provider, $newProviders, $removeProviders)));

            $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
            /** @var PlaceholderService $placeholderService */
            $placeholderService         = $this->container->get("application.placeholder.{$type}.service");
            $appointmentArray           = $appointment->toArray();
            $appointmentArray['sendCF'] = true;

            $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

            $eventType = $period ? 'event' : 'appointment';

            if ($provider->getOutlookCalendar()) {
                $providerDescription = $provider->getOutlookCalendar()->getDescription();

                if (
                    is_array($providerDescription) &&
                    array_key_exists($eventType, $providerDescription) &&
                    $providerDescription[$eventType] !== '' &&
                    $providerDescription[$eventType] !== null
                ) {
                    $descriptionSettings = $providerDescription;
                } else {
                    $descriptionSettings = $this->outlookCalendarSettings['description'];
                }
            } else {
                $descriptionSettings = $this->outlookCalendarSettings['description'];
            }

            $description = $this->getDescription($placeholderService, $placeholderData, $type, $descriptionSettings);

            $body = $this->getBodyForInsert($eventId, $description);

            $event->setBody($body);

            $event = apply_filters('amelia_before_outlook_calendar_event_updated_filter', $event, $appointment->toArray(), $provider->toArray());

            do_action('amelia_before_outlook_calendar_event_updated', $event, $appointment->toArray(), $provider->toArray());

            $outlookCalendarId = $provider->getOutlookCalendar() ?
                $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                $provider->getOutlookCalendarId()->getValue();

            try {
                $updatedEvent = $this->graph->createRequest(
                    'PATCH',
                    sprintf(
                        '/me/calendars/%s/events/%s',
                        $outlookCalendarId,
                        $eventId
                    )
                )->attachBody($event)->setReturnType(get_class($event))->execute();

                $this->syncMicrosoftTeamsUrl($appointment, $period, $enabledForEntity, $updatedEvent);

                do_action('amelia_after_outlook_calendar_event_updated', $updatedEvent, $appointment->toArray(), $provider->toArray());
            } catch (GraphException | QueryExecutionException $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Persist or clear Microsoft Teams URL after calendar create/update.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param EventPeriod|null $period
     * @param bool $enabledForEntity
     * @param Event|null $outlookEvent
     *
     * @return void
     *
     * @throws QueryExecutionException
     */
    private function syncMicrosoftTeamsUrl($appointment, $period, $enabledForEntity, $outlookEvent): void
    {
        $joinUrl = null;

        if (
            $enabledForEntity &&
            $outlookEvent &&
            $outlookEvent->getOnlineMeeting() &&
            $outlookEvent->getOnlineMeeting()->getJoinUrl()
        ) {
            $joinUrl = $outlookEvent->getOnlineMeeting()->getJoinUrl();
        }

        if ($period) {
            /** @var EventPeriodsRepository $eventPeriodsRepository */
            $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');

            if (!$enabledForEntity) {
                $period->setMicrosoftTeamsUrl(null);
                $eventPeriodsRepository->updateFieldById(
                    $period->getId()->getValue(),
                    null,
                    'microsoftTeamsUrl'
                );

                return;
            }

            if ($joinUrl) {
                $period->setMicrosoftTeamsUrl($joinUrl);
                $eventPeriodsRepository->updateFieldById(
                    $period->getId()->getValue(),
                    $joinUrl,
                    'microsoftTeamsUrl'
                );
            }

            return;
        }

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');

        if (!$enabledForEntity) {
            $appointment->setMicrosoftTeamsUrl(null);
            $appointmentRepository->updateFieldById(
                $appointment->getId()->getValue(),
                null,
                'microsoftTeamsUrl'
            );

            return;
        }

        if ($joinUrl) {
            $appointment->setMicrosoftTeamsUrl($joinUrl);
            $appointmentRepository->updateFieldById(
                $appointment->getId()->getValue(),
                $joinUrl,
                'microsoftTeamsUrl'
            );
        }
    }

    /**
     * Delete an Event from Outlook Calendar.
     *
     * @param Appointment|EventPeriod $appointment
     * @param Provider    $provider
     *
     * @throws GraphException
     * @throws QueryExecutionException
     */
    private function deleteEvent($appointment, $provider): void
    {
        if ($appointment->getOutlookCalendarEventId()) {
            do_action('amelia_before_outlook_calendar_event_deleted', $appointment->toArray(), $provider->toArray());

            $outlookCalendarId = $provider->getOutlookCalendar() ?
                $provider->getOutlookCalendar()->getCalendarId()->getValue() :
                $provider->getOutlookCalendarId()->getValue();

            try {
                $this->graph->createRequest(
                    'DELETE',
                    sprintf(
                        '/me/calendars/%s/events/%s',
                        $outlookCalendarId,
                        $appointment->getOutlookCalendarEventId()->getValue()
                    )
                )->execute();
            } catch (\Throwable $e) {
                // The event is already gone from Outlook (e.g. deleted there), so only the local reference is left to clear.
                if (!$this->isGraphEventMissingError($e)) {
                    throw $e;
                }
            }

            $appointment->setOutlookCalendarEventId(null);
            $appointment->setMicrosoftTeamsUrl(null);

            /** @var AppointmentRepository $repository */
            $repository = $this->container->get('domain.booking.appointment.repository');

            if (is_a($appointment, EventPeriod::class)) {
                /** @var EventPeriodsRepository $repository */
                $repository = $this->container->get('domain.booking.event.period.repository');
            }
            $repository->updateFieldById($appointment->getId()->getValue(), null, 'outlookCalendarEventId');
            $repository->updateFieldById($appointment->getId()->getValue(), null, 'microsoftTeamsUrl');

            do_action('amelia_after_outlook_calendar_event_deleted', $appointment->toArray(), $provider->toArray());
        }
    }

    /**
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    private function getEventPreview($appointment, $provider, $period = null)
    {
        $event = $this->createEvent($appointment, $provider, $period);

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $enabledForEntity = $settingsService
            ->getEntitySettings($period ? $appointment->getSettings() : $appointment->getService()->getSettings())
            ->getMicrosoftTeamsSettings()
            ->getEnabled();

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService         = $this->container->get("application.placeholder.{$type}.service");
        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $body = new ItemBody();
        $body->setContentType(new BodyType(BodyType::HTML));

        if ($enabledForEntity) {
            $body->setContent('');
            $event->setAttendees([]);
            $event->setIsOnlineMeeting(true);
            $event->setOnlineMeetingProvider(new OnlineMeetingProviderType('teamsForBusiness'));
        }
        if (!$enabledForEntity) {
            $body->setContent($this->getDescriptionForInsert($placeholderService, $placeholderData, $period, null));
        }
        $event->setBody($body);

        $outlookCalendarId = $provider->getOutlookCalendar() ?
            $provider->getOutlookCalendar()->getCalendarId()->getValue() :
            $provider->getOutlookCalendarId()->getValue();

        try {
            $event = $this->graph->createRequest(
                'POST',
                sprintf(
                    '/me/calendars/%s/events',
                    $outlookCalendarId
                )
            )->attachBody($event)->setReturnType(get_class($event))->execute();
            return $event;
        } catch (GraphException $e) {
            return null;
        }
    }

    /**
     * Create and return Outlook Calendar Event Object filled with appointments data.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider    $provider
     * @param EventPeriod $period
     *
     * @return Event
     *
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws Exception
     */
    private function createEvent($appointment, $provider, $period = null): Event
    {
        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');

        /** @var AbstractCustomFieldApplicationService $customFieldService */
        $customFieldService = $this->container->get('application.customField.service');

        $type = $period ? Entities::EVENT : Entities::APPOINTMENT;
        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get("application.placeholder.{$type}.service");

        $appointmentLocationId = $appointment->getLocationId() ? $appointment->getLocationId()->getValue() : null;
        $providerLocationId    = $provider->getLocationId() ? $provider->getLocationId()->getValue() : null;

        $locationId = $appointmentLocationId ?: $providerLocationId;

        /** @var \AmeliaBooking\Domain\Entity\Location\Location $location */
        $location = $locationId ? $locationRepository->getById($locationId) : null;

        $address = $customFieldService->getCalendarEventLocation($appointment);

        $appointmentArray           = $appointment->toArray();
        $appointmentArray['sendCF'] = true;

        $placeholderData = $placeholderService->getPlaceholdersData($appointmentArray);

        $start = $period ?  clone $period->getPeriodStart()->getValue() : clone $appointment->getBookingStart()->getValue();

        if ($period) {
            $time = (int)$period->getPeriodEnd()->getValue()->format('H') * 60 + (int)$period->getPeriodEnd()->getValue()->format('i');
            $end  = DateTimeService::getCustomDateTimeObject(
                $start->format('Y-m-d')
            )->add(new \DateInterval('PT' . $time . 'M'));
        } else {
            $end = clone $appointment->getBookingEnd()->getValue();
        }

        if ($this->getIncludeBufferTime($provider) === true && $type === Entities::APPOINTMENT) {
            $timeBefore = $appointment->getService()->getTimeBefore() ?
                $appointment->getService()->getTimeBefore()->getValue() : 0;
            $timeAfter  = $appointment->getService()->getTimeAfter() ?
                $appointment->getService()->getTimeAfter()->getValue() : 0;
            $start->modify('-' . $timeBefore . ' second');
            $end->modify('+' . $timeAfter . ' second');
        }

        $eventType = $period ? 'event' : 'appointment';

        if ($provider->getOutlookCalendar()) {
            $providerTitle = $provider->getOutlookCalendar()->getTitle();

            if (
                is_array($providerTitle) &&
                array_key_exists($eventType, $providerTitle) &&
                $providerTitle[$eventType] !== '' &&
                $providerTitle[$eventType] !== null
            ) {
                $titleSettings = $providerTitle;
            } else {
                $titleSettings = $this->outlookCalendarSettings['title'];
            }
        } else {
            $titleSettings = $this->outlookCalendarSettings['title'];
        }

        $timeZone = DateTimeService::getTimeZone()->getName();

        $startDateTime = new DateTimeTimeZone();
        $startDateTime->setDateTime($start->format('Y-m-d\TH:i:s'));
        $startDateTime->setTimeZone($timeZone);
        $endDateTime = new DateTimeTimeZone();
        $endDateTime->setDateTime($end->format('Y-m-d\TH:i:s'));
        $endDateTime->setTimeZone($timeZone);

        $event = new Event();

        $event->setStart($startDateTime);
        $event->setEnd($endDateTime);

        $event->setSubject(
            $placeholderService->applyPlaceholders(
                $period ? $titleSettings['event'] : $titleSettings['appointment'],
                $placeholderData
            )
        );

        if ($location || $address) {
            $outlookLocation = new Location();
            $outlookLocation->setDisplayName($address ?: $location->getName()->getValue());

            if ($location && $location->getCoordinates()) {
                $outlookCoordinates = new OutlookGeoCoordinates();
                $outlookCoordinates->setLatitude($location->getCoordinates()->getLatitude());
                $outlookCoordinates->setLongitude($location->getCoordinates()->getLongitude());
                $outlookLocation->setCoordinates($outlookCoordinates);
            }

            if ($location) {
                $outlookAddress = new PhysicalAddress();
                $outlookAddress->setStreet($location->getAddress() ? $location->getAddress()->getValue() : null);
                $outlookLocation->setAddress($outlookAddress);
            }

            $event->setLocation($outlookLocation);
        }

        $property = new SingleValueLegacyExtendedProperty();
        $property->setId('Integer ' . self::GUID . ' Name appointmentId');
        $property->setValue((string)$appointment->getId()->getValue());

        $event->setSingleValueExtendedProperties([$property]);

        if ($period && $period->getPeriodStart()->getValue()->diff($period->getPeriodEnd()->getValue())->format('%a') !== '0') {
            $recData = [
                "pattern" => [
                    "type" => "daily",
                    "interval" => 1
                ],
                "range" => [
                    "type" => "endDate",
                    "startDate" => $period->getPeriodStart()->getValue()->format('Y-m-d'),
                    "endDate" => $period->getPeriodEnd()->getValue()->format('Y-m-d'),
                    "recurrenceTimeZone" => $period->getPeriodStart()->getValue()->getTimezone()->getName()
                ]
            ];
            $event->setRecurrence(OutlookGraphClient::modelFromArray(PatternedRecurrence::class, $recData));
        }

        return $event;
    }

    private function getDescription($placeholderService, $placeholderData, $type, $descriptionSettings = null): string
    {
        if ($descriptionSettings === null) {
            $descriptionSettings = $this->outlookCalendarSettings['description'];
        }

        $description = $placeholderService->applyPlaceholders(
            $type === 'event' ? $descriptionSettings['event'] : $descriptionSettings['appointment'],
            $placeholderData
        );
        return str_replace("\n", '<br>', $description);
    }

    private function getDescriptionForInsert(
        $placeholderService,
        $placeholderData,
        $period,
        $joinUrl,
        $descriptionSettings = null
    ) {
        $type        = $period ? Entities::EVENT : Entities::APPOINTMENT;
        $description = $this->getDescription($placeholderService, $placeholderData, $type, $descriptionSettings);

        // include the joinUrl in the body content to ensure the Join button remains visible
        return $joinUrl
            ? '<a href="' . $joinUrl . '">Join Meeting</a><br><br>' . $description . '<br><br>'
            : $description;
    }

    private function getBodyForInsert($eventId, $description)
    {
        $event = $this->getEvent($eventId);

        // The event is only read to keep its Join link, so a new body serves when it cannot be read:
        // the id belongs to another organizer's mailbox, or the event is gone from this one.
        $body = $event && $event->getBody() ? $event->getBody() : new ItemBody();

        $joinUrl     = $event && $event->getOnlineMeeting() ? $event->getOnlineMeeting()->getJoinUrl() : null;
        $bodyContent = ($joinUrl)
            ? '<a href="' . $joinUrl . '">Join Meeting</a><br><br>' .  $description . '<br><br>'
            : $description;

        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent($bodyContent);

        return $body;
    }

    private function getEvent($eventId)
    {
        try {
            $event = $this->graph->createRequest(
                'GET',
                sprintf(
                    '/me/events/%s/',
                    $eventId
                )
            )->setReturnType(Event::class)->execute();
            return $event;
        } catch (GraphException $e) {
            return null;
        }
    }

    /**
     * Get All Attendees that need to be added in Outlook Calendar Event based on "addAttendees" Settings.
     *
     * @param Appointment|\AmeliaBooking\Domain\Entity\Booking\Event\Event $appointment
     * @param Provider $provider
     *
     * @return array
     *
     * @throws NotFoundException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function getAttendees($appointment, $provider, $newProviders = null, $removeProviders = null): array
    {
        $attendees = [];

        if ($this->outlookCalendarSettings['addAttendees'] === true) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            $providers = is_a($appointment, Appointment::class) ?
                [$providerRepository->getById($appointment->getProviderId()->getValue())] :
                $appointment->getProviders()->getItems();

            if ($newProviders) {
                $providers = array_merge($providers, $newProviders);
            }
            if ($removeProviders) {
                $providersRemoveIds = array_map(
                    function ($value) {
                        return $value->getId()->getValue();
                    },
                    $removeProviders
                );
            }

            foreach ($providers as $provider) {
                if (empty($providersRemoveIds) || !in_array($provider->getId()->getValue(), $providersRemoveIds)) {
                    $attendees[] = [
                        'emailAddress'    => [
                            'name'    => $provider->getFirstName()->getValue() . ' ' . $provider->getLastName()->getValue(),
                            'address' => $provider->getEmail()->getValue(),
                        ],
                        'type'            => 'required',
                        'status'  => [
                            'response' => 'accepted',
                            'time' => (new DateTime('now'))->format(DATE_ATOM)
                        ]
                    ];
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
                            'emailAddress' => [
                                'name'    =>
                                    $customer->getFirstName()->getValue() . ' ' . $customer->getLastName()->getValue(),
                                'address' => $customer->getEmail()->getValue(),
                            ],
                            'type'         => 'required',
                            'status'  => [
                                'response' => 'accepted',
                                'time' => (new DateTime('now'))->format(DATE_ATOM)
                            ]
                        ];
                    }
                }
            }
        }

        return $attendees;
    }

    /**
     * @param array $attendees
     *
     * @return Attendee[]
     */
    private function createGraphAttendees(array $attendees): array
    {
        return array_map(
            function ($attendee) {
                return OutlookGraphClient::modelFromArray(Attendee::class, $attendee);
            },
            $attendees
        );
    }

    /**
     * @param mixed $property
     *
     * @return string|null
     */
    private function getGraphPropertyId($property): ?string
    {
        if (is_object($property) && method_exists($property, 'getId')) {
            return $property->getId();
        }

        return is_array($property) ? ($property['id'] ?? null) : null;
    }

    /**
     * @param mixed $property
     *
     * @return string|null
     */
    private function getGraphPropertyValue($property): ?string
    {
        if (is_object($property) && method_exists($property, 'getValue')) {
            return $property->getValue();
        }

        return is_array($property) ? ($property['value'] ?? null) : null;
    }

    /**
     * Refresh Token if it is expired
     *
     * @param String $token
     *
     * @return string
     *
     * @throws ContainerException
     * @throws Exception
     */
    private function refreshToken($token): string
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var array $outlookSettings */
        $outlookSettings = $settingsService->getCategorySettings('outlookCalendar');

        $decodedToken = json_decode($token, true);

        $response = wp_remote_post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            array(
            'timeout' => 25,
            'body'    => array(
                'client_id'     => $outlookSettings['clientID'],
                'client_secret' => $outlookSettings['clientSecret'],
                'grant_type'    => 'refresh_token',
                'refresh_token' => $decodedToken['refresh_token'],
                'redirect_uri'  => !AMELIA_DEV
                    ? str_replace('http://', 'https://', $outlookSettings['redirectURI'])
                    : $outlookSettings['redirectURI'],
                'scope'         => 'offline_access calendars.readwrite',
            )
            )
        );

        if ($response instanceof WP_Error) {
            throw new \Exception($response->get_error_message());
        }

        if (!is_array($response)) {
            throw new \Exception('Invalid response from Microsoft');
        }

        if ($response['response']['code'] !== 200) {
            $responseBody = json_decode($response['body'], true);

            throw new \Exception($responseBody['error_description']);
        }

        $decodedToken            = json_decode($response['body'], true);
        $decodedToken['created'] = time();

        return json_encode($decodedToken);
    }

    /**
     * @param $token
     *
     * @return bool
     */
    private function isAccessTokenExpired($token): bool
    {
        $decodedToken = json_decode($token, true);

        if (!isset($decodedToken['created'])) {
            return true;
        }

        return ($decodedToken['created'] + ($decodedToken['expires_in'] - 30)) < time();
    }

    /**
     * @param DateTimeTimeZone $eventStart
     * @param DateTimeTimeZone $eventEnd
     *
     * @return array
     *
     * @throws Exception
     */
    private function removeTimeBasedEvents($eventStart, $eventEnd)
    {
        $timesToRemove = [];

        $daysBetweenStartAndEnd = (int)DateTimeService::getCustomDateTimeObjectFromUtc($eventEnd->getDateTime())
            ->diff(DateTimeService::getCustomDateTimeObjectFromUtc($eventStart->getDateTime()))->format('%a');

        // If event is in the same day, or not
        if ($daysBetweenStartAndEnd === 0) {
            $timesToRemove[] = [
                'eventStartDateTime' => DateTimeService::getCustomDateTimeFromUtc($eventStart->getDateTime()),
                'eventEndDateTime'   => DateTimeService::getCustomDateTimeFromUtc($eventEnd->getDateTime())
            ];
        } else {
            for ($i = 0; $i <= $daysBetweenStartAndEnd; $i++) {
                $startDateTime = DateTimeService::getCustomDateTimeObjectFromUtc(
                    $eventStart->getDateTime()
                )->modify('+' . $i . ' days');

                $timesToRemove[] = [
                    'eventStartDateTime' => $i === 0 ?
                        $startDateTime->format('Y-m-d H:i:s') :
                        $startDateTime->format('Y-m-d') . ' 00:00:01',
                    'eventEndDateTime'   => $i === $daysBetweenStartAndEnd ?
                        DateTimeService::getCustomDateTimeFromUtc($eventEnd->getDateTime()) :
                        $startDateTime->format('Y-m-d') . ' 23:59:59'
                ];
            }
        }

        return $timesToRemove;
    }

    /**
     * @return bool
     */
    private function isCalendarEnabled(): bool
    {
        return $this->settings->isFeatureEnabled('outlookCalendar') &&
            $this->outlookCalendarSettings['clientID'] &&
            $this->outlookCalendarSettings['clientSecret'];
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param string $from
     * @param string $fromName
     * @param string $replyTo
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param array  $bccEmails
     * @param array  $attachments
     *
     * @return void
     *
     * @throws ContainerException
     * @throws GraphException
     */
    public function sendEmail(
        $from,
        $fromName,
        $replyTo,
        $to,
        $subject,
        $body,
        $bccEmails = [],
        $attachments = []
    ): void {
        $this->authorizeAdmin();

        $attachmentList = [];

        foreach ($attachments as $attachment) {
            $attachmentList[] = [
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => $attachment['fileName'],
                'contentType'  => mime_content_type($attachment['filePath']),
                'contentBytes' => base64_encode(file_get_contents($attachment['filePath'])),
                'isInline'     => false,
            ];
        }

        $bccList = [];

        foreach ($bccEmails as $bcc) {
            $bccList[] = [
                'emailAddress' => [
                    'address' => trim($bcc),
                ]
            ];
        }

        $message = [
            'from'          => [
                'emailAddress' => [
                    'name' => mb_convert_encoding($fromName, 'UTF-8'),
                ]
            ],
            'subject'       => mb_convert_encoding($subject, 'UTF-8'),
            'body'          => [
                'contentType' => 'HTML',
                'content'     => mb_convert_encoding($body, 'UTF-8'),
            ],
            'toRecipients'  => [
                [
                    'emailAddress' => [
                        'address' => trim($to),
                    ],
                ]
            ],
            'replyTo'       => trim($replyTo) ? [
                [
                    'emailAddress' => [
                        'address' => trim($replyTo),
                    ],
                ]
            ] : [],
            'bccRecipients' => $bccList,
            'attachments'   => $attachmentList,
        ];

        try {
            $this->graph
                ->createRequest('POST', '/me/sendMail')
                ->attachBody(
                    [
                        'message'         => $message,
                        'saveToSentItems' => true
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
        }
    }

    public function getCalendarListsForAccounts(array $accounts, $provider): array
    {
        foreach ($accounts as &$account) {
            if (isset($account['token']) && $account['token']) {
                try {
                    $graph = $this->createGraphForAccount($account);

                    if (!$graph) {
                        $account['calendarList'] = [];
                        continue;
                    }

                    $outlookCalendars = $graph
                        ->createCollectionRequest('GET', '/me/calendars')
                        ->setReturnType(Calendar::class)
                        ->setPageSize(100)
                        ->getPage();

                    $calendarList = [];
                    /** @var Calendar $outlookCalendar */
                    foreach ($outlookCalendars as $outlookCalendar) {
                        if ($outlookCalendar->getCanEdit() !== false) {
                            $calendarList[] = [
                                'id'        => $outlookCalendar->getId(),
                                'name'      => $outlookCalendar->getName(),
                                'owner'     => $outlookCalendar->getOwner() ? $outlookCalendar->getOwner()->getName() : '',
                                'isDefault' => $outlookCalendar->getIsDefaultCalendar() === true,
                            ];
                        }
                    }

                    $account['calendarList'] = $calendarList;
                } catch (\Exception $e) {
                    $this->logger->error(
                        'OutlookCalendar: Error fetching calendar list for account',
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
     * Public webhook URL for Microsoft Graph calendar notifications.
     *
     * @return string
     */
    private function getOutlookCalendarWebhookAddress(): string
    {
        $host = AMELIA_HOME_URL;
        $homeUrl = preg_match('/^https?:\/\//', $host) ? $host : 'https://' . $host;
        $homeHost = parse_url($homeUrl, PHP_URL_HOST);
        $homeScheme = parse_url($homeUrl, PHP_URL_SCHEME);

        $base = rtrim($homeUrl, '/');

        if (
            AMELIA_NGROK_URL &&
            (
                (defined('AMELIA_DEV') && AMELIA_DEV) ||
                $homeScheme !== 'https' ||
                in_array($homeHost, ['localhost', '127.0.0.1'], true)
            )
        ) {
            $ngrok = preg_match('/^https?:\/\//', AMELIA_NGROK_URL)
                ? AMELIA_NGROK_URL
                : 'https://' . AMELIA_NGROK_URL;

            // Swap only the scheme + host, so a WordPress install served from a
            // subdirectory (e.g. http://localhost/ameliaDev) keeps its base path
            $base = preg_replace('#^https?://[^/]+#', rtrim($ngrok, '/'), $base);
        }

        $webhookUrl = apply_filters(
            'amelia_outlook_calendar_notification_url',
            rtrim($base, '/') . '/wpamelia/outlook/notification'
        );

        return preg_replace('/^http:\/\//', 'https://', (string)$webhookUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function ensureTwoWaySyncForProvider($providerId): void
    {
        $providerId = (int)$providerId;
        if ($providerId <= 0) {
            return;
        }

        if (!$this->settings->isFeatureEnabled('outlookCalendar')) {
            return;
        }

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        try {
            $calendars = $outlookCalendarRepository->getByEntityId($providerId, 'userId');
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: ensureTwoWaySync load calendars',
                [
                    'exception'  => $e,
                    'providerId' => $providerId,
                ]
            );
            return;
        }

        if (!$calendars->length()) {
            // No connection row: the employee is on the global Settings page connection, where the calendar
            // selection lives on the user row and the credentials come from the plugin settings.
            $this->ensureTwoWaySyncForGlobalProvider($providerId);

            return;
        }

        foreach ($calendars->getItems() as $outlookCalendar) {
            try {
                $provider = $providerRepository->getById($providerId);
                $provider->setOutlookCalendar($outlookCalendar);
                if (!$this->authorizeProvider($provider)) {
                    continue;
                }

                if (!$outlookCalendar->getCalendarId() || !$outlookCalendar->getCalendarId()->getValue()) {
                    $calendarId = $this->pickPrimaryCalendarId($this->listCalendarList($provider));
                    if (!$calendarId || !$outlookCalendar->getId()) {
                        continue;
                    }

                    $outlookCalendar->setCalendarId(new Label($calendarId));
                    $outlookCalendarRepository->update($outlookCalendar, $outlookCalendar->getId()->getValue());
                    $provider->setOutlookCalendar($outlookCalendar);
                }

                $this->createOrRefreshGraphSubscription($outlookCalendar, $provider);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'OutlookCalendar: ensureTwoWaySync failed',
                    [
                        'exception'  => $e,
                        'providerId' => $providerId,
                    ]
                );
            }
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
    private function ensureTwoWaySyncForGlobalProvider($providerId): void
    {
        if (!$this->isAccessTokenSet()) {
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

        $calendarId = $provider->getOutlookCalendarId() && $provider->getOutlookCalendarId()->getValue()
            ? $provider->getOutlookCalendarId()->getValue()
            : null;

        // Selection cleared: release whatever this employee was attached to.
        if (!$calendarId) {
            $this->releaseUnusedGlobalCalendarSyncRows();

            return;
        }

        if (!$this->authorizeProvider($provider)) {
            return;
        }

        $accountId = $this->resolveGlobalAccountId($provider);

        if ($accountId === '') {
            return;
        }

        $calendarSync = $calendarSyncRepository->getOrCreate(
            CalendarSync::TYPE_OUTLOOK,
            $accountId,
            $calendarId,
            null
        );

        if (!$calendarSync || !$calendarSync->getId()) {
            return;
        }

        // Anything this employee used to point at may now be unused.
        $this->releaseUnusedGlobalCalendarSyncRows();

        if ($calendarSync->hasLiveSubscription(self::SUBSCRIPTION_RENEW_MARGIN)) {
            return;
        }

        try {
            $this->registerGraphSubscription($calendarSync, null);
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: global subscription create failed',
                [
                    'exception'  => $e,
                    'providerId' => $providerId,
                    'calendarId' => $calendarId,
                ]
            );
        }
    }

    /**
     * The mailbox behind the global access token. There is no connection row to cache it on, so it is kept
     * in the plugin settings next to the token it belongs to.
     *
     * @param Provider $provider
     *
     * @return string
     */
    private function resolveGlobalAccountId(Provider $provider): string
    {
        $stored = !empty($this->outlookCalendarSettings['accountId'])
            ? (string)$this->outlookCalendarSettings['accountId']
            : '';

        if ($stored !== '') {
            return $stored;
        }

        try {
            $identity = $this->fetchMailboxIdentity();

            $accountId = !empty($identity['email']) ? strtolower(trim((string)$identity['email'])) : '';
        } catch (\Throwable $e) {
            $this->logger->warning(
                'OutlookCalendar: could not resolve global account for calendar sync',
                ['exception' => $e]
            );

            return '';
        }

        if ($accountId === '') {
            return '';
        }

        $this->settings->setSetting('outlookCalendar', 'accountId', $accountId);
        $this->outlookCalendarSettings['accountId'] = $accountId;

        return $accountId;
    }

    /**
     * Drop global watches nothing points at any more, which is how a cleared or changed calendar selection
     * gets cleaned up - there is no connection row whose removal would signal it.
     *
     * @return void
     */
    private function releaseUnusedGlobalCalendarSyncRows(): void
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        try {
            $rows = $calendarSyncRepository->getAllByType(CalendarSync::TYPE_OUTLOOK);
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
                $employees = $this->globalEmployeesForCalendarSync($calendarSync);

                if ($employees || $outlookCalendarRepository->getConnectionsBySyncId($syncId)) {
                    continue;
                }

                // Authorize per row rather than relying on the caller: this runs before any authorization
                // when a calendar selection is cleared, and the rows in this loop can belong to different
                // mailboxes, so an ambient credential would be missing or the wrong one.
                if ($calendarSync->getSubscriptionId() && $this->authorizeForTeardown($calendarSync)) {
                    $this->deleteGraphSubscriptionRemote($calendarSync->getSubscriptionId());
                }

                $calendarSyncRepository->delete($syncId);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'OutlookCalendar: releasing unused global watch failed',
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
    public function renewSubscriptions(): void
    {
        if (!$this->settings->isFeatureEnabled('outlookCalendar')) {
            return;
        }

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        try {
            $calendarSyncRows = $calendarSyncRepository->getAllByType(CalendarSync::TYPE_OUTLOOK);
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: renew subscriptions load calendars',
                ['exception' => $e]
            );
            return;
        }

        /** @var CalendarSync $calendarSync */
        foreach ($calendarSyncRows->getItems() as $calendarSync) {
            $syncId = $calendarSync->getId() ? (int)$calendarSync->getId()->getValue() : null;

            if (!$syncId) {
                continue;
            }

            try {
                $connections = $outlookCalendarRepository->getConnectionsBySyncId($syncId);

                // Employees on the global connection reach a calendar through their user row rather than
                // a connection row, so they are counted before anything is torn down. Asking only when
                // ownerConnectionId is null would miss the row whose owning connection has been deleted:
                // it looks unused although those employees still rely on it.
                if (!$connections) {
                    $globalEmployees = $this->globalEmployeesForCalendarSync($calendarSync);

                    if (!$globalEmployees) {
                        // Nothing points at this calendar any more. Authorize before the remote delete: at
                        // this point in the loop $this->graph is either unset or still pointing at the
                        // previous calendar's mailbox.
                        if ($calendarSync->getSubscriptionId() && $this->authorizeForTeardown($calendarSync)) {
                            $this->deleteGraphSubscriptionRemote($calendarSync->getSubscriptionId());
                        }

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
                if ($calendarSync->hasLiveSubscription(self::SUBSCRIPTION_RENEW_MARGIN)) {
                    continue;
                }

                // Hand the watch to a connection that is still around, in case the owner has gone.
                if ($connections) {
                    $owner = $this->pickOwnerConnection($calendarSync, $connections);

                    if ($owner && (int)$owner['id'] !== (int)$calendarSync->getOwnerConnectionId()) {
                        $calendarSyncRepository->updateOwnerConnectionId($syncId, $owner['id']);
                        $calendarSync->setOwnerConnectionId($owner['id']);
                    }
                }

                $outlookCalendar = $this->authorizeForCalendarSync($calendarSync);

                if ($outlookCalendar === false) {
                    continue;
                }

                $this->registerGraphSubscription($calendarSync, $outlookCalendar);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'OutlookCalendar: renew subscription failed',
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
    private function pickOwnerConnection(CalendarSync $calendarSync, array $connections): ?array
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
     * {@inheritdoc}
     */
    public function removeTwoWaySyncForProvider($providerId): void
    {
        $providerId = (int)$providerId;
        if ($providerId <= 0) {
            return;
        }

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        try {
            $calendars = $outlookCalendarRepository->getByEntityId($providerId, 'userId');
        } catch (\Throwable $e) {
            return;
        }

        foreach ($calendars->getItems() as $outlookCalendar) {
            if (!$outlookCalendar->getId()) {
                continue;
            }
            $this->removeTwoWaySyncForCalendarRow($outlookCalendar->getId()->getValue());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeTwoWaySyncForCalendarRow($calendarRowId): void
    {
        $calendarRowId = (int)$calendarRowId;
        if ($calendarRowId <= 0) {
            return;
        }

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');
        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        try {
            $outlookCalendar = $outlookCalendarRepository->getById($calendarRowId);
        } catch (\Throwable $e) {
            return;
        }

        if (!$outlookCalendar->getSyncId()) {
            return;
        }

        $syncId = (int)$outlookCalendar->getSyncId()->getValue();

        // Authorizing first means the remote subscription can actually be deleted when this is the last
        // connection using it. Without a usable token only the local rows can be cleaned up.
        $providerId = $outlookCalendarRepository->getUserIdByOutlookCalendarRowId($calendarRowId);

        if ($providerId) {
            try {
                $provider = $providerRepository->getById($providerId);
                $provider->setOutlookCalendar($outlookCalendar);

                // Best effort: a failure only means the remote subscription cannot be deleted, which
                // deleteGraphSubscriptionRemote() detects through the authorization flag.
                $this->authorizeProvider($provider);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'OutlookCalendar: removeTwoWaySync authorize',
                    [
                        'exception'     => $e,
                        'calendarRowId' => $calendarRowId,
                        'providerId'    => $providerId,
                    ]
                );
            }
        }

        try {
            $outlookCalendarRepository->updateSyncId($calendarRowId, null);

            $this->detachConnectionFromCalendarSync($calendarRowId, $syncId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: removeTwoWaySync detach',
                [
                    'exception'     => $e,
                    'calendarRowId' => $calendarRowId,
                    'syncId'        => $syncId,
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processGraphCalendarNotifications(
        $subscriptionId,
        $clientStateFromNotification,
        array $notifications
    ): void {
        if (!$subscriptionId || !$notifications) {
            return;
        }

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        $calendarSync = $calendarSyncRepository->getBySubscriptionId(
            CalendarSync::TYPE_OUTLOOK,
            $subscriptionId
        );

        if (!$calendarSync) {
            return;
        }

        $storedClientState   = (string)$calendarSync->getClientState();
        $incomingClientState = (string)$clientStateFromNotification;

        // The webhook endpoint carries neither a nonce nor a logged in user, so the client state is the only proof
        // that a notification really comes from Graph. A missing stored value cannot be verified, so it is rejected
        // instead of trusted - the subscription is recreated with a fresh client state the next time the provider is
        // saved or reconnected (ensureTwoWaySyncForProvider).
        if ($storedClientState === '' || !hash_equals($storedClientState, $incomingClientState)) {
            return;
        }

        $outlookCalendar = $this->authorizeForCalendarSync($calendarSync);

        if ($outlookCalendar === false) {
            $this->logger->error(
                'OutlookCalendar: notification authorize failed',
                [
                    'subscriptionId' => $subscriptionId,
                    'syncId'         => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );

            return;
        }

        $this->renewGraphSubscription($calendarSync, $outlookCalendar);

        /** @var CalendarSyncLockRepository $syncLockRepository */
        $syncLockRepository = $this->container->get('domain.calendar.sync.lock.repository');

        foreach ($notifications as $notification) {
            $changeType = isset($notification['changeType']) ? (string)$notification['changeType'] : '';
            $eventId = $this->extractGraphEventIdFromNotification($notification);
            if ($eventId === '') {
                $this->logger->warning(
                    'OutlookCalendar: notification missing event id',
                    [
                        'notification'   => $notification,
                        'subscriptionId' => $subscriptionId,
                    ]
                );
                continue;
            }

            // Graph delivers at least once, so a second copy of this change can already be in flight. Taking the
            // lock before fetching the event means the winner works from the current calendar state and whoever
            // follows re-reads the appointment it just updated and applies nothing.
            $lockKey = 'outlook:event:' . $eventId;

            $lock = $syncLockRepository->acquire($lockKey);

            if ($lock === CalendarSyncLockRepository::RESULT_CONTENDED) {
                // Two notifications for one event id within the wait window are the same change arriving
                // twice, and the holder fetches the event fresh, so it already sees whatever this delivery
                // was going to apply.
                $this->logger->info(
                    'OutlookCalendar: skipped notification, another delivery holds the event',
                    [
                        'eventId'        => $eventId,
                        'subscriptionId' => $subscriptionId,
                    ]
                );

                continue;
            }

            try {
                if (stripos($changeType, 'deleted') !== false) {
                    $this->applyOutlookTwoWayDeletion($eventId, $calendarSync);
                    continue;
                }

                try {
                    $graphEvent = $this->fetchGraphEventForTwoWay(
                        $calendarSync->getCalendarId(),
                        $eventId
                    );
                } catch (\Throwable $e) {
                    if ($this->isGraphEventMissingError($e)) {
                        $this->applyOutlookTwoWayDeletion($eventId, $calendarSync);
                        continue;
                    }

                    $this->logger->error(
                        'OutlookCalendar: fetch event for two-way failed',
                        [
                            'exception' => $e,
                            'eventId'   => $eventId,
                        ]
                    );
                    continue;
                }

                if (!$graphEvent) {
                    continue;
                }

                $this->applyOutlookTwoWayUpsert($graphEvent, $eventId, $calendarSync);
            } finally {
                if ($lock === CalendarSyncLockRepository::RESULT_ACQUIRED) {
                    $syncLockRepository->release($lockKey);
                }
            }
        }
    }

    /**
     * @param array $notification
     *
     * @return string
     */
    private function extractGraphEventIdFromNotification(array $notification): string
    {
        $resourceData = isset($notification['resourceData']) && is_array($notification['resourceData'])
            ? $notification['resourceData']
            : [];

        foreach (['id', '@odata.id', 'odata.id'] as $key) {
            if (!empty($resourceData[$key])) {
                return rawurldecode(basename((string)$resourceData[$key]));
            }
        }

        if (!empty($notification['resource'])) {
            return rawurldecode(basename((string)$notification['resource']));
        }

        return '';
    }

    /**
     * @param \Throwable $e
     *
     * @return bool
     */
    private function isGraphEventMissingError(\Throwable $e): bool
    {
        if ((int)$e->getCode() === 404) {
            return true;
        }

        if (method_exists($e, 'getResponseStatusCode') && (int)$e->getResponseStatusCode() === 404) {
            return true;
        }

        return (bool)preg_match('/\\b(404|NotFound|ErrorItemNotFound|ResourceNotFound)\\b/i', $e->getMessage());
    }

    /**
     * Attach a connection to the watch for its calendar, creating both if they do not exist yet.
     *
     * Several connections can point at one calendar - two employees sharing a mailbox, or one employee
     * with the calendar connected twice - and Graph would happily hand out a subscription per connection,
     * delivering every change as many times as there are connections. The registry row is what makes them
     * share one.
     *
     * @param \AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar $outlookCalendar
     * @param Provider                                             $provider
     *
     * @return void
     */
    private function createOrRefreshGraphSubscription($outlookCalendar, Provider $provider): void
    {
        $calendarSync = $this->attachConnectionToCalendarSync($outlookCalendar, $provider);

        if (!$calendarSync) {
            return;
        }

        // Someone else's connection already holds a live watch on this calendar, and its notifications
        // cover this connection too.
        if ($calendarSync->hasLiveSubscription(self::SUBSCRIPTION_RENEW_MARGIN)) {
            return;
        }

        $this->registerGraphSubscription($calendarSync, $outlookCalendar);
    }

    /**
     * Resolve the registry row for a connection's calendar and point the connection at it.
     *
     * @param \AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar $outlookCalendar
     * @param Provider                                             $provider
     *
     * @return CalendarSync|null
     */
    private function attachConnectionToCalendarSync($outlookCalendar, Provider $provider): ?CalendarSync
    {
        if (
            !$outlookCalendar->getId() ||
            !$outlookCalendar->getCalendarId() ||
            !$outlookCalendar->getCalendarId()->getValue()
        ) {
            return null;
        }

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        $connectionId = (int)$outlookCalendar->getId()->getValue();
        $calendarId   = $outlookCalendar->getCalendarId()->getValue();
        $accountId    = $this->resolveAccountId($outlookCalendar, $provider);

        $calendarSync = $calendarSyncRepository->getOrCreate(
            CalendarSync::TYPE_OUTLOOK,
            $accountId,
            $calendarId,
            $connectionId
        );

        if (!$calendarSync || !$calendarSync->getId()) {
            return null;
        }

        $currentSyncId = $outlookCalendar->getSyncId() ? (int)$outlookCalendar->getSyncId()->getValue() : null;
        $syncId        = (int)$calendarSync->getId()->getValue();

        if ($currentSyncId !== $syncId) {
            // The connection may have been watching a different calendar until now, in which case the old
            // watch has to be released before the new one is taken up.
            if ($currentSyncId) {
                $this->detachConnectionFromCalendarSync($connectionId, $currentSyncId);
            }

            $outlookCalendarRepository->updateSyncId($connectionId, $syncId);
            $outlookCalendar->setSyncId(new Id($syncId));
        }

        return $calendarSync;
    }

    /**
     * The mailbox a calendar id belongs to. Graph calendar ids are only meaningful inside their mailbox,
     * so two employees on the same shared calendar in different mailboxes hold different ids for it and
     * must not be merged.
     *
     * When the address cannot be read, the connection gets an identity of its own rather than being merged
     * into someone else's - that degrades to a subscription per connection, which is what happened before
     * the registry existed, instead of pointing two mailboxes at one watch.
     *
     * @param \AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar $outlookCalendar
     * @param Provider                                             $provider
     *
     * @return string
     */
    private function resolveAccountId($outlookCalendar, Provider $provider): string
    {
        if ($outlookCalendar->getAccountId()) {
            return $outlookCalendar->getAccountId();
        }

        $connectionId = (int)$outlookCalendar->getId()->getValue();

        $accountId = '';

        try {
            $identity = $this->fetchMailboxIdentity();

            $accountId = !empty($identity['email']) ? strtolower(trim((string)$identity['email'])) : '';
        } catch (\Throwable $e) {
            $this->logger->warning(
                'OutlookCalendar: could not resolve account for calendar sync',
                [
                    'exception'    => $e,
                    'connectionId' => $connectionId,
                ]
            );
        }

        if ($accountId === '') {
            $accountId = 'connection:' . $connectionId;
        }

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        $outlookCalendarRepository->updateAccountId($connectionId, $accountId);
        $outlookCalendar->setAccountId($accountId);

        return $accountId;
    }

    /**
     * Register a Graph subscription for a calendar and record it on the registry row.
     *
     * @param CalendarSync                                              $calendarSync
     * @param \AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar|null $outlookCalendar connection driving
     *                                                                                   it, null for a
     *                                                                                   watch on the global
     *                                                                                   token
     *
     * @return void
     */
    private function registerGraphSubscription(CalendarSync $calendarSync, $outlookCalendar): void
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        if ($calendarSync->getSubscriptionId()) {
            $this->deleteGraphSubscriptionRemote($calendarSync->getSubscriptionId());

            // The remote subscription is gone, so the stored state describes something that no longer
            // exists. Persisting the clear now matters because the create below can throw: callers only
            // log, and a row still holding a future expiresAt reads as live to hasLiveSubscription(),
            // which would make every renewal path skip it until that stale expiry aged into the margin.
            $calendarSync->setSubscriptionId(null);
            $calendarSync->setExpiresAt(null);
            $calendarSync->setClientState(null);

            $calendarSyncRepository->clearSubscriptionState($calendarSync->getId()->getValue());
        }

        $clientState = bin2hex(random_bytes(16));

        $expiration = (new DateTime('now', new \DateTimeZone('UTC')));
        $expiration->add(new \DateInterval('P2DT12H'));

        $body = [
            'changeType'         => 'created,updated,deleted',
            'notificationUrl'    => $this->getOutlookCalendarWebhookAddress(),
            'resource'           => '/me/calendars/' . $calendarSync->getCalendarId() . '/events',
            'expirationDateTime' => $expiration->format('Y-m-d\TH:i:s.000\Z'),
            'clientState'        => $clientState,
        ];

        $subscription = $this->graph->createRequest('POST', '/subscriptions')
            ->attachBody($body)
            ->setReturnType(Subscription::class)
            ->execute();

        $expiresRaw = $subscription->getExpirationDateTime();
        if ($expiresRaw instanceof \DateTimeInterface) {
            $expiresAt = gmdate('Y-m-d H:i:s', $expiresRaw->getTimestamp());
        } else {
            $expiresAt = $expiresRaw ? gmdate('Y-m-d H:i:s', strtotime((string)$expiresRaw)) : null;
        }

        $calendarSync->setSubscriptionId($subscription->getId());
        $calendarSync->setExpiresAt($expiresAt);
        $calendarSync->setClientState($clientState);

        // Whoever registered the watch is the connection whose token renews and deletes it. A null owner
        // means it runs on the global token instead.
        $ownerConnectionId = $outlookCalendar && $outlookCalendar->getId()
            ? (int)$outlookCalendar->getId()->getValue()
            : null;

        $calendarSync->setOwnerConnectionId($ownerConnectionId);

        $calendarSyncRepository->updateSubscriptionState(
            $calendarSync->getId()->getValue(),
            $calendarSync->getSubscriptionId(),
            $calendarSync->getExpiresAt(),
            $calendarSync->getClientState()
        );

        $calendarSyncRepository->updateOwnerConnectionId(
            $calendarSync->getId()->getValue(),
            $ownerConnectionId
        );
    }

    /**
     * Release a connection's claim on a watch, tearing the watch down only when nothing else needs it.
     *
     * @param int $connectionId
     * @param int $syncId
     *
     * @return void
     */
    private function detachConnectionFromCalendarSync($connectionId, $syncId): void
    {
        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        try {
            /** @var CalendarSync|null $calendarSync */
            $calendarSync = $calendarSyncRepository->getById($syncId);
        } catch (\Throwable $e) {
            return;
        }

        if (!$calendarSync) {
            return;
        }

        $remaining = $outlookCalendarRepository->getConnectionsBySyncId($syncId, $connectionId);

        if ($remaining) {
            // Still in use. Hand the watch over if the connection leaving was the one driving it - the
            // subscription itself stays valid, since a token for the same mailbox addresses it too.
            if ((int)$calendarSync->getOwnerConnectionId() === (int)$connectionId) {
                $calendarSyncRepository->updateOwnerConnectionId($syncId, $remaining[0]['id']);
            }

            return;
        }

        // Employees on the global connection reach a calendar through their user row rather than a
        // connection row, so counting connections alone would tear down a subscription they still rely
        // on. Drop the owner first and ask as the owner-less row this is about to become: while an owner
        // is set, the account check short circuits, and it is the part that matters here because Outlook
        // calendar ids are mailbox scoped and only mean this calendar within the global account.
        $calendarSync->setOwnerConnectionId(null);

        if ($this->globalEmployeesForCalendarSync($calendarSync)) {
            $calendarSyncRepository->updateOwnerConnectionId($syncId, null);

            return;
        }

        if ($calendarSync->getSubscriptionId()) {
            $this->deleteGraphSubscriptionRemote($calendarSync->getSubscriptionId());
        }

        $calendarSyncRepository->delete($syncId);
    }

    /**
     * @param array $calendarList
     *
     * @return string|null
     */
    private function pickPrimaryCalendarId(array $calendarList): ?string
    {
        foreach ($calendarList as $calendar) {
            if (!empty($calendar['isDefault']) && !empty($calendar['id'])) {
                return $calendar['id'];
            }
        }

        foreach ($calendarList as $calendar) {
            if (!empty($calendar['owner']) && !empty($calendar['id'])) {
                return $calendar['id'];
            }
        }

        return !empty($calendarList[0]['id']) ? $calendarList[0]['id'] : null;
    }

    /**
     * @param string $subscriptionId
     *
     * @return void
     */
    private function deleteGraphSubscriptionRemote($subscriptionId): void
    {
        if (!$subscriptionId) {
            return;
        }

        // Callers are responsible for authorizing the right mailbox first. The client object is always
        // present, so authorization is tracked separately.
        if (!$this->graphAuthorized) {
            $this->logger->warning(
                'OutlookCalendar: cannot delete subscription without an authorized client, leaving it to expire',
                ['subscriptionId' => $subscriptionId]
            );

            return;
        }

        try {
            $this->graph->createRequest('DELETE', '/subscriptions/' . rawurlencode($subscriptionId))->execute();
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: DELETE subscription failed',
                [
                    'exception'      => $e,
                    'subscriptionId' => $subscriptionId,
                ]
            );
        }
    }

    /**
     * Whether an unowned watch still belongs to the account the global connection currently uses.
     *
     * A Graph calendar id is only meaningful inside its mailbox, and the user row records the employee's
     * selection without recording which mailbox it came from - there is no per employee account to carry,
     * because a site has one global connection at a time. So the account on the watch is compared against
     * the one the settings hold now: if the global connection has since been pointed at another mailbox,
     * selections left over from the previous one must not keep that watch alive or authorize changes
     * through it.
     *
     * @param CalendarSync $calendarSync
     *
     * @return bool
     */
    private function isGlobalAccountCurrent(CalendarSync $calendarSync): bool
    {
        // An owned watch runs on its connection's token, so the global account says nothing about it.
        if ($calendarSync->getOwnerConnectionId()) {
            return true;
        }

        $currentAccountId = !empty($this->outlookCalendarSettings['accountId'])
            ? (string)$this->outlookCalendarSettings['accountId']
            : '';

        // No account recorded yet - a watch from before it was resolved. Matching on the calendar id
        // alone is what happened before, and is better than refusing every global watch.
        if ($currentAccountId === '') {
            return true;
        }

        return $calendarSync->getAccountId() === $currentAccountId;
    }

    /**
     * Employees whose user row selects the calendar this watch is for, empty when the watch belongs to a
     * superseded account.
     *
     * @param CalendarSync $calendarSync
     *
     * @return int[]
     * @throws QueryExecutionException
     */
    private function globalEmployeesForCalendarSync(CalendarSync $calendarSync): array
    {
        if (!$this->isGlobalAccountCurrent($calendarSync)) {
            return [];
        }

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        return $providerRepository->getProviderIdsByCalendarId(
            $calendarSync->getCalendarId(),
            'outlookCalendarId'
        );
    }

    /**
     * Authorize Graph with the access token from the plugin settings, independent of any employee.
     *
     * @return bool
     */
    private function authorizeWithGlobalToken(): bool
    {
        if (!$this->isAccessTokenSet()) {
            $this->graphAuthorized = false;

            return false;
        }

        /** @var OutlookCalendarMiddlewareService $outlookCalendarMiddlewareService */
        $outlookCalendarMiddlewareService = $this->container->get(
            'infrastructure.outlook.calendar.middleware.service'
        );

        // Passing no calendar is what selects the settings token rather than a connection's own.
        $graph = $outlookCalendarMiddlewareService->getGraph(null);

        if ($graph === null) {
            $this->graphAuthorized = false;

            return false;
        }

        $this->graph = $graph;
        $this->graphAuthorized = true;

        return true;
    }

    /**
     * Authorize purely in order to tear a subscription down.
     *
     * A subscription being removed has nobody left using it, so authorizeForCalendarSync() would refuse
     * it. Any credential for the right mailbox will do to send the delete - the owning connection if its
     * row still exists, otherwise the global token.
     *
     * @param CalendarSync $calendarSync
     *
     * @return bool whether Graph is now authorized
     */
    private function authorizeForTeardown(CalendarSync $calendarSync): bool
    {
        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        try {
            $ownerConnectionId = $calendarSync->getOwnerConnectionId();

            if ($ownerConnectionId) {
                $providerId = $outlookCalendarRepository->getUserIdByOutlookCalendarRowId($ownerConnectionId);

                if ($providerId) {
                    $provider = $providerRepository->getById($providerId);
                    $provider->setOutlookCalendar($outlookCalendarRepository->getById($ownerConnectionId));

                    return $this->authorizeProvider($provider);
                }
            }

            // No connection left to borrow, so the watch belongs to the global connection and the token
            // for it comes from the settings. Going through an employee would be both wasteful - a remote
            // call each, for every orphaned row - and wrong, since an employee who happens to have a
            // connection of their own would authorize their mailbox instead of the global one.
            return $this->authorizeWithGlobalToken();
        } catch (\Throwable $e) {
            $this->logger->warning(
                'OutlookCalendar: could not authorize to delete a subscription',
                [
                    'exception' => $e,
                    'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );

            return false;
        }
    }

    /**
     * Authorize Graph with whatever credential drives a watch.
     *
     * An owned watch uses that connection's token. An unowned one belongs to the global Settings page
     * connection, so it uses the token from the plugin settings and only needs some employee attached to
     * the calendar to hang the request on.
     *
     * @param CalendarSync $calendarSync
     *
     * @return \AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar|null|false the connection driving the
     *                                                                        watch, null when it runs on
     *                                                                        the global token, false when
     *                                                                        authorization failed
     */
    private function authorizeForCalendarSync(CalendarSync $calendarSync)
    {
        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $ownerConnectionId = $calendarSync->getOwnerConnectionId();

        try {
            if ($ownerConnectionId) {
                $providerId = $outlookCalendarRepository->getUserIdByOutlookCalendarRowId($ownerConnectionId);

                // The owning connection may be gone - a disconnect that removed the row without releasing
                // the watch. Hand it to another connection still attached before giving up.
                if (!$providerId && $calendarSync->getId()) {
                    $syncId = (int)$calendarSync->getId()->getValue();

                    $remaining = $outlookCalendarRepository->getConnectionsBySyncId($syncId);

                    if ($remaining) {
                        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

                        $calendarSyncRepository->updateOwnerConnectionId($syncId, $remaining[0]['id']);
                        $calendarSync->setOwnerConnectionId($remaining[0]['id']);

                        $ownerConnectionId = $remaining[0]['id'];
                        $providerId        = $remaining[0]['userId'];
                    }
                }

                if (!$providerId) {
                    return false;
                }

                $outlookCalendar = $outlookCalendarRepository->getById($ownerConnectionId);

                $provider = $providerRepository->getById($providerId);
                $provider->setOutlookCalendar($outlookCalendar);

                return $this->authorizeProvider($provider) ? $outlookCalendar : false;
            }

            if (!$this->isAccessTokenSet()) {
                return false;
            }

            $employees = $this->globalEmployeesForCalendarSync($calendarSync);

            if (!$employees) {
                return false;
            }

            $provider = $providerRepository->getById($employees[0]);

            return $this->authorizeProvider($provider) ? null : false;
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: authorize for calendar sync failed',
                [
                    'exception' => $e,
                    'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );

            return false;
        }
    }

    /**
     * @param CalendarSync                                         $calendarSync
     * @param \AmeliaBooking\Domain\Entity\Outlook\OutlookCalendar $outlookCalendar connection driving it
     *
     * @return void
     */
    private function renewGraphSubscription(CalendarSync $calendarSync, $outlookCalendar): void
    {
        if ($calendarSync->hasLiveSubscription(self::SUBSCRIPTION_RENEW_MARGIN)) {
            return;
        }

        try {
            $this->registerGraphSubscription($calendarSync, $outlookCalendar);
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: subscription renew failed',
                [
                    'exception' => $e,
                    'syncId'    => $calendarSync->getId() ? $calendarSync->getId()->getValue() : null,
                ]
            );
        }
    }

    /**
     * @param string $calendarId
     * @param string $eventId
     *
     * @return Event|null
     */
    private function fetchGraphEventForTwoWay($calendarId, $eventId): ?Event
    {
        $expand = rawurlencode(
            "singleValueExtendedProperties(\$filter=id eq 'Integer " . self::GUID . " Name appointmentId')"
        );

        return $this->graph->createRequest(
            'GET',
            sprintf(
                '/me/calendars/%s/events/%s?$expand=%s',
                rawurlencode($calendarId),
                rawurlencode($eventId),
                $expand
            )
        )
            ->setHeader('Prefer', 'outlook.timezone="' . DateTimeService::getTimeZone()->getName() . '"')
            ->setReturnType(Event::class)
            ->execute();
    }

    /**
     * @param Event        $graphEvent
     * @param string       $graphEventId
     * @param CalendarSync $calendarSync calendar the notification arrived for
     *
     * @return void
     */
    private function applyOutlookTwoWayUpsert(Event $graphEvent, $graphEventId, CalendarSync $calendarSync): void
    {
        $ameliaNumericId = $this->readAmeliaNumericIdFromGraphEvent($graphEvent);
        if (!$ameliaNumericId) {
            return;
        }

        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');
        /** @var CalendarApplicationService $calendarApplicationService */
        $calendarApplicationService = $this->container->get('application.calendar.service');

        $appointments = $appointmentRepository->getByFieldValue('outlookCalendarEventId', $graphEventId);
        if ($appointments->length()) {
            $appointmentKeys = $appointments->keys();
            /** @var Appointment $appointment */
            $appointment = $appointments->getItem($appointmentKeys[0]);
            if ((int)$appointment->getId()->getValue() !== $ameliaNumericId) {
                return;
            }

            if (!$this->isChangeAllowedForCalendar($calendarSync, $appointment->getProviderId())) {
                return;
            }

            $appointment = $appointmentRepository->getById($appointment->getId()->getValue());

            if ($this->isGraphEventCancelled($graphEvent)) {
                $calendarApplicationService->handleAppointmentReject($appointment, 'outlookCalendar');
                return;
            }

            [$startString, $endString] = $this->graphEventTimesToSiteStrings($graphEvent);
            $calendarApplicationService->handleAppointmentReschedule(
                $appointment,
                $startString,
                $endString,
                'outlookCalendar'
            );
            return;
        }

        $periods = $eventPeriodsRepository->getByFieldValue('outlookCalendarEventId', $graphEventId);
        if (!$periods->length()) {
            return;
        }

        $periodKeys = $periods->keys();
        /** @var EventPeriod $eventPeriod */
        $eventPeriod = $periods->getItem($periodKeys[0]);
        if ((int)$eventPeriod->getEventId()->getValue() !== $ameliaNumericId) {
            return;
        }

        $event = $calendarApplicationService->getChangedEvent($eventPeriod);

        if (!$event || !$this->isChangeAllowedForCalendar($calendarSync, $event->getOrganizerId())) {
            return;
        }

        if ($this->isGraphEventCancelled($graphEvent)) {
            $calendarApplicationService->handleEventPeriodDelete($event, $eventPeriod, 'outlookCalendar');
            return;
        }

        [$startString, $endString] = $this->graphEventTimesToSiteStrings($graphEvent);
        $calendarApplicationService->handleEventPeriodReschedule(
            $event,
            $eventPeriod,
            $startString,
            $endString,
            'outlookCalendar'
        );
    }

    /**
     * @param string       $graphEventId
     * @param CalendarSync $calendarSync calendar the notification arrived for
     *
     * @return void
     */
    private function applyOutlookTwoWayDeletion(string $graphEventId, CalendarSync $calendarSync): void
    {
        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var EventPeriodsRepository $eventPeriodsRepository */
        $eventPeriodsRepository = $this->container->get('domain.booking.event.period.repository');
        /** @var CalendarApplicationService $calendarApplicationService */
        $calendarApplicationService = $this->container->get('application.calendar.service');

        /** @var Collection $appointments */
        $appointments = $appointmentRepository->getByFieldValue('outlookCalendarEventId', $graphEventId);

        if ($appointments->length()) {
            $keys = $appointments->keys();

            /** @var Appointment $appointment */
            $appointment = $appointments->getItem($keys[0]);

            if (!$this->isChangeAllowedForCalendar($calendarSync, $appointment->getProviderId())) {
                return;
            }

            $appointment = $appointmentRepository->getById($appointment->getId()->getValue());
            $calendarApplicationService->handleAppointmentReject($appointment, 'outlookCalendar');
            return;
        }

        /** @var Collection $periods */
        $periods = $eventPeriodsRepository->getByFieldValue('outlookCalendarEventId', $graphEventId);

        if ($periods->length()) {
            $pKeys = $periods->keys();

            /** @var EventPeriod $eventPeriod */
            $eventPeriod = $periods->getItem($pKeys[0]);
            $event = $calendarApplicationService->getChangedEvent($eventPeriod);

            if (!$event || !$this->isChangeAllowedForCalendar($calendarSync, $event->getOrganizerId())) {
                return;
            }

            $calendarApplicationService->handleEventPeriodDelete($event, $eventPeriod, 'outlookCalendar');
        }
    }

    /**
     * The calendar scoped guard: a change may only be applied to a booking whose employee is actually
     * connected to the calendar the notification came from.
     *
     * An event id is not scoped to a calendar, so matching one is not on its own proof that the change
     * belongs to this calendar. Ownership cannot be a single employee either - several employees can share
     * one calendar and one watch, so any of them is a legitimate target.
     *
     * The connection rows are the source of truth here. Once the calendar an event was written to is
     * recorded on the booking itself, this becomes a direct comparison instead.
     *
     * @param CalendarSync $calendarSync
     * @param Id|null      $providerId
     *
     * @return bool
     */
    private function isChangeAllowedForCalendar(CalendarSync $calendarSync, $providerId): bool
    {
        if (!$providerId || !$calendarSync->getId()) {
            return false;
        }

        /** @var OutlookCalendarRepository $outlookCalendarRepository */
        $outlookCalendarRepository = $this->container->get('domain.outlook.calendar.repository');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        try {
            $attached = $outlookCalendarRepository->isProviderAttachedToSync(
                (int)$calendarSync->getId()->getValue(),
                (int)$providerId->getValue()
            );

            if ($attached) {
                return true;
            }

            // Employees on the global connection have no connection row; their selection is the user row,
            // which only counts while the watch still belongs to the account that connection uses.
            return $this->isGlobalAccountCurrent($calendarSync)
                && $providerRepository->isProviderUsingCalendarId(
                    (int)$providerId->getValue(),
                    $calendarSync->getCalendarId(),
                    'outlookCalendarId'
                );
        } catch (\Throwable $e) {
            $this->logger->error(
                'OutlookCalendar: could not verify calendar ownership for change',
                [
                    'exception'  => $e,
                    'providerId' => $providerId->getValue(),
                ]
            );

            return false;
        }
    }

    /**
     * @param Event $graphEvent
     *
     * @return int|null
     */
    private function readAmeliaNumericIdFromGraphEvent(Event $graphEvent): ?int
    {
        $props = $graphEvent->getSingleValueExtendedProperties();
        if (!$props) {
            return null;
        }

        $needle = 'Integer ' . self::GUID . ' Name appointmentId';
        foreach ($props as $prop) {
            if ($this->getGraphPropertyId($prop) === $needle) {
                $value = (int)$this->getGraphPropertyValue($prop);
                return $value > 0 ? $value : null;
            }
        }

        return null;
    }

    /**
     * @param Event $graphEvent
     *
     * @return bool
     */
    private function isGraphEventCancelled(Event $graphEvent): bool
    {
        $cancelled = $graphEvent->getIsCancelled();

        return $cancelled === true || $cancelled === 1 || $cancelled === '1';
    }

    /**
     * @param Event $graphEvent
     *
     * @return array
     * @throws DateInvalidTimeZoneException
     * @throws Exception
     */
    private function graphEventTimesToSiteStrings(Event $graphEvent): array
    {
        $start = $graphEvent->getStart();
        $end = $graphEvent->getEnd();

        $startTz = $start && $start->getTimeZone() ? $start->getTimeZone() : 'UTC';
        $endTz = $end && $end->getTimeZone() ? $end->getTimeZone() : 'UTC';

        $startDt = new DateTime($start->getDateTime(), new \DateTimeZone($startTz));
        $endDt = new DateTime($end->getDateTime(), new \DateTimeZone($endTz));

        $startDt->setTimezone(DateTimeService::getTimeZone());
        $endDt->setTimezone(DateTimeService::getTimeZone());

        return [
            $startDt->format('Y-m-d H:i:s'),
            $endDt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array $outlookSettings
     * @param array $savedOutlookSettings
     *
     * @return CommandResult
     */
    public function validateCredentials(array $outlookSettings, array $savedOutlookSettings): CommandResult
    {
        $validateResult = OutlookCredentialsValidatorService::validateCredentials(
            $outlookSettings,
            $savedOutlookSettings
        );

        if ($validateResult instanceof CommandResult) {
            return $validateResult;
        }

        $result = new CommandResult();
        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData($validateResult);

        return $result;
    }
}
