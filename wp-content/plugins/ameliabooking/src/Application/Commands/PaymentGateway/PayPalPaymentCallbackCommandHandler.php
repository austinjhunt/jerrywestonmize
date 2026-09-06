<?php

namespace AmeliaBooking\Application\Commands\PaymentGateway;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;

/**
 * Class PayPalPaymentCallbackCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\PaymentGateway
 */
class PayPalPaymentCallbackCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'status',
        'token',
        'PayerID',
    ];

    /**
     * @param PayPalPaymentCallbackCommand $command
     *
     * @return CommandResult
     * @throws \AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException
     */
    public function handle(PayPalPaymentCallbackCommand $command)
    {
        $result = new CommandResult();

        try {
            $this->checkMandatoryFields($command);
        } catch (InvalidArgumentException $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'PayPal payment callback processing failed',
                [
                    'exception'  => $e,
                    'hasToken'   => $command->getField('token') !== null,
                    'hasPayerId' => $command->getField('PayerID') !== null,
                ]
            );

            throw $e;
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('');
        $result->setData([]);

        return $result;
    }
}
