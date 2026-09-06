<?php

namespace AmeliaBooking\Application\Controller\Stripe;

use AmeliaBooking\Application\Commands\Stripe\CancelStripePaymentIntentCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Class CancelStripePaymentIntentController
 *
 * @package AmeliaBooking\Application\Controller\Stripe
 */
class CancelStripePaymentIntentController extends Controller
{
    protected $allowedFields = [
        'name',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return CancelStripePaymentIntentCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new CancelStripePaymentIntentCommand($args);

        $this->setCommandFields($command, $request->getParsedBody());

        return $command;
    }
}
