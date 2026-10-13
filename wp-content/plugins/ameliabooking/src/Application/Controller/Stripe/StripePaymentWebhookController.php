<?php

namespace AmeliaBooking\Application\Controller\Stripe;

use AmeliaBooking\Application\Commands\Stripe\StripePaymentWebhookCommand;
use AmeliaBooking\Application\Controller\Controller;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Class StripePaymentWebhookController
 *
 * @package AmeliaBooking\Application\Controller\Stripe
 */
class StripePaymentWebhookController extends Controller
{
    protected $allowedFields = [
        'payload',
        'signature',
    ];

    /**
     * @param Request $request
     * @param         $args
     *
     * @return StripePaymentWebhookCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args): StripePaymentWebhookCommand
    {
        $command = new StripePaymentWebhookCommand($args);

        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $payload = $body->getContents();

        if ($payload === '') {
            $payload = file_get_contents('php://input') ?: '';
        }

        $this->setCommandFields(
            $command,
            [
                'payload'   => $payload,
                'signature' => $request->getHeaderLine('Stripe-Signature'),
            ]
        );

        return $command;
    }
}
