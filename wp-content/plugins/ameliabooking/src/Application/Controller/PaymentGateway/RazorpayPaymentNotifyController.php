<?php

namespace AmeliaBooking\Application\Controller\PaymentGateway;

use AmeliaBooking\Application\Commands\PaymentGateway\RazorpayPaymentNotifyCommand;
use AmeliaBooking\Application\Controller\Controller;
use RuntimeException;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class RazorpayPaymentNotifyController
 *
 * @package AmeliaBooking\Application\Controller\PaymentGateway
 */
class RazorpayPaymentNotifyController extends Controller
{
    /**
     * Fields for Razorpay payment notify that can be received from API
     *
     * @var array
     */
    protected $allowedFields = [
        'name',
        'paymentId',
        'signature',
        'orderId',
    ];

    /**
     * Instantiates the Razorpay Payment Notify command to hand it over to the Command Handler
     *
     * @param Request $request
     * @param         $args
     *
     * @return RazorpayPaymentNotifyCommand
     * @throws RuntimeException
     */
    protected function instantiateCommand(Request $request, $args)
    {
        $command = new RazorpayPaymentNotifyCommand($args);

        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            $this->setCommandFields($command, $parsedBody);
        }

        $this->setCommandFields($command, $request->getQueryParams());

        return $command;
    }
}
