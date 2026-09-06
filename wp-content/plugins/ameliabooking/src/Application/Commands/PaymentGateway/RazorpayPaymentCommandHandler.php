<?php

namespace AmeliaBooking\Application\Commands\PaymentGateway;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Application\Services\Reservation\AbstractReservationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Reservation;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Factory\Cache\CacheFactory;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Domain\ValueObjects\String\Token;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Services\Payment\RazorpayService;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;

/**
 * Class RazorpayPaymentCommandHandler
 *
 * Reserves the slot with a pending payment before opening Razorpay checkout
 * (same pattern as Mollie/Barion), so an interrupted browser after capture
 * cannot leave the slot free for a second booking.
 *
 * @package AmeliaBooking\Application\Commands\PaymentGateway
 */
class RazorpayPaymentCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'bookings',
        'payment'
    ];

    /**
     * @param RazorpayPaymentCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handle(RazorpayPaymentCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $requestData = $this->getAppointmentData($command->getFields(), [PaymentType::RAZORPAY]);

        $type = $command->getField('type') ?: Entities::APPOINTMENT;

        /** @var AbstractReservationService $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var RazorpayService $paymentService */
        $paymentService = $this->container->get('infrastructure.payment.razorpay.service');

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        $bookingData = apply_filters('amelia_before_razorpay_redirect_filter', $requestData);

        do_action('amelia_before_razorpay_redirect', $bookingData);

        /** @var Reservation $reservation */
        $reservation = $reservationService->getNew(true, true, true);

        $reservationService->processBooking(
            $result,
            $bookingData,
            $reservation,
            false
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
                    'onSitePayment'     => true
                ]
            );

            return $result;
        }

        $token = new Token();

        /** @var Cache $cache */
        $cache = CacheFactory::create(
            [
                'name' => $token->getValue(),
                'data' => json_encode(
                    [
                        'status'  => null,
                        'request' => $command->getField('componentProps'),
                    ]
                ),
            ]
        );

        $cacheId = $cacheRepository->add($cache);

        $cache->setId(new Id($cacheId));

        /** @var Reservation $reservation */
        $reservation = $reservationService->getNew(true, true, true);

        $result = $reservationService->processRequest(
            $bookingData,
            $reservation,
            true
        );

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            $cacheRepository->delete($cache->getId()->getValue());

            return $result;
        }

        $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings(
            $reservation,
            PaymentType::RAZORPAY
        );

        $identifier = $cacheId . '_' . $token->getValue() . '_' . $type;

        $notes = $additionalInformation['metaData'] ?: [];
        $notes['ameliaCache'] = $identifier;

        $orderData = [
            'amount' => $paymentService->toPaise($paymentAmount),
            'notes'  => $notes,
        ];

        $orderData = apply_filters(
            'amelia_before_razorpay_execute_filter',
            $orderData,
            $reservation->getReservation()->toArray()
        );

        do_action('amelia_before_razorpay_execute', $orderData, $reservation->getReservation()->toArray());

        $transfers = [];

        try {
            $razorpayOrder = $paymentService->execute($orderData, $transfers);
        } catch (Exception $e) {
            $reservationService->deleteReservation($reservation);
            $cacheRepository->delete($cache->getId()->getValue());

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message' => $e->getMessage() && json_decode($e->getMessage(), true) !== false ?
                        json_decode($e->getMessage(), true)['detail'] : '',
                    'paymentSuccessful' => false,
                ]
            );

            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Razorpay payment processing failed',
                [
                    'exception'     => $e,
                    'reservationId' => $reservation->getReservation()->getId() ?
                        $reservation->getReservation()->getId()->getValue() : null,
                    'amount'        => $paymentAmount,
                ]
            );

            return $result;
        }

        $razorpayOrderId = $razorpayOrder['id'];
        $result = $paymentAS->updateCache($result, $command->getFields(), $cache, $reservation, null, $razorpayOrderId);
        $resultData = $result->getData();

        $data = [
            'key'         => $paymentService->getKeyId(),
            'amount'      => $orderData['amount'],
            'name'        => $additionalInformation['name'],
            'description' => $additionalInformation['description'] ?:
                $reservation->getBookable()->getName()->getValue(),
            'prefill'     => [
                'name'    => $reservation->getCustomer()->getFullName(),
                'email'   => $reservation->getCustomer()->getEmail() ?
                    $reservation->getCustomer()->getEmail()->getValue() : '',
                'contact' => $reservation->getCustomer()->getPhone() ?
                    $reservation->getCustomer()->getPhone()->getValue() : '',
            ],
            'order_id'    => $razorpayOrderId,
            'notes'       => $notes,
        ];

        $data = apply_filters('amelia_after_razorpay_execute_filter', $data, $reservation->getReservation()->toArray());

        do_action('amelia_after_razorpay_execute', $data, $reservation->getReservation()->toArray());

        $bookings = [];

        if (!empty($resultData['type']) && $resultData['type'] !== 'package') {
            if (!empty($resultData['booking']['id']) && !empty($resultData['booking']['token'])) {
                $bookings[] = [
                    'id'    => $resultData['booking']['id'],
                    'token' => $resultData['booking']['token'],
                ];
            }
            $recurringBookings = $resultData['recurring'] ?? [];
            foreach ($recurringBookings as $recurring) {
                if (empty($recurring['booking']['id']) || empty($recurring['booking']['token'])) {
                    continue;
                }
                $bookings[] = [
                    'id'    => $recurring['booking']['id'],
                    'token' => $recurring['booking']['token'],
                ];
            }
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Proceed to Razorpay Payment Module');
        $result->setData(
            [
                'data'            => $data,
                'name'            => $identifier,
                'bookings'        => $bookings,
                'packageCustomer' => !empty($resultData['packageCustomerId']) ? [
                    'id'    => $resultData['packageCustomerId'],
                    'token' => !empty($resultData['packageCustomerToken']) ?
                        $resultData['packageCustomerToken'] : '',
                ] : null,
            ]
        );

        return $result;
    }
}
