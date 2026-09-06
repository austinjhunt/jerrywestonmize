<?php

namespace AmeliaBooking\Application\Commands\Apple;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;

class DisconnectFromAppleCalendarCommandHandler extends CommandHandler
{
    /**
     * @param DisconnectFromAppleCalendarCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function handle(DisconnectFromAppleCalendarCommand $command)
    {
        /** @var AbstractUser $user */
        $user = $command->authorizeProviderWritePermission((int)$command->getArg('id'));

        $result = new CommandResult();

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');
        /** @var Provider $provider */
        $provider = $providerRepository->getById($command->getArg('id'));

        do_action('amelia_before_apple_calendar_deleted', $provider->toArray(), $command->getArg('id'));

        $providerRepository->updateFieldById($provider->getId()->getValue(), null, 'appleCalendarId');
        do_action('amelia_after_apple_calendar_deleted', $provider->toArray(), $command->getArg('id'));

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Apple calendar successfully deleted.');

        return $result;
    }
}
