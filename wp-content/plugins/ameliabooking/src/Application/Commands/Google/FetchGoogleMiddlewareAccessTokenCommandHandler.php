<?php

namespace AmeliaBooking\Application\Commands\Google;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Google\GoogleCalendar;
use AmeliaBooking\Domain\Factory\Google\GoogleCalendarFactory;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarMiddlewareService;
use Throwable;

class FetchGoogleMiddlewareAccessTokenCommandHandler extends CommandHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(FetchGoogleMiddlewareAccessTokenCommand $command): CommandResult
    {
        $result = new CommandResult();

        $accessToken = $this->normalizeAccessToken($command->getField('params')['access_token'] ?? null);

        $providerId = $command->getField('params')['providerId'] ?? null;

        $returnUrl = $command->getField('params')['returnUrl'] ?? null;

        $isBackend = $command->getField('params')['isBackend'] ?? null;

        if (!$accessToken) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Access token is required');
            return $result;
        }

        if ($providerId) {
            /** @var ProviderRepository $providerRepository */
            $providerRepository = $this->container->get('domain.users.providers.repository');

            /** @var GoogleCalendarRepository $googleCalendarRepository */
            $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

            /** @var AbstractGoogleCalendarMiddlewareService $googleCalendarMiddlewareService */
            $googleCalendarMiddlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');

            $hasExistingCalendar = false;
            try {
                $googleCalendarRepository->getByProviderId($providerId);
                $hasExistingCalendar = true;
            } catch (\Exception $e) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->info(
                    'GoogleCalendar: No existing calendar found, this is the first account',
                    [
                        'exception'  => $e,
                        'providerId' => $providerId,
                    ]
                );
            }

            $primaryCalendarId = null;
            if (!$hasExistingCalendar) {
                try {
                    $calendarList = $googleCalendarMiddlewareService->getCalendarList(['token' => $accessToken]);
                    foreach ($calendarList as $calendar) {
                        if (!empty($calendar['primary'])) {
                            $primaryCalendarId = $calendar['id'];
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->warning(
                        'GoogleCalendar: Unable to fetch primary calendar',
                        [
                            'exception'  => $e,
                            'providerId' => $providerId,
                        ]
                    );
                }
            }

            $googleCalendar = GoogleCalendarFactory::create([
                'token' => $accessToken,
                'calendarId' => $primaryCalendarId
            ]);

            $googleCalendarRepository->beginTransaction();

            $additionalSettings = null;
            if (!$hasExistingCalendar) {
                /** @var SettingsService $settingsService */
                $settingsService = $this->container->get('domain.settings.service');
                $globalGoogleSettings = $settingsService->getCategorySettings('googleCalendar');

                $additionalSettings = [
                    'insertPendingAppointments' => $globalGoogleSettings['insertPendingAppointments'] ?? false,
                    'includeBufferTime'         => $globalGoogleSettings['includeBufferTimeGoogleCalendar'] ?? false,
                    'title'                     => $globalGoogleSettings['title'] ?? null,
                    'description'               => $globalGoogleSettings['description'] ?? null,
                ];
            } else {
                $existingAccount = $providerRepository->getGoogleCalendarAccounts($providerId)[0] ?? null;

                if ($existingAccount) {
                    $additionalSettings = [
                        'insertPendingAppointments' => $existingAccount['insertPendingAppointments'] ?? false,
                        'includeBufferTime'         => $existingAccount['includeBufferTime']         ?? false,
                        'title'                     => $existingAccount['title']                     ?? null,
                        'description'               => $existingAccount['description']               ?? null,
                    ];
                }
            }

            $calendarId = $googleCalendarRepository->add($googleCalendar, $providerId, $additionalSettings);

            if (!$calendarId) {
                $googleCalendarRepository->rollback();
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Failed to add Google Calendar');
                return $result;
            }

            $googleCalendarRepository->commit();

            do_action('amelia_after_google_calendar_added', $googleCalendar ? $googleCalendar->toArray() : null, $providerId);

            if (!$hasExistingCalendar) {
                $providerRepository->updateFieldById($providerId, null, 'googleCalendarId');
            }

            /** @var SettingsService $settingsService */
            $settingsService = $this->container->get('domain.settings.service');

            if ($settingsService->isFeatureEnabled('googleCalendar')) {
                /** @var AbstractGoogleCalendarService $googleCalendarService */
                $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');

                try {
                    /** @var GoogleCalendar $savedCalendar */
                    $savedCalendar = $googleCalendarRepository->getById($calendarId);

                    $googleCalendarService->manageChannel($savedCalendar);
                } catch (Throwable $e) {
                    $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                        'GoogleCalendar: manageChannel after OAuth (middleware) failed',
                        [
                            'exception'  => $e,
                            'providerId' => $providerId,
                            'calendarId' => $calendarId,
                        ]
                    );
                }
            }

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully fetched access token');

            if ($returnUrl) {
                $result->setUrl($returnUrl);
            } else {
                $result->setUrl(
                    rtrim(AMELIA_SITE_URL, '/')
                    . '/wp-admin/admin.php?page=wpamelia-employees#/manage/'
                    . $providerId
                    . '/integrations/google-calendar'
                );
            }

            return $result;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var AbstractGoogleCalendarMiddlewareService  $googleCalendarMiddlewareService */
        $googleCalendarMiddlewareService = $this->container->get('infrastructure.google.calendar.middleware.service');

        $googleSettings = $settingsService->getCategorySettings('googleCalendar');
        $googleAccountData = $googleCalendarMiddlewareService->getUserInfo($accessToken);
        $googleSettings['googleAccountData'] = [
            'name'    => $googleAccountData['name'],
            'email'   => $googleAccountData['email'],
            'picture' => $googleAccountData['picture']
        ];
        $googleSettings['accessToken'] = $accessToken;
        $settingsService->setCategorySettings('googleCalendar', $googleSettings);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully fetched access token');
        $result->setData($googleAccountData);

        $result->setUrl(
            AMELIA_SITE_URL . '/wp-admin/admin.php?page=wpamelia-features-integrations#/integrations/google/general'
        );

        return $result;
    }

    /**
     * Middleware can return the token JSON as a slashed query-string value.
     *
     * @param string|null $accessToken
     *
     * @return string|null
     */
    private function normalizeAccessToken(?string $accessToken): ?string
    {
        if (!$accessToken) {
            return $accessToken;
        }

        $decoded = json_decode($accessToken, true);

        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes($accessToken), true);
        }

        $encoded = is_array($decoded) ? json_encode($decoded) : false;

        return $encoded ?: $accessToken;
    }
}
