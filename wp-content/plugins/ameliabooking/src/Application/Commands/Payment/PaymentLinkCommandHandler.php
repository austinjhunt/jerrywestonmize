<?php

namespace AmeliaBooking\Application\Commands\Payment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use Interop\Container\Exception\ContainerException;

/**
 * Class PaymentLinkCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Payment
 */
class PaymentLinkCommandHandler extends CommandHandler
{

    /**
     * @param PaymentLinkCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws ContainerException
     */
    public function handle(PaymentLinkCommand $command)
    {
        $result = new CommandResult();

        /** @var PaymentApplicationService $paymentApplicationService */
        $paymentApplicationService = $this->container->get('application.payment.service');

        $data = $command->getField('data');

        if ($data['data']['type'] === 'appointment') {
            $data['data']['bookable'] = $data['data']['service'];
        } else {
            $data['data']['bookable'] = $data['data'][$data['data']['type']];
        }

        $paymentLinks = $paymentApplicationService->createPaymentLink(
            $data['data'],
            0,
            null,
            [$data['paymentMethod'] => true],
            $command->getField('redirectUrl')
        );

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage(
            !empty(array_values($paymentLinks)[0]['link'])
                ? 'Successfully created link' : array_values($paymentLinks)[1]
        );
        $result->setData(
            [
                'paymentLink' => array_values($paymentLinks)[0],
                'error'       => array_values($paymentLinks)[1]
            ]
        );

        return $result;
    }
}
