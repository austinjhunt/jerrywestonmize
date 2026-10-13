<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile\NotificationPreferences;

use AmeliaBooking\Application\Commands\Command;

/**
 * Class GetMobileNotificationPreferencesCommand
 *
 * @package AmeliaBooking\Application\Commands\Mobile\NotificationPreferences
 */
class GetMobileNotificationPreferencesCommand extends Command
{
    public function __construct($args)
    {
        parent::__construct($args);
    }
}
