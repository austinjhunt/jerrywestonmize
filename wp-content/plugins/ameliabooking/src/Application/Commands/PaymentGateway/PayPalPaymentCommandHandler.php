<?php

namespace AmeliaBooking\Application\Commands\PaymentGateway;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Services\Payment\PaymentServiceInterface;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;

/**
 * Class PayPalPaymentCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\PaymentGateway
 */
class PayPalPaymentCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'bookings',
        'payment'
    ];

    /**
     * @param PayPalPaymentCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handle(PayPalPaymentCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $requestData = $this->getAppointmentData($command->getFields(), [PaymentType::PAY_PAL]);

        $type = $command->getField('type') ?: Entities::APPOINTMENT;

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);
        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        $reservation = $reservationService->getNew(true, true, true);

        $reservationService->processBooking(
            $result,
            $requestData,
            $reservation,
            false
        );

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            return $result;
        }

        $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings(
            $reservation,
            PaymentType::PAY_PAL
        );

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            return $result;
        }

        $paymentAmount = $reservationService->getReservationPaymentAmount($reservation);

        if (!$paymentAmount) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'paymentSuccessful' => false,
                    'onSitePayment' => true
                ]
            );

            return $result;
        }

        /** @var PaymentServiceInterface $paymentService */
        $paymentService = $this->container->get('infrastructure.payment.payPal.service');

        $paymentAmount = apply_filters('amelia_before_paypal_execute_filter', $paymentAmount, $reservation->getReservation()->toArray());

        do_action('amelia_before_paypal_execute', $paymentAmount, $reservation->getReservation()->toArray());

        $transfers = [];

        $response = $paymentService->execute(
            [
                'returnUrl'   => AMELIA_ACTION_URL . '/payment/payPal/callback&status=true',
                'cancelUrl'   => AMELIA_ACTION_URL . '/payment/payPal/callback&status=false',
                'amount'      => $paymentAmount,
                'description' => $additionalInformation['description']
            ],
            $transfers
        );

        if (!$response->isSuccessful()) {
            $data = $response->getData();

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => !empty($data['details'][0]['issue']) ?
                        $data['details'][0]['issue'] : $response->getMessage(),
                    'paymentSuccessful' => false
                ]
            );

            return $result;
        }

        $response = apply_filters('amelia_after_paypal_execute_filter', $response, $reservation->getReservation()->toArray());

        do_action('amelia_after_paypal_execute', $response, $reservation->getReservation()->toArray());

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData(
            [
                'paymentID'            => $response->getData()['id'] ?? '',
                'transactionReference' => $response->getTransactionReference(),
            ]
        );

        return $result;
    }
}
