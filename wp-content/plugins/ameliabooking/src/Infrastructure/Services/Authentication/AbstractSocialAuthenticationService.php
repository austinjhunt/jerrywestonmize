<?php

namespace AmeliaBooking\Infrastructure\Services\Authentication;

abstract class AbstractSocialAuthenticationService
{
    /**
     * Exchange Google Authorization Code for Access Token.
     */
    abstract public function getGoogleUserProfile($accessToken);

    /**
     * Exchange Facebook Authorization Code for Access Token.
     */
    abstract public function getFacebookUserProfile($code, $redirectUrl);
}
