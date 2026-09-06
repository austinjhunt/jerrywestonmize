<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Stash;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Stash\StashApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class UpdateStashCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stash
 */
class UpdateStashCommandHandler extends CommandHandler
{
    /**
     * @param UpdateStashCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     */
    public function handle(UpdateStashCommand $command)
    {
        /** @var AbstractUser $user */
        $user = $command->authorizeProviderWritePermission((int)$command->getArg('id'));

        $result = new CommandResult();

        /** @var StashApplicationService $stashApplicationService */
        $stashApplicationService = $this->container->get('application.stash.service');

        $stashApplicationService->setStash();

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully updated stash');
        $result->setData(true);

        return $result;
    }
}
