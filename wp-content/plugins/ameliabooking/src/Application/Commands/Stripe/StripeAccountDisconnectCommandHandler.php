<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;

/**
 * Class StripeAccountDisconnectCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class StripeAccountDisconnectCommandHandler extends CommandHandler
{
    /**
     * @param StripeAccountDisconnectCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     */
    public function handle(StripeAccountDisconnectCommand $command)
    {
        /** @var AbstractUser $user */
        $user = $command->authorizeProviderWritePermission((int)$command->getArg('id'));

        $result = new CommandResult();

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var Provider $provider */
        $provider = $providerRepository->getById((int)$command->getArg('id'));

        $providerRepository->updateFieldById(
            $provider->getId()->getValue(),
            null,
            'stripeConnect'
        );

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully updated user');
        $result->setData([]);

        return $result;
    }
}
