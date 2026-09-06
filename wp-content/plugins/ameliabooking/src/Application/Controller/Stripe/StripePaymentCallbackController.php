<?php

namespace AmeliaBooking\Application\Controller\Stripe;

use AmeliaBooking\Application\Commands\Stripe\StripePaymentCallbackCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Class StripePaymentCallbackController
 *
 * @package AmeliaBooking\Application\Controller\Stripe
 */
class StripePaymentCallbackController extends Controller
{
    protected $allowedFields = [
        'name',
        'returnUrl',
        'payment_intent',
        'payment_intent_client_secret',
        'redirect_status',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return StripePaymentCallbackCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new StripePaymentCallbackCommand($args);

        $this->setCommandFields($command, $request->getQueryParams());

        return $command;
    }
}
