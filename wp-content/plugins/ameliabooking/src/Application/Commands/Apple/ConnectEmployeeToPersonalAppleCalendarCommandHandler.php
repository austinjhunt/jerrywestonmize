<?php

namespace AmeliaBooking\Application\Commands\Apple;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Apple\AppleCalendarFactory;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Services\Apple\AbstractAppleCalendarService;

class ConnectEmployeeToPersonalAppleCalendarCommandHandler extends CommandHandler
{
    /**
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     */
    public function handle(ConnectEmployeeToPersonalAppleCalendarCommand $command)
    {
        /** @var AbstractUser $user */
        $user = $command->authorizeProviderWritePermission((int)$command->getArg('id'));

        $result = new CommandResult();

        $employeeAppleCalendar = $command->getField('employeeAppleCalendar');

        /** @var AbstractAppleCalendarService $appleCalendarService */
        $appleCalendarService = $this->container->get('infrastructure.apple.calendar.service');

        $appleId       = $employeeAppleCalendar['iCloudId'];
        $applePassword = $employeeAppleCalendar['appSpecificPassword'];

        $credentials = $appleCalendarService->handleAppleCredentials($appleId, $applePassword);

        if (!$credentials) {
            $result->setDataInResponse(true);
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Make sure you are using the correct iCloud email address and app-specific password.');

            return $result;
        }

        $providerRepository = $this->container->get('domain.users.providers.repository');
        /** @var Provider $provider */
        $provider = $providerRepository->getById($command->getArg('id'));

        $provider->setEmployeeAppleCalendar(AppleCalendarFactory::create($employeeAppleCalendar));
        do_action('amelia_before_apple_calendar_employee_connected', $provider->toArray(), $command->getArg('id'));

        $providerRepository->updateFieldById($provider->getId()->getValue(), json_encode($employeeAppleCalendar, true), 'employeeAppleCalendar');
        $providerRepository->updateFieldById($provider->getId()->getValue(), null, 'appleCalendarId');
        do_action('amelia_after_apple_calendar_employee_connected', $provider->toArray(), $command->getArg('id'));

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Apple Calendar successfully connected.');
        $result->setData(
            [
                'isEmployeeConnectedToPersonalAppleCalendar' => true
            ]
        );

        return $result;
    }
}
