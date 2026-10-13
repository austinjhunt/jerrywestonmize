<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Mobile\NotificationPreferences;

use AmeliaBooking\Application\Commands\Mobile\NotificationPreferences\GetMobileNotificationPreferencesCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /mobile/v1/notification-preferences
 */
class GetMobileNotificationPreferencesMobileController extends MobileV1Controller
{
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetMobileNotificationPreferencesCommand($args);

        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
