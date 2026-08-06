<?php

namespace AmeliaBooking\Application\Controller\Settings;

use AmeliaBooking\Application\Commands\Settings\ProcessImportDataTransferCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class ProcessImportDataTransferController extends Controller
{
    public $allowedFields = [
        'ameliaNonce',
        'wpAmeliaNonce',
        'jobId',
    ];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new ProcessImportDataTransferCommand($args);
        $this->setCommandFields($command, $request->getParsedBody() ?: []);

        return $command;
    }
}
