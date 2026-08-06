<?php

namespace AmeliaBooking\Application\Commands\User\Customer;

use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Domain\Repository\User\UserRepositoryInterface;
use AmeliaBooking\Infrastructure\Repository\User\UserRepository;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class GetCustomerCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\User\Customer
 */
class GetCustomerCommandHandler extends CommandHandler
{
    /**
     * @param GetCustomerCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(GetCustomerCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        /** @var UserApplicationService $userAS */
        $userAS = $this->getContainer()->get('application.user.service');

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var UserRepository $userRepository */
        $userRepository = $this->container->get('domain.users.repository');

        /** @var AbstractUser|null $currentUser */
        $currentUser = null;

        if (!$command->getPermissionService()->currentUserCanRead(Entities::CUSTOMERS)) {
            if ($command->getToken()) {
                $currentUser = $userAS->getAuthenticatedUser($command->getToken(), false, 'providerCabinet');

                if ($currentUser === null) {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Could not retrieve user');
                    $result->setData(
                        [
                            'reauthorize' => true
                        ]
                    );

                    return $result;
                }
            } else {
                throw new AccessDeniedException('You are not allowed to read user');
            }
        } else {
            $currentUser = $this->container->get('logged.in.user');
        }

        $rolesSettings = $settingsService->getCategorySettings('roles');

        if (
            $currentUser !== null &&
            $currentUser->getType() === Entities::PROVIDER &&
            empty($rolesSettings['allowReadAllCustomers'])
        ) {
            /** @var Collection $providerCustomers */
            $providerCustomers = $userRepository->getProviderAllowedCustomers(
                $currentUser->getId()->getValue()
            );

            $allowedCustomersIds = $providerCustomers->keys();

            if (!in_array($command->getField('id'), $allowedCustomersIds)) {
                throw new AccessDeniedException('You are not allowed to read user');
            }
        }

        /** @var UserRepositoryInterface $userRepository */
        $userRepository = $this->getContainer()->get('domain.users.repository');

        /** @var AbstractUser $user */
        $user = $userRepository->getById((int)$command->getField('id'));

        $userArray = $user->toArray();

        $userArray = apply_filters('amelia_get_customer_filter', $userArray);

        do_action('amelia_get_customer', $userArray);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved user');
        $result->setData(
            [
                Entities::USER => $userArray
            ]
        );

        return $result;
    }
}
