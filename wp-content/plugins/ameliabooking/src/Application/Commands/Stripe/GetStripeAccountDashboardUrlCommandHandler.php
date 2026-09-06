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
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use Exception;

/**
 * Class GetStripeAccountDashboardUrlCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class GetStripeAccountDashboardUrlCommandHandler extends CommandHandler
{
    /**
     * @param GetStripeAccountDashboardUrlCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     * @throws Exception
     */
    public function handle(GetStripeAccountDashboardUrlCommand $command)
    {
        /** @var AbstractUser $user */
        $user = $command->authorizeProviderReadPermission((int)$command->getArg('id'));

        $result = new CommandResult();

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var Provider $provider */
        $provider = $providerRepository->getById((int)$command->getArg('id'));

        $stripeAccountUrl = $provider->getStripeConnect() && $provider->getStripeConnect()->getId()
            ? $stripeService->getExpressAccountLink($provider->getStripeConnect()->getId()->getValue())
            : null;

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved stripe login url.');
        $result->setData(
            [
                'url' => $stripeAccountUrl,
            ]
        );

        return $result;
    }
}
