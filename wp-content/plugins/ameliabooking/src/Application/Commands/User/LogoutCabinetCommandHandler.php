<?php

namespace AmeliaBooking\Application\Commands\User;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Infrastructure\WP\UserService\UserService;

/**
 * Class LogoutCabinetCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\User
 */
class LogoutCabinetCommandHandler extends CommandHandler
{
    /**
     * @param LogoutCabinetCommand $command
     *
     * @return CommandResult
     */
    public function handle(LogoutCabinetCommand $command)
    {
        $result = new CommandResult();

        /** @var UserService $userService */
        $userService = $this->container->get('users.service');

        do_action('amelia_before_logout_user');

        /** @var AbstractUser $user */
        $user = $this->container->get('logged.in.user');

        // If user is WP user connected to Amelia user, admin or manager, logout WP user
        if (
            $user &&
            (
                $user->getId() !== null ||
                $user->getType() === AbstractUser::USER_ROLE_ADMIN ||
                $user->getType() === AbstractUser::USER_ROLE_MANAGER
            )
        ) {
            $userService->logoutWordPressUser();
        }

        $userService->logoutAmeliaUser(false);

        do_action('amelia_after_logout_user');

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData([]);

        return $result;
    }
}
