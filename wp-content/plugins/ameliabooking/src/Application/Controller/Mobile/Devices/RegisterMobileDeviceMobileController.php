<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Mobile\Devices;

use AmeliaBooking\Application\Commands\Mobile\Devices\RegisterMobileDeviceCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /mobile/v1/devices
 *
 * Registers or updates an Expo push token for the authenticated provider.
 * Body: { "token": string, "platform": "ios"|"android", "appVersion"?: string }
 */
class RegisterMobileDeviceMobileController extends MobileV1Controller
{
    public $allowedFields = [
        'token',
        'platform',
        'appVersion',
    ];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new RegisterMobileDeviceCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
