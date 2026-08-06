<?php

namespace AmeliaBooking\Application\Commands\Settings;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Infrastructure\Services\DataTransfer\DataTransferService;

class ProcessImportDataTransferCommandHandler extends CommandHandler
{
    protected $mandatoryFields = [
        'jobId',
    ];

    public function handle(ProcessImportDataTransferCommand $command): CommandResult
    {
        if (!$command->getPermissionService()->currentUserCanWrite(Entities::SETTINGS)) {
            throw new AccessDeniedException('You are not allowed to import Amelia settings and data.');
        }

        $this->checkMandatoryFields($command);

        /** @var DataTransferService $dataTransferService */
        $dataTransferService = $this->container->get('infrastructure.dataTransfer.service');

        $result = new CommandResult();
        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully processed the Amelia import step.');
        $result->setData([
            'importData' => $dataTransferService->processImportJob($command->getField('jobId'))
        ]);

        return $result;
    }
}
