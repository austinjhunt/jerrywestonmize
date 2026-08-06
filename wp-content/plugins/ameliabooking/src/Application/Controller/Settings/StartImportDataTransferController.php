<?php

namespace AmeliaBooking\Application\Controller\Settings;

use AmeliaBooking\Application\Commands\Settings\StartImportDataTransferCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class StartImportDataTransferController extends Controller
{
    public $allowedFields = [
        'ameliaNonce',
        'wpAmeliaNonce',
    ];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new StartImportDataTransferCommand($args);
        $this->setCommandFields($command, $request->getParsedBody() ?: []);

        $uploadedFiles = $request->getUploadedFiles();
        $command->setField('file', $uploadedFiles['file'] ?? null);

        return $command;
    }
}
