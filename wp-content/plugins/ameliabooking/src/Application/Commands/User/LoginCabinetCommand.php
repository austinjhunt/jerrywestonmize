<?php

namespace AmeliaBooking\Application\Commands\User;

use AmeliaBooking\Application\Commands\Command;

/**
 * Class LoginCabinetCommand
 *
 * @package AmeliaBooking\Application\Commands\User
 */
class LoginCabinetCommand extends Command
{
    /**
     * @return bool
     */
    public function skipsRecaptcha(): bool
    {
        return false;
    }
}
