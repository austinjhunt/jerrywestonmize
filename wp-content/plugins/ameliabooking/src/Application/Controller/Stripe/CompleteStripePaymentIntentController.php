<?php

namespace AmeliaBooking\Application\Controller\Stripe;

use AmeliaBooking\Application\Commands\Stripe\CompleteStripePaymentIntentCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Class CompleteStripePaymentIntentController
 *
 * @package AmeliaBooking\Application\Controller\Stripe
 */
class CompleteStripePaymentIntentController extends Controller
{
    protected $allowedFields = [
        'name',
        'paymentIntentId',
        'recaptcha',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return CompleteStripePaymentIntentCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new CompleteStripePaymentIntentCommand($args);

        $this->setCommandFields($command, $request->getParsedBody());

        return $command;
    }
}
