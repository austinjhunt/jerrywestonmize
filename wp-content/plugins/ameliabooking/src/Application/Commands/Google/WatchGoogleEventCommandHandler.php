<?php

namespace AmeliaBooking\Application\Commands\Google;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Calendar\CalendarSync;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Repository\Calendar\CalendarSyncRepository;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Infrastructure\Services\Google\GoogleCalendarService;
use AmeliaBooking\Infrastructure\WP\Http\RequestFinisher;

/**
 * Class WatchGoogleEventCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Google
 */
class WatchGoogleEventCommandHandler extends CommandHandler
{
    /**
     * @param WatchGoogleEventCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function handle(WatchGoogleEventCommand $command): CommandResult
    {
        /** @var AbstractGoogleCalendarService $googleCalendarService */
        $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');

        $resourceState = $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'] ?? null;

        $channelId = $_SERVER['HTTP_X_GOOG_CHANNEL_ID'] ?? null;

        $resourceId = $_SERVER['HTTP_X_GOOG_RESOURCE_ID'] ?? null;

        $channelToken = isset($_SERVER['HTTP_X_GOOG_CHANNEL_TOKEN'])
            ? (string)$_SERVER['HTTP_X_GOOG_CHANNEL_TOKEN']
            : '';

        $result = new CommandResult();

        if ($resourceState === 'sync') {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        if (!$resourceId || !$channelId) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->warning(
                'GoogleCalendar: push notification rejected — missing Goog-Channel-Id or Goog-Resource-Id',
                [
                    'channelId'     => $channelId,
                    'resourceId'    => $resourceId,
                    'resourceState' => $resourceState,
                ]
            );

            $result->setResult(CommandResult::RESULT_ERROR);

            return $result;
        }

        if (!GoogleCalendarService::isGooglePushChannelTokenValid($channelId, $channelToken)) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->warning(
                'GoogleCalendar: push notification rejected — invalid or missing Goog-Channel-Token',
                [
                    'channelId'  => $channelId,
                    'resourceId' => $resourceId,
                ]
            );
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setDataInResponse(false);

            return $result;
        }

        /** @var CalendarSyncRepository $calendarSyncRepository */
        $calendarSyncRepository = $this->container->get('domain.calendar.sync.repository');

        // The channel belongs to a watched calendar, which may serve several employees, so the push is
        // resolved to that calendar rather than to one employee's connection.
        $calendarSync = $calendarSyncRepository->getBySubscriptionId(CalendarSync::TYPE_GOOGLE, $channelId);

        if (!$calendarSync || $calendarSync->getResourceId() !== $resourceId) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->warning(
                $calendarSync
                    ? 'GoogleCalendar: push notification rejected — resourceId does not match the stored channel'
                    : 'GoogleCalendar: push notification rejected — no calendar sync row for this channel',
                [
                    'channelId'        => $channelId,
                    'resourceId'       => $resourceId,
                    'storedResourceId' => $calendarSync ? $calendarSync->getResourceId() : null,
                    'syncId'           => $calendarSync && $calendarSync->getId()
                        ? $calendarSync->getId()->getValue()
                        : null,
                ]
            );

            $result->setResult(CommandResult::RESULT_ERROR);

            return $result;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        if (!$settingsService->isFeatureEnabled('googleCalendar')) {
            $result->setResult(CommandResult::RESULT_SUCCESS);

            return $result;
        }

        // Google retries a push that is not acknowledged quickly, and listing the changed events plus applying
        // them is far too slow for that window, so the work runs once the response has been sent.
        RequestFinisher::deferUntilAfterResponse(
            function () use ($googleCalendarService, $calendarSync, $resourceId) {
                try {
                    $googleCalendarService->manageChangedEvents($calendarSync, $resourceId);
                } catch (\Throwable $e) {
                    $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                        'GoogleCalendar: push processing failed',
                        [
                            'exception'  => $e,
                            'resourceId' => $resourceId,
                        ]
                    );
                }
            }
        );

        $result->setResult(CommandResult::RESULT_SUCCESS);

        return $result;
    }
}
