<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile;

use AmeliaBooking\Application\Commands\Command;
use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Entity\User\AbstractUser;

/**
 * Class AbstractMobileCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Mobile
 */
abstract class AbstractMobileCommandHandler extends CommandHandler
{
    /**
     * @param Command $command
     *
     * @return AbstractUser|null
     */
    protected function getAuthenticatedProvider(Command $command)
    {
        /** @var UserApplicationService $userAS */
        $userAS = $this->container->get('application.user.service');

        return $userAS->getAuthenticatedUser($command->getToken(), false, 'providerCabinet');
    }

    /**
     * @return CommandResult
     */
    protected function createReauthorizeResult()
    {
        $result = new CommandResult();

        $result->setResult(CommandResult::RESULT_ERROR);
        $result->setMessage('Could not retrieve user');
        $result->setData(['reauthorize' => true]);

        return $result;
    }
}
