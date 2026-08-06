<?php

namespace AmeliaBooking\Application\Commands\Settings;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Services\DataTransfer\DataTransferService;

class StartImportDataTransferCommandHandler extends CommandHandler
{
    protected $mandatoryFields = [
        'file',
    ];

    public function handle(StartImportDataTransferCommand $command): CommandResult
    {
        if (!$command->getPermissionService()->currentUserCanWrite(Entities::SETTINGS)) {
            throw new AccessDeniedException('You are not allowed to import Amelia settings and data.');
        }

        $this->checkMandatoryFields($command);

        /** @var DataTransferService $dataTransferService */
        $dataTransferService = $this->container->get('infrastructure.dataTransfer.service');

        $result = new CommandResult();
        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully initialized the Amelia import.');
        $result->setData([
            'importData' => $dataTransferService->startImportJob($command->getField('file'))
        ]);

        return $result;
    }
}
