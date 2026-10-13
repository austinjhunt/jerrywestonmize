<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Controller\Mobile\NotificationPreferences;

use AmeliaBooking\Application\Commands\Mobile\NotificationPreferences\UpdateMobileNotificationPreferencesCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * PUT /mobile/v1/notification-preferences
 *
 * Body: either
 *   { "preferences": { "new_bookings": true, ... } }
 * or flat category keys:
 *   { "new_bookings": true, "status_changes": false, ... }
 */
class UpdateMobileNotificationPreferencesMobileController extends MobileV1Controller
{
    public $allowedFields = [
        'preferences',
        'new_bookings',
        'status_changes',
        'changes',
        'reminders',
        'reminder_time_before',
    ];

    protected function instantiateCommand(Request $request, $args)
    {
        $command = new UpdateMobileNotificationPreferencesCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, $requestBody);
        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
