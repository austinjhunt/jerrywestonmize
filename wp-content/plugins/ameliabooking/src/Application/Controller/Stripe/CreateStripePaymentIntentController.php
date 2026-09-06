<?php

namespace AmeliaBooking\Application\Controller\Stripe;

use AmeliaBooking\Application\Commands\Stripe\CreateStripePaymentIntentCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Class CreateStripePaymentIntentController
 *
 * @package AmeliaBooking\Application\Controller\Stripe
 */
class CreateStripePaymentIntentController extends Controller
{
    protected $allowedFields = [
        'type',
        'bookings',
        'bookingStart',
        'notifyParticipants',
        'eventId',
        'serviceId',
        'providerId',
        'locationId',
        'couponCode',
        'payment',
        'recurring',
        'isCart',
        'recaptcha',
        'packageId',
        'package',
        'packageRules',
        'utcOffset',
        'locale',
        'timeZone',
        'deposit',
        'componentProps',
        'returnUrl',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return CreateStripePaymentIntentCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new CreateStripePaymentIntentCommand($args);

        $this->setCommandFields($command, $request->getParsedBody());

        return $command;
    }
}
