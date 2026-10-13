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
        // Identifies a reservation this request supersedes, so returning to the payment step reuses the
        // hold it already has instead of releasing it and taking a new one.
        'cacheName',
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
