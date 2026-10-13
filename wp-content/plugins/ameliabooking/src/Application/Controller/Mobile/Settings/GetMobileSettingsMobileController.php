<?php

namespace AmeliaBooking\Application\Controller\Mobile\Settings;

use AmeliaBooking\Application\Commands\Mobile\Settings\GetMobileSettingsCommand;
use AmeliaBooking\Application\Controller\Mobile\MobileV1Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /mobile/v1/settings
 *
 * Returns payment-display settings (currency) for the Amelia Staff mobile app.
 * Requires: Authorization: Bearer <provider-jwt>
 */
class GetMobileSettingsMobileController extends MobileV1Controller
{
    /**
     * @param Request $request
     * @param         $args
     *
     * @return GetMobileSettingsCommand
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new GetMobileSettingsCommand($args);

        $command->setToken($request);
        $this->forceCabinetContext($command);

        return $command;
    }
}
