<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Payment;

use AmeliaBooking\Application\Commands\Payment\GetPaymentCommand;
use AmeliaBooking\Application\Commands\Payment\GetPaymentDetailsCommand;
use AmeliaBooking\Application\Controller\Controller;
use Slim\Http\Request;

/**
 * Class GetPaymentController
 *
 * @package AmeliaBooking\Application\Controller\Payment
 */
class GetPaymentDetailsController extends Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetPaymentDetailsCommand($args);
        $command->setField('params', $request->getQueryParams());
        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);

        return $command;
    }
}
