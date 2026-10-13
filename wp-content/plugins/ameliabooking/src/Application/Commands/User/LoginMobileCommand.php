<?php

namespace AmeliaBooking\Application\Commands\User;

class LoginMobileCommand extends LoginCabinetCommand
{
    /**
     * @return bool
     */
    public function skipsRecaptcha(): bool
    {
        return true;
    }
}
