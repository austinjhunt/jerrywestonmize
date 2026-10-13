<?php

namespace AmeliaBooking\Application\Services\Calendar;

use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\Status;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Apple\AbstractAppleCalendarService;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarService;
use AmeliaVendor\Sabre\VObject\Reader;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * Builds read-only calendar rows for busy times from Google / Outlook / Apple calendars.
 */
class CalendarExternalIntegrationEventsService
{
    /** @var Container */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param array         $queryParams
     * @param string        $timeZone
     * @param AbstractUser  $user
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect(array $queryParams, $timeZone, AbstractUser $user): array
    {
        if ($user->getType() === Entities::CUSTOMER) {
            return [];
        }

        if (
            empty($queryParams['entitiesToShow']) ||
            !in_array('appointments', (array)$queryParams['entitiesToShow'], true)
        ) {
            return [];
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $statuses = isset($queryParams['statuses']) ? (array)$queryParams['statuses'] : [];

        $showGoogle  = $this->filterEnabled($statuses, 'googleCalendarEvents');
        $showOutlook = $this->filterEnabled($statuses, 'outlookCalendarEvents');
        $showApple   = $this->filterEnabled($statuses, 'appleCalendarEvents');

        if (!$showGoogle && !$showOutlook && !$showApple) {
            return [];
        }

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        $criteria = [
            'dates'          => [
                $queryParams['calendarStartDate'],
                $queryParams['calendarEndDate'],
            ],
            'providerStatus' => Status::VISIBLE,
        ];

        if (!empty($queryParams['providers'])) {
            $criteria['providers'] = (array)$queryParams['providers'];
        }

        if (!empty($queryParams['locations'])) {
            $criteria['locations'] = (array)$queryParams['locations'];
        }

        if (!empty($queryParams['services'])) {
            $criteria['services'] = (array)$queryParams['services'];
        }

        $providers = $providerRepository->getWithSchedule($criteria)->getItems();

        $periodStart = DateTimeService::getCustomDateTimeRFC3339(
            $queryParams['calendarStartDate'] . ' 00:00:00'
        );
        $periodEnd = DateTimeService::getCustomDateTimeRFC3339(
            $queryParams['calendarEndDate'] . ' 23:59:59'
        );
        $periodStartEnd = explode('T', $periodStart)[0] . 'T' . explode('T', $periodEnd)[1];

        $rows = [];

        // Busy rows are provider-agnostic, and several employees can point at the very same external calendar:
        // the global Settings connection hands one calendar to every employee, and employees connecting on
        // their own can pick a calendar a colleague already uses. Fetching per employee then renders the same
        // event once per employee (and burns one API round trip each), so every calendar is fetched only once.
        $fetchedGoogleCalendars  = [];
        $fetchedOutlookCalendars = [];
        $fetchedAppleCalendars   = [];

        /** @var Provider $provider */
        foreach ($providers as $provider) {
            $providerArray = $provider->toArray();

            if ($showGoogle || $showOutlook) {
                $this->mergeConnectedCalendarAccountsIntoProviderArray(
                    $providerArray,
                    (int)$provider->getId()->getValue(),
                    $providerRepository,
                    $settingsService,
                    $showGoogle,
                    $showOutlook
                );
            }

            if ($showGoogle && $settingsService->isFeatureEnabled('googleCalendar')) {
                $googleCalendarId = $this->resolveCalendarId($providerArray, 'googleCalendar', 'googleCalendarId');

                if ($googleCalendarId === '' || !isset($fetchedGoogleCalendars[$googleCalendarId])) {
                    try {
                        /** @var AbstractGoogleCalendarService $googleCalendarService */
                        $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');
                        $events = $googleCalendarService->getEvents($providerArray, $periodStart, $periodStartEnd, $periodEnd, []);
                        // The completed round trip is what dedupes, not what it returned: a calendar with no
                        // events in the period is still fetched, so colleagues sharing it must not refetch it.
                        if ($googleCalendarId !== '') {
                            $fetchedGoogleCalendars[$googleCalendarId] = true;
                        }
                        foreach ($events as $event) {
                            $row = $this->mapGoogleEvent($event, $timeZone);
                            if ($row !== null) {
                                $rows[] = $row;
                            }
                        }
                    } catch (Exception $e) {
                    }
                }
            }

            if ($showOutlook && $settingsService->isFeatureEnabled('outlookCalendar')) {
                $outlookCalendarId = $this->resolveCalendarId($providerArray, 'outlookCalendar', 'outlookCalendarId');

                if ($outlookCalendarId === '' || !isset($fetchedOutlookCalendars[$outlookCalendarId])) {
                    try {
                        /** @var AbstractOutlookCalendarService $outlook */
                        $outlook = $this->container->get('infrastructure.outlook.calendar.service');
                        $events  = $outlook->getEvents($providerArray, $periodStart, $periodStartEnd, $periodEnd, []);
                        if ($outlookCalendarId !== '') {
                            $fetchedOutlookCalendars[$outlookCalendarId] = true;
                        }
                        foreach ($events as $event) {
                            $row = $this->mapOutlookEvent($event, $timeZone);
                            if ($row !== null) {
                                $rows[] = $row;
                            }
                        }
                    } catch (Exception $e) {
                    }
                }
            }

            if ($showApple && $settingsService->isFeatureEnabled('appleCalendar')) {
                try {
                    $rows = array_merge(
                        $rows,
                        $this->mapAppleBusyForProvider($provider, $queryParams, $timeZone, $fetchedAppleCalendars)
                    );
                } catch (Exception $e) {
                }
            }
        }

        return $rows;
    }

    /**
     * Calendar the given employee is read through, the same way the calendar services resolve it: the connected
     * account row first, then the legacy column filled in by the global Settings connection. Google ids are
     * globally unique and Graph ids embed the mailbox, so the id alone identifies a calendar across accounts.
     *
     * @param array<string, mixed> $providerArray
     * @param string               $entityKey
     * @param string               $legacyKey
     *
     * @return string
     */
    private function resolveCalendarId(array $providerArray, $entityKey, $legacyKey): string
    {
        $calendar = isset($providerArray[$entityKey]) && is_array($providerArray[$entityKey])
            ? $providerArray[$entityKey]
            : [];

        // UserFactory hydrates the connected calendar only when the row carries a token, and the calendar
        // services read the legacy column whenever it did not - so a tokenless row is not the fetched calendar.
        if (isset($calendar['token']) && !empty($calendar['calendarId'])) {
            return (string)$calendar['calendarId'];
        }

        return !empty($providerArray[$legacyKey]) ? (string)$providerArray[$legacyKey] : '';
    }

    /**
     * @param array $statuses
     * @param string $flag
     *
     * @return bool
     */
    private function filterEnabled(array $statuses, $flag)
    {
        return in_array($flag, $statuses, true);
    }

    /**
     * Multi-account / personal OAuth rows live outside the joined calendar entities used by Provider::toArray().
     * GoogleCalendarService::getEvents / OutlookCalendarService::getEvents expect token + calendar id on the array
     * passed to ProviderFactory::create().
     *
     * @param array<string, mixed> $providerArray
     */
    private function mergeConnectedCalendarAccountsIntoProviderArray(
        array &$providerArray,
        int $providerId,
        ProviderRepository $providerRepository,
        SettingsService $settingsService,
        bool $forGoogle,
        bool $forOutlook
    ) {
        if ($forGoogle && $settingsService->isFeatureEnabled('googleCalendar')) {
            $googleCalendar = isset($providerArray['googleCalendar']) && is_array($providerArray['googleCalendar'])
                ? $providerArray['googleCalendar']
                : [];

            $resolvedCalId = isset($googleCalendar['calendarId']) && $googleCalendar['calendarId'] !== ''
                ? (string)$googleCalendar['calendarId']
                : '';
            $legacyCalId = !empty($providerArray['googleCalendarId'])
                ? (string)$providerArray['googleCalendarId']
                : '';
            if ($resolvedCalId === '' && $legacyCalId !== '') {
                $resolvedCalId = $legacyCalId;
            }

            $resolvedToken = !empty($googleCalendar['token']) ? (string)$googleCalendar['token'] : '';

            if ($resolvedToken === '' || $resolvedCalId === '') {
                $accounts = $providerRepository->getGoogleCalendarAccounts($providerId);
                foreach ($accounts as $account) {
                    if ($resolvedToken === '' && !empty($account['token'])) {
                        $resolvedToken = (string)$account['token'];
                        if (!empty($account['id'])) {
                            $googleCalendar['id'] = $account['id'];
                        }
                    }
                    if ($resolvedCalId === '' && !empty($account['calendarId'])) {
                        $resolvedCalId = (string)$account['calendarId'];
                    }
                }
            }

            if ($resolvedToken !== '' && $resolvedCalId !== '') {
                $providerArray['googleCalendar'] = array_merge($googleCalendar, [
                    'token'      => $resolvedToken,
                    'calendarId' => $resolvedCalId,
                ]);
            }
        }

        if ($forOutlook && $settingsService->isFeatureEnabled('outlookCalendar')) {
            $outlookCalendar = isset($providerArray['outlookCalendar']) && is_array($providerArray['outlookCalendar'])
                ? $providerArray['outlookCalendar']
                : [];

            $resolvedCalId = isset($outlookCalendar['calendarId']) && $outlookCalendar['calendarId'] !== ''
                ? (string)$outlookCalendar['calendarId']
                : '';
            $legacyCalId = !empty($providerArray['outlookCalendarId'])
                ? (string)$providerArray['outlookCalendarId']
                : '';
            if ($resolvedCalId === '' && $legacyCalId !== '') {
                $resolvedCalId = $legacyCalId;
            }

            $resolvedToken = !empty($outlookCalendar['token']) ? (string)$outlookCalendar['token'] : '';

            if ($resolvedToken === '' || $resolvedCalId === '') {
                $accounts = $providerRepository->getOutlookCalendarAccounts($providerId);
                foreach ($accounts as $account) {
                    if ($resolvedToken === '' && !empty($account['token'])) {
                        $resolvedToken = (string)$account['token'];
                        if (!empty($account['id'])) {
                            $outlookCalendar['id'] = $account['id'];
                        }
                    }
                    if ($resolvedCalId === '' && !empty($account['calendarId'])) {
                        $resolvedCalId = (string)$account['calendarId'];
                    }
                }
            }

            if ($resolvedToken !== '' && $resolvedCalId !== '') {
                $providerArray['outlookCalendar'] = array_merge($outlookCalendar, [
                    'token'      => $resolvedToken,
                    'calendarId' => $resolvedCalId,
                ]);
            }
        }
    }

    /**
     * @param object $event Google Calendar API event (prefixed or vendor namespace).
     * @param string $timeZone
     *
     * @return array<string, mixed>|null
     * @throws \DateInvalidTimeZoneException
     */
    private function mapGoogleEvent($event, $timeZone)
    {
        // Respect Busy/Free: "transparent" means the event does not block time, so it is not shown as busy.
        if (method_exists($event, 'getTransparency') && $event->getTransparency() === 'transparent') {
            return null;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $googleSettings = $settingsService->getCategorySettings('googleCalendar');

        if ($this->isAmeliaGoogleEvent($event) && !$googleSettings['showCalendarBookings']) {
            return null;
        }

        $start = $event->getStart();
        $end   = $event->getEnd();

        if ($start->getDateTime()) {
            $startDt = DateTimeService::getCustomDateTimeObject($start->getDateTime());
            $endDt   = DateTimeService::getCustomDateTimeObject($end->getDateTime());
        } elseif ($start->getDate()) {
            $startDt = new DateTime($start->getDate() . ' 00:00:00', new DateTimeZone('UTC'));
            $endDt   = new DateTime($end->getDate() . ' 00:00:00', new DateTimeZone('UTC'));
            $endDt->modify('-1 day')->setTime(23, 59, 59);
        } else {
            return null;
        }

        if ($timeZone) {
            $tz = new DateTimeZone($timeZone);
            $startDt = $startDt->setTimezone($tz);
            $endDt = $endDt->setTimezone($tz);
        }

        // Respect privacy: do not reveal titles of private/confidential events.
        $visibility = method_exists($event, 'getVisibility') ? (string)$event->getVisibility() : '';
        $isPrivate  = in_array($visibility, ['private', 'confidential'], true);

        $title = (!$isPrivate && method_exists($event, 'getSummary') && $event->getSummary())
            ? $event->getSummary()
            : __('Busy', 'wpamelia');

        return $this->buildRow(
            'externalGoogle',
            $title,
            $startDt,
            $endDt
        );
    }

    /**
     * @param object   $event Microsoft Graph calendar event.
     * @param string   $timeZone
     *
     * @return array<string, mixed>|null
     */
    private function mapOutlookEvent($event, $timeZone)
    {
        // Respect Busy/Free: "free" means the event does not block time, so it is not shown as busy.
        $showAs = method_exists($event, 'getShowAs') ? $event->getShowAs() : null;
        if ($showAs !== null && $showAs->value() === 'free') {
            return null;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $outlookSettings = $settingsService->getCategorySettings('outlookCalendar');

        if ($this->isAmeliaOutlookEvent($event) && !$outlookSettings['showCalendarBookings']) {
            return null;
        }

        $start = $event->getStart();
        $end   = $event->getEnd();

        $startDateTime = $start->getDateTime();
        $endDateTime   = $end->getDateTime();

        if ($startDateTime instanceof DateTimeInterface) {
            $startDateTime = $startDateTime->format('Y-m-d H:i:s');
        }
        if ($endDateTime instanceof DateTimeInterface) {
            $endDateTime = $endDateTime->format('Y-m-d H:i:s');
        }

        if (!is_string($startDateTime) || $startDateTime === '' || !is_string($endDateTime) || $endDateTime === '') {
            return null;
        }

        $startDt = DateTimeService::getCustomDateTimeObject($startDateTime);
        $endDt   = DateTimeService::getCustomDateTimeObject($endDateTime);

        if ($timeZone) {
            $tz = new DateTimeZone($timeZone);
            $startDt = $startDt->setTimezone($tz);
            $endDt = $endDt->setTimezone($tz);
        }

        // Respect privacy: do not reveal subjects of private/confidential events.
        $sensitivity = method_exists($event, 'getSensitivity') ? $event->getSensitivity() : null;
        $isPrivate   = $sensitivity !== null && in_array($sensitivity->value(), ['private', 'confidential'], true);

        $subject = $event->getSubject() ?: null;
        $title   = (!$isPrivate && $subject) ? (string)$subject : __('Busy', 'wpamelia');

        return $this->buildRow(
            'externalOutlook',
            $title,
            $startDt,
            $endDt
        );
    }

    /**
     * @param Provider                  $provider
     * @param array                     $queryParams
     * @param string                    $timeZone
     * @param array<string, bool>       $fetchedCalendars Calendars already read for another employee.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapAppleBusyForProvider(
        Provider $provider,
        array $queryParams,
        $timeZone,
        array &$fetchedCalendars
    ): array {
        $rows = [];

        /** @var AbstractAppleCalendarService $apple */
        $apple = $this->container->get('infrastructure.apple.calendar.service');

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');
        $appleSettings   = $settingsService->getCategorySettings('appleCalendar');

        $employeeAppleCalendar = $provider->getEmployeeAppleCalendar()
            ? $provider->getEmployeeAppleCalendar()->toArray()
            : null;

        $appleId = $employeeAppleCalendar && !empty($employeeAppleCalendar['iCloudId'])
            ? $employeeAppleCalendar['iCloudId']
            : ($appleSettings['clientID'] ?? '');

        $appPassword = $employeeAppleCalendar && !empty($employeeAppleCalendar['appSpecificPassword'])
            ? $employeeAppleCalendar['appSpecificPassword']
            : ($appleSettings['clientSecret'] ?? '');

        if ($appleId === '' || $appPassword === '') {
            return $rows;
        }

        $calendarId = $provider->getAppleCalendarId() && $provider->getAppleCalendarId()->getValue()
            ? $provider->getAppleCalendarId()->getValue()
            : null;

        if (
            ($calendarId === null || $calendarId === '') &&
            $employeeAppleCalendar &&
            !empty($employeeAppleCalendar['iCloudId'])
        ) {
            $calendars = $apple->getCalendars($appleId, $appPassword);
            if (is_array($calendars)) {
                foreach ($calendars as $calendar) {
                    if (
                        isset($calendar['name'], $calendar['id'], $calendar['privilege']) &&
                        $calendar['name'] !== '' &&
                        $calendar['id'] !== '' &&
                        $calendar['privilege'] === 'write'
                    ) {
                        $calendarId = $calendar['id'];
                        break;
                    }
                }
            }
        }

        if ($calendarId === null || $calendarId === '') {
            return $rows;
        }

        // iCloud calendar paths are account scoped, so identity here is (account, calendar).
        $calendarKey = $appleId . '|' . $calendarId;

        if (isset($fetchedCalendars[$calendarKey])) {
            return $rows;
        }

        $startDateTime = new DateTime(
            $queryParams['calendarStartDate'] . ' 00:00:00',
            new DateTimeZone($timeZone ?: 'UTC')
        );
        $endDateTime = new DateTime(
            $queryParams['calendarEndDate'] . ' 23:59:59',
            new DateTimeZone($timeZone ?: 'UTC')
        );

        $serializedList = $apple->getCalendarEvents(
            $appleId,
            $appPassword,
            null,
            $calendarId,
            $startDateTime,
            $endDateTime,
            $provider
        );

        // Marked on the fetch, not on the mapped rows: a period holding only Amelia's own events maps to
        // nothing, yet the calendar was read and must not be read again for the next employee on it.
        $fetchedCalendars[$calendarKey] = true;

        foreach ($serializedList as $serialized) {
            $data = Reader::read($serialized);
            if (!isset($data->VFREEBUSY)) {
                continue;
            }
            if (isset($data->UID) && $this->isAmeliaAppleEventUid((string)$data->UID->getValue())) {
                continue;
            }
            $fb = $data->VFREEBUSY;
            if (!isset($fb->DTSTART) || !isset($fb->DTEND)) {
                continue;
            }
            $startDt = $fb->DTSTART->getDateTime();
            $endDt   = $fb->DTEND->getDateTime();
            if ($timeZone) {
                $tz = new DateTimeZone($timeZone);
                $startDt = $startDt->setTimezone($tz);
                $endDt = $endDt->setTimezone($tz);
            }
            $summary = (isset($data->SUMMARY) && (string)$data->SUMMARY->getValue() !== '')
                ? (string)$data->SUMMARY->getValue()
                : __('Busy', 'wpamelia');
            $rows[]  = $this->buildRow(
                'externalApple',
                $summary,
                $startDt,
                $endDt
            );
        }

        return $rows;
    }

    /**
     * Events Amelia wrote into the calendar itself carry a marker its insertEvent() stamps on them. The
     * appointment or event behind such a row is already rendered on this calendar, so keeping the external
     * copy would show every booking twice - once as the booking, once as a grey busy block over it.
     *
     * @param object $event Google Calendar API event.
     *
     * @return bool
     */
    private function isAmeliaGoogleEvent($event): bool
    {
        if (!method_exists($event, 'getExtendedProperties')) {
            return false;
        }

        $extendedProperties = $event->getExtendedProperties();

        if ($extendedProperties === null) {
            return false;
        }

        $shared = $extendedProperties->shared;

        // Google returns the value as the string "true", so only the marker's presence is meaningful.
        return is_array($shared) && array_key_exists('ameliaEvent', $shared);
    }

    /**
     * Outlook's equivalent of the Google marker: the single-value extended property insertEvent() sets.
     * getEvents() asks Graph to expand exactly that property, so it is on every event that carries it.
     *
     * @param object $event Microsoft Graph calendar event.
     *
     * @return bool
     */
    private function isAmeliaOutlookEvent($event): bool
    {
        if (!method_exists($event, 'getSingleValueExtendedProperties')) {
            return false;
        }

        $properties = $event->getSingleValueExtendedProperties();

        if (!is_iterable($properties)) {
            return false;
        }

        foreach ($properties as $property) {
            $id = null;

            if (is_array($property)) {
                $id = isset($property['id']) ? $property['id'] : null;
            } elseif (is_object($property) && method_exists($property, 'getId')) {
                $id = $property->getId();
            }

            // Graph echoes the id back in the shape it was requested in, but not always in the same case.
            if (is_string($id) && strcasecmp($id, AbstractOutlookCalendarService::AMELIA_APPOINTMENT_PROPERTY_ID) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apple has no extended properties, so Amelia's own events are recognised by the UID its insertEvent()
     * generates - which getCalendarEvents() copies onto the free/busy payload read here.
     *
     * @param string $uid
     *
     * @return bool
     */
    private function isAmeliaAppleEventUid($uid): bool
    {
        return strpos($uid, 'ameliaAppointmentEvent_') === 0 || strpos($uid, 'ameliaEventEvent_') === 0;
    }

    /**
     * @param string            $type
     * @param string            $title
     * @param DateTimeInterface $startDt
     * @param DateTimeInterface $endDt
     *
     * @return array<string, mixed>
     */
    private function buildRow($type, $title, DateTimeInterface $startDt, DateTimeInterface $endDt)
    {
        return [
            'title'                   => $title,
            'type'                    => $type,
            'mainColor'               => '#9ca3af',
            'editable'                => false,
            'startEditable'           => false,
            'durationEditable'        => false,
            'startWithoutBuffer'      => $startDt->format('Y-m-d H:i:s'),
            'endWithoutBuffer'        => $endDt->format('Y-m-d H:i:s'),
        ];
    }
}
