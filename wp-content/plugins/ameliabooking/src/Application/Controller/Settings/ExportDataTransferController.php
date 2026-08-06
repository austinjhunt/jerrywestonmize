<?php

namespace AmeliaBooking\Application\Controller\Settings;

use AmeliaBooking\Application\Commands\Settings\ExportDataTransferCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

class ExportDataTransferController extends Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new ExportDataTransferCommand($args);
        $this->setCommandFields($command, $request->getParsedBody() ?: []);

        return $command;
    }
}
