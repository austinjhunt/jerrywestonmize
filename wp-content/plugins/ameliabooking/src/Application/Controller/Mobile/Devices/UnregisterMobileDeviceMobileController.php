<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Mobile\Devices;

use AmeliaBooking\Application\Commands\Mobile\Devices\UnregisterMobileDeviceCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DELETE /mobile/v1/devices
 *
 * Unregisters an Expo push token for the authenticated provider.
 * Body: { "token": string }
 */
class UnregisterMobileDeviceMobileController extends MobileV1Controller
{
    public $allowedFields = [
        'token',
    ];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new UnregisterMobileDeviceCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
