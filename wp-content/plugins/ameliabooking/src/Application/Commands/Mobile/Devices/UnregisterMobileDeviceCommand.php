<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile\Devices;

use AmeliaBooking\Application\Commands\Command;

/**
 * Class UnregisterMobileDeviceCommand
 *
 * @package AmeliaBooking\Application\Commands\Mobile\Devices
 */
class UnregisterMobileDeviceCommand extends Command
{
    public function __construct($args)
    {
        parent::__construct($args);
    }
}
