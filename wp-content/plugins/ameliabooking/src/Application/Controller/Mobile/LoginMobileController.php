<?php

namespace AmeliaBooking\Application\Controller\Mobile;

use AmeliaBooking\Application\Commands\User\LoginMobileCommand;
use AmeliaBooking\Application\Controller\User\LoginCabinetController;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Unauthenticated staff-app login (POST /mobile/v1/authenticate).
 *
 * Extends LoginCabinetController for the same success cookies / JWT body, but:
 *  - Does NOT extend MobileV1Controller (no Bearer — this is how the app gets one)
 *  - Ignores client cabinetType and forces provider
 *  - Does not accept recaptcha / token / checkIfWpUser from the body
 *
 * ReCAPTCHA is skipped because LoginMobileCommand::skipsRecaptcha() is true.
 * That is a product split from the web Employee Panel, not proof of the IPA/APK.
 */
class LoginMobileController extends LoginCabinetController
{
    /**
     * @var array
     */
    protected $allowedFields = [
        'email',
        'password',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return LoginMobileCommand
     */
    protected function instantiateCommand(Request $request, $args): LoginMobileCommand
    {
        $command = new LoginMobileCommand($args);

        $requestBody = $request->getParsedBody();
        $this->setCommandFields($command, is_array($requestBody) ? $requestBody : []);
        $command->setField('cabinetType', AbstractUser::USER_ROLE_PROVIDER);

        return $command;
    }
}
