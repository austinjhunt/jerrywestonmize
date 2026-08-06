<?php

namespace AmeliaBooking\Application\Commands\Settings;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Services\DataTransfer\DataTransferService;

class ExportDataTransferCommandHandler extends CommandHandler
{
    public function handle(ExportDataTransferCommand $command): CommandResult
    {
        if (!$command->getPermissionService()->currentUserCanWrite(Entities::SETTINGS)) {
            throw new AccessDeniedException('You are not allowed to export Amelia settings and data.');
        }

        /** @var DataTransferService $dataTransferService */
        $dataTransferService = $this->container->get('infrastructure.dataTransfer.service');

        $result = new CommandResult();
        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully exported Amelia data.');
        $result->setData($dataTransferService->exportArchive());

        return $result;
    }
}
