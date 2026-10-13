<?php

namespace AmeliaBooking\Application\Commands\Outlook;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarService;
use AmeliaBooking\Infrastructure\WP\Http\RequestFinisher;
use Interop\Container\Exception\ContainerException;

/**
 * Class WatchOutlookCalendarNotificationCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Outlook
 */
class WatchOutlookCalendarNotificationCommandHandler extends CommandHandler
{
    /**
     * @param WatchOutlookCalendarNotificationCommand $command
     *
     * @return CommandResult
     * @throws ContainerException
     */
    public function handle(WatchOutlookCalendarNotificationCommand $command)
    {
        $result = new CommandResult();

        $validationToken = $command->getField('validationToken');
        if ($validationToken !== null && $validationToken !== '') {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setHtml((string)$validationToken);

            return $result;
        }

        $value = $command->getField('value');
        if (!is_array($value) || $value === []) {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setData([]);

            return $result;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        if (!$settingsService->isFeatureEnabled('outlookCalendar')) {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setData([]);

            return $result;
        }

        $grouped = [];
        foreach ($value as $notification) {
            if (!is_array($notification)) {
                continue;
            }

            $subscriptionId = isset($notification['subscriptionId'])
                ? (string)$notification['subscriptionId']
                : '';

            if ($subscriptionId === '') {
                continue;
            }

            $grouped[$subscriptionId][] = $notification;
        }

        // Graph wants an acknowledgement within seconds and drops the subscription after repeated failures, so the
        // notifications are processed only after the response has been sent.
        if ($grouped !== []) {
            RequestFinisher::deferUntilAfterResponse(
                function () use ($grouped) {
                    // Graph has already been told the notification was accepted, so a throw here cannot
                    // become a retry - it would only surface as a fatal in WordPress shutdown, taking
                    // every grouped notification with it. Anything that escapes per subscription
                    // handling is recorded instead.
                    try {
                        $this->processNotifications($grouped);
                    } catch (\Throwable $e) {
                        $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                            'OutlookCalendar: deferred notification processing failed',
                            ['exception' => $e]
                        );
                    }
                }
            );
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData([]);

        return $result;
    }

    /**
     * @param array $grouped notifications keyed by subscription id
     *
     * @return void
     */
    private function processNotifications(array $grouped)
    {
        /** @var AbstractOutlookCalendarService $outlookCalendarService */
        $outlookCalendarService = $this->container->get('infrastructure.outlook.calendar.service');

        foreach ($grouped as $subscriptionId => $notifications) {
            $clientState = isset($notifications[0]['clientState']) ? $notifications[0]['clientState'] : null;

            try {
                $outlookCalendarService->processGraphCalendarNotifications(
                    $subscriptionId,
                    $clientState,
                    $notifications
                );
            } catch (\Throwable $e) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                    'OutlookCalendar: notification processing failed',
                    [
                        'exception'      => $e,
                        'subscriptionId' => $subscriptionId,
                    ]
                );
            }
        }
    }
}
