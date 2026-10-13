<?php

namespace AmeliaBooking\Application\Commands\Google;

use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;

/**
 * Class DisconnectFromGoogleAccountCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Google
 */
class DisconnectFromGoogleAccountCommandHandler extends CommandHandler
{
    /**
     * @param DisconnectFromGoogleAccountCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    /**
     * @param AbstractGoogleCalendarService $googleCalendarService
     * @param GoogleCalendarRepository      $googleCalendarRepository
     * @param int                           $calendarRowId
     *
     * @return void
     */
    private function releaseCalendarWatch($googleCalendarService, $googleCalendarRepository, $calendarRowId): void
    {
        try {
            $googleCalendarService->disconnect($googleCalendarRepository->getById($calendarRowId));
        } catch (\Throwable $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                'GoogleCalendar: releasing watch on disconnect failed',
                [
                    'exception'     => $e,
                    'calendarRowId' => $calendarRowId,
                ]
            );
        }
    }

    public function handle(DisconnectFromGoogleAccountCommand $command): CommandResult
    {
        /** @var AbstractUser $user */
        $user = $command->authorizeProviderWritePermission((int)$command->getArg('id'));

        $result = new CommandResult();

        /** @var GoogleCalendarRepository $googleCalendarRepository */
        $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

        $accountId = $command->getField('accountId');

        /** @var AbstractGoogleCalendarService $googleCalendarService */
        $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');

        if ($accountId) {
            try {
                // Release the watch before the row goes, so the channel is stopped remotely and no
                // registry row is left owning a connection that no longer exists.
                $this->releaseCalendarWatch($googleCalendarService, $googleCalendarRepository, $accountId);

                if ($googleCalendarRepository->delete($accountId)) {
                    do_action('amelia_after_google_calendar_deleted', ['accountId' => $accountId], $command->getArg('id'));

                    $result->setResult(CommandResult::RESULT_SUCCESS);
                    $result->setMessage('Google calendar account successfully disconnected.');
                } else {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Unable to delete google calendar account.');
                }
            } catch (\Exception $e) {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Unable to delete google calendar account.');
            }

            return $result;
        }

        $providerId = $command->getArg('id');

        try {
            /** @var GoogleCalendarRepository $googleCalendarRepository */
            $googleCalendars = $googleCalendarRepository->getByEntityId($providerId, 'userId');

            foreach ($googleCalendars->getItems() as $googleCalendar) {
                do_action('amelia_before_google_calendar_deleted', $googleCalendar->toArray(), $providerId);

                try {
                    $googleCalendarService->disconnect($googleCalendar);
                } catch (\Throwable $e) {
                    $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                        'GoogleCalendar: releasing watch on disconnect failed',
                        [
                            'exception'  => $e,
                            'providerId' => $providerId,
                        ]
                    );
                }
            }

            if ($googleCalendarRepository->deleteByEntityId($providerId, 'userId')) {
                foreach ($googleCalendars->getItems() as $googleCalendar) {
                    do_action('amelia_after_google_calendar_deleted', $googleCalendar, $providerId);
                }

                $result->setResult(CommandResult::RESULT_SUCCESS);
                $result->setMessage('Google calendar successfully disconnected.');
            } else {
                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Unable to delete google calendar accounts.');
            }
        } catch (\Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Unable to delete google calendar accounts.');
        }

        return $result;
    }
}
