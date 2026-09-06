<?php

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Application\Services\Reservation\AbstractReservationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;
use Throwable;

/**
 * Class CompleteStripePaymentIntentCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class CompleteStripePaymentIntentCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'name',
        'paymentIntentId',
    ];

    /**
     * @param CompleteStripePaymentIntentCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function handle(CompleteStripePaymentIntentCommand $command)
    {
        $this->checkMandatoryFields($command);

        return $this->complete(
            $command->getField('name'),
            $command->getField('paymentIntentId')
        );
    }

    /**
     * @param string $name
     * @param string $paymentIntentId
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function complete($name, $paymentIntentId)
    {
        $result = new CommandResult();

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        $data = explode('_', $name);
        $requestedType = isset($data[2]) ? $data[2] : null;

        if (
            count($data) < 3 ||
            !in_array($requestedType, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true)
        ) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: Invalid payment intent cache name format',
                ['name' => $name]
            );

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Invalid cache name format',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        /** @var Cache $cache */
        $cache = isset($data[0], $data[1]) ? $cacheRepository->getByIdAndName($data[0], $data[1]) : null;

        if (!$cache || !$cache->getData()) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Cache object not saved',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        $cacheData = json_decode($cache->getData()->getValue(), true);

        $cachedType = $this->getCachedType($cacheData);

        if ($cachedType !== $requestedType) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: Entity type mismatch for cache name',
                [
                    'name'         => $name,
                    'cachedType'    => $cachedType,
                    'requestedType' => $requestedType,
                ]
            );

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Invalid entity type',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        if (
            empty($cacheData['stripePaymentIntentId']) ||
            (string)$cacheData['stripePaymentIntentId'] !== (string)$paymentIntentId
        ) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: PaymentIntent mismatch for cache name',
                [
                    'name'            => $name,
                    'paymentIntentId' => $paymentIntentId,
                ]
            );

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Payment intent mismatch',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        if (!empty($cacheData['status']) && $cacheData['status'] === 'paid') {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully get booking');
            $result->setData($cacheData['response']);

            return $result;
        }

        $transfers = !empty($cacheData['stripeTransfers']) ? $cacheData['stripeTransfers'] : [];

        $stripeResult = $stripeService->completePaymentIntent($paymentIntentId, $transfers);
        $stripeStatus = is_array($stripeResult) ? $stripeResult['status'] : $stripeResult;
        $stripeErrorMessage = is_array($stripeResult) && !empty($stripeResult['message'])
            ? $stripeResult['message']
            : null;

        if ($stripeStatus === 'transfer_failed') {
            $cacheData['stripeTransfers'] = $transfers;
            $cache->setData(new Json(json_encode($cacheData)));

            $cacheRepository->update(
                $cache->getId()->getValue(),
                $cache
            );
        }

        $status = $this->mapStripeStatus($stripeStatus);

        if ($status === 'paid') {
            if ($cache->getPaymentId()) {
                $result = $paymentAS->updateAppointmentAndCache($cachedType, $status, $cache, $paymentIntentId);

                if (!empty($transfers['accounts'])) {
                    $paymentAS->setPaymentsTransfers($transfers);
                }

                $result->setDataInResponse(true);

                return $result;
            }

            return $this->createBookingAfterPayment(
                $cache,
                $cacheData,
                $paymentIntentId,
                $transfers,
                $cachedType
            );
        }

        if ($status === 'failed' || $status === 'canceled' || $status === 'expired') {
            if ($cache->getPaymentId()) {
                $result = $paymentAS->updateAppointmentAndCache($cachedType, $status, $cache, $paymentIntentId);
                $result->setDataInResponse(true);

                return $result;
            }

            $this->markCacheStatus($cache, $cacheData, $status);

            $errorMessage = $stripeErrorMessage ?: FrontendStrings::getCommonStrings()['payment_error'];

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage($errorMessage);
            $result->setData(
                [
                    'paymentSuccessful' => false,
                    'status'            => $stripeStatus,
                    'message'           => $errorMessage,
                ]
            );

            return $result;
        }

        if ($status === 'pending') {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Payment is still processing');
            $result->setData(
                [
                    'paymentSuccessful' => false,
                    'status'            => $stripeStatus,
                ]
            );

            return $result;
        }

        $errorMessage = $stripeErrorMessage ?: FrontendStrings::getCommonStrings()['payment_error'];

        $result->setResult(CommandResult::RESULT_ERROR);
        $result->setMessage($errorMessage);
        $result->setData(
            [
                'paymentSuccessful' => false,
                'status'            => $stripeStatus,
                'message'           => $errorMessage,
            ]
        );

        return $result;
    }

    private function getCachedType($cacheData)
    {
        $cachedType = is_array($cacheData) && !empty($cacheData['type']) ? $cacheData['type'] : null;

        if (!$cachedType && !empty($cacheData['request']['type'])) {
            $cachedType = $cacheData['request']['type'];
        }

        if (!$cachedType && !empty($cacheData['request']['state']['appointment']['type'])) {
            $cachedType = $cacheData['request']['state']['appointment']['type'];
        }

        if (!$cachedType && !empty($cacheData['response']['type'])) {
            $cachedType = $cacheData['response']['type'];
        }

        return in_array($cachedType, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true) ?
            $cachedType : null;
    }

    private function mapStripeStatus($status)
    {
        switch ($status) {
            case 'succeeded':
            case 'requires_capture':
                return 'paid';

            case 'processing':
            case 'requires_action':
            case 'requires_source_action':
            case 'requires_confirmation':
                // Finalization should wait for Stripe's payment_intent.succeeded outcome.
                return 'pending';

            case 'canceled':
                return 'canceled';

            case 'requires_payment_method':
            case 'transfer_failed':
                return 'failed';

            default:
                return null;
        }
    }

    /**
     * @param Cache  $cache
     * @param array  $cacheData
     * @param string $paymentIntentId
     * @param array  $transfers
     * @param string $type
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    private function createBookingAfterPayment($cache, $cacheData, $paymentIntentId, $transfers, $type)
    {
        $result = new CommandResult();

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var AbstractReservationService $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        if (empty($cacheData['bookingRequest']) || !is_array($cacheData['bookingRequest'])) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Booking request not found',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        // normalized, not validated: the cached request carries the payment object that was already
        // validated when the intent was created, and this runs after Stripe captured the funds, where
        // refusing a payment Amelia itself built would leave the customer paid for and unbooked
        $bookingRequest = $this->getAppointmentData($cacheData['bookingRequest']);

        // The intent id comes from the confirmed PaymentIntent, not from the cached request, so the payment
        // this booking is finalized against is the one the gateway captured.
        $bookingRequest['payment']['data']['paymentIntentId'] = $paymentIntentId;

        if (isset($bookingRequest['payment']['data']['createPaymentIntent'])) {
            unset($bookingRequest['payment']['data']['createPaymentIntent']);
        }

        $reservation = $reservationService->getNew(true, true, true);

        try {
            // $paymentVerified: reCAPTCHA was validated before the intent was created, the token it was
            // verified with is single use, and this runs after the funds were captured - re-checking it
            // could only fail a customer who already paid. The flag is set here, on the server, after the
            // PaymentIntent was confirmed with Stripe; it is never read from the cached booking request.
            $result = $reservationService->processRequest(
                $bookingRequest,
                $reservation,
                true,
                true
            );
        } catch (Throwable $e) {
            // Stripe captured the funds before this method was entered, and processRequest threw without
            // reaching a terminal state - record one so the row is not left looking unprocessed and
            // replayable. No automatic refund: processRequest keeps the booking when the capture was
            // already recorded and only rolls it back otherwise, and that outcome is not visible here,
            // so refunding could take money back from a booking the customer still holds.
            try {
                $cacheData['needsManualReview'] = true;
                $cacheData['stripePaymentIntentId'] = $paymentIntentId;
                $cacheData['bookingError'] = $e->getMessage();

                $this->markCacheStatus($cache, $cacheData, 'failed');
            } catch (Throwable $reconcileError) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                    'Amelia Stripe: failed to record booking failure for PaymentIntent',
                    [
                        'paymentIntentId' => $paymentIntentId,
                        'exception'       => $reconcileError->getMessage(),
                    ]
                );
            }

            throw $e;
        }

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            $this->refundPaymentIntent($stripeService, $paymentIntentId, $transfers, $cache, $cacheData);
            $this->markCacheStatus($cache, $cacheData, 'failed');

            $resultData = $result->getData() ?: [];
            $resultData['paymentSuccessful'] = false;
            $result->setData($resultData);

            return $result;
        }

        $componentProps = !empty($cacheData['request']) ? $cacheData['request'] : [];

        $result = $paymentAS->updateCache(
            $result,
            ['componentProps' => $componentProps],
            $cache,
            $reservation
        );

        if (!empty($transfers['accounts'])) {
            $paymentAS->setPaymentsTransfers($transfers);
        }

        $cacheData['status'] = 'paid';
        $cacheData['response'] = $result->getData();

        $cache->setData(new Json(json_encode($cacheData)));

        $cacheRepository->update(
            $cache->getId()->getValue(),
            $cache
        );

        $result->setDataInResponse(true);

        return $result;
    }

    /**
     * @param Cache  $cache
     * @param array  $cacheData
     * @param string $status
     *
     * @return void
     * @throws QueryExecutionException
     */
    private function markCacheStatus($cache, $cacheData, $status)
    {
        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        $cacheData['status'] = $status;

        $cache->setData(new Json(json_encode($cacheData)));

        $cacheRepository->update(
            $cache->getId()->getValue(),
            $cache
        );
    }

    /**
     * @param StripeService $stripeService
     * @param string        $paymentIntentId
     * @param array         $transfers
     * @param Cache         $cache
     * @param array         $cacheData
     *
     * @return void
     */
    private function refundPaymentIntent($stripeService, $paymentIntentId, $transfers, $cache, $cacheData)
    {
        try {
            $stripeService->refund(
                [
                    'id'        => $paymentIntentId,
                    'transfers' => $transfers,
                ]
            );
        } catch (Exception $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: failed to refund PaymentIntent after booking failure',
                [
                    'paymentIntentId' => $paymentIntentId,
                    'exception'       => $e->getMessage(),
                ]
            );

            $cacheData['status'] = 'refundFailed';
            $cacheData['needsManualRefund'] = true;
            $cacheData['stripePaymentIntentId'] = $paymentIntentId;
            $cacheData['refundError'] = $e->getMessage();

            $cache->setData(new Json(json_encode($cacheData)));

            /** @var CacheRepository $cacheRepository */
            $cacheRepository = $this->container->get('domain.cache.repository');

            $cacheRepository->update(
                $cache->getId()->getValue(),
                $cache
            );

            do_action(
                'amelia_stripe_refund_failed',
                $paymentIntentId,
                $e->getMessage(),
                $cacheData
            );
        }
    }
}
