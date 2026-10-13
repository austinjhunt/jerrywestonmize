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
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
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
    /**
     * Message of a result that returns a booking somebody else finalized, so that a caller
     * which runs post-booking actions can tell it apart from one it finalized itself.
     */
    public const MESSAGE_ALREADY_FINALIZED = 'Booking already finalized';

    public $mandatoryFields = [
        'name',
        'paymentIntentId',
    ];

    /**
     * Runs a finalized booking's post-booking actions, unless somebody else already has.
     *
     * For a Stripe checkout nothing about the browser is guaranteed: the customer can close the
     * tab the moment 3D Secure returns, and the request that finalizes the payment then completes
     * on the server with nobody left to ask for notifications. So finalization runs them here
     * rather than waiting for the browser to call /bookings/success, which is what left paid
     * bookings with no notification at all when the window was closed before the Congrats page.
     *
     * Running them here means the browser, the webhook and the reconciliation cron can all reach
     * this point for the same payment, so the right to run them is claimed on the payment row and
     * only the single caller that wins the claim proceeds. The browser's own /bookings/success
     * call already honours the same flag, so it turns into a no-op instead of sending twice.
     *
     * A failure is logged and swallowed: the payment is captured and the booking exists, and
     * throwing here would turn a missing notification into a failed request or an endlessly
     * redelivered webhook.
     *
     * @param CommandResult $result
     *
     * @return void
     */
    private function runPostBookingActionsOnce($result): void
    {
        if ($result->getResult() !== CommandResult::RESULT_SUCCESS) {
            return;
        }

        $data = $result->getData() ?: [];

        $type = !empty($data['type']) ? $data['type'] : null;
        $paymentId = !empty($data['paymentId']) ? (int)$data['paymentId'] : 0;

        if (!in_array($type, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true)) {
            return;
        }

        // With no payment there is nothing for runPostBookingActions() to claim on, so it could
        // not tell that the browser is about to run the same actions. Sending a customer two of
        // every notification is worse than leaving this to the browser as it was before.
        if (!$paymentId) {
            return;
        }

        try {
            /** @var AbstractReservationService $reservationService */
            $reservationService = $this->container->get('application.reservation.service')->get($type);

            // Not guarded here: runPostBookingActions() claims `triggeredActions` on the payment
            // itself and returns without doing anything when somebody else already holds it, so
            // calling it twice for one payment sends one set of notifications.
            $reservationService->runPostBookingActions($result);
        } catch (Throwable $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: post-booking actions failed after finalization',
                [
                    'paymentId' => $paymentId,
                    'type'      => $type,
                    'exception' => $e->getMessage(),
                ]
            );
        }
    }

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
    public function complete($name, $paymentIntentId): CommandResult
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
            // A paid row whose claim was abandoned mid-finalization (the holder was killed by
            // a timeout or an OOM) is deliberately not short-circuited: falling through lets
            // claimForPaidFinalization() take the claim over, which is the difference between
            // a captured payment that is booked on the next delivery and one that silently
            // never gets booked at all.
            if (!CacheRepository::isPaidClaimAbandoned($cacheData)) {
                $paidResult = $this->paidCacheResult($cacheData);

                // The booking was finalized by whoever got here first - but finalizing is not
                // notifying, and if that caller was a request whose browser had already gone
                // away, nothing ran the post-booking actions. The claim decides: it succeeds
                // only when they are genuinely still outstanding.
                $this->runPostBookingActionsOnce($paidResult);

                return $paidResult;
            }

            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->warning(
                'Amelia Stripe: taking over an abandoned paid finalization',
                [
                    'name'            => $name,
                    'paymentIntentId' => $paymentIntentId,
                    'claimedAt'       => $cacheData['paidClaimedAt'],
                ]
            );
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
            // The reservation was saved before the customer could pay, so finalizing is only a matter of
            // capturing what was authorized and marking the existing booking paid - nothing is validated
            // here, and nothing can fail in a way that would need the money handed back.
            if ($cache->getPaymentId()) {
                if (!$this->captureAuthorizedFunds($stripeService, $paymentIntentId, $transfers, $stripeStatus)) {
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

                $result = $paymentAS->updateAppointmentAndCache($cachedType, $status, $cache, $paymentIntentId);

                if (!empty($transfers['accounts'])) {
                    $paymentAS->setPaymentsTransfers($transfers);
                }

                $this->runPostBookingActionsOnce($result);

                $result->setDataInResponse(true);

                return $result;
            }

            // Only reached by a row that has no payment linked to it, which since the reservation moved
            // ahead of the payment means a checkout that was started on an older version of the plugin
            // and finished after the update. Kept so those bookings still get created, and deliberately
            // not the path new checkouts take: it validates after the money is already gone.
            return $this->createBookingAfterPayment(
                $cache,
                $cacheData,
                $paymentIntentId,
                $transfers,
                $cachedType,
                $stripeStatus
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
     * @param string $stripeStatus
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    private function createBookingAfterPayment($cache, $cacheData, $paymentIntentId, $transfers, $type, $stripeStatus = null): CommandResult
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

        $this->restoreStagedCustomFieldFileNames($bookingRequest, $cacheData['bookingRequest']);

        // The intent id comes from the confirmed PaymentIntent, not from the cached request, so the payment
        // this booking is finalized against is the one the gateway captured.
        $bookingRequest['payment']['data']['paymentIntentId'] = $paymentIntentId;

        if (!$cacheRepository->claimForPaidFinalization($cache->getId()->getValue())) {
            try {
                $freshCache = $cacheRepository->getById($cache->getId()->getValue());
                $freshData = $freshCache && $freshCache->getData()
                    ? json_decode($freshCache->getData()->getValue(), true)
                    : $cacheData;
            } catch (NotFoundException $e) {
                $freshData = $cacheData;
            }

            return $this->paidCacheResult(is_array($freshData) ? $freshData : $cacheData);
        }

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
            // processRequest threw without reaching a terminal state - record one so the row is not left
            // looking unprocessed and replayable. An authorized intent was never charged, so the hold is
            // released here instead of sitting on the customer's card until it expires. Funds that were
            // already captured are not refunded: processRequest keeps the booking when the capture was
            // already recorded and only rolls it back otherwise, and that outcome is not visible here,
            // so refunding could take money back from a booking the customer still holds.
            try {
                $cacheData['needsManualReview'] = true;
                $cacheData['stripePaymentIntentId'] = $paymentIntentId;
                $cacheData['bookingError'] = $e->getMessage();

                if ($stripeStatus === 'requires_capture') {
                    $cancelStatus = $this->cancelAuthorizedFunds($stripeService, $paymentIntentId, $transfers);

                    $cacheData['cancelStatus'] = $cancelStatus;

                    if ($cancelStatus === 'canceled') {
                        $this->markCacheStatus($cache, $cacheData, 'failed');
                    } else {
                        $this->recordCancelFailure($cache, $cacheData, $paymentIntentId);
                    }
                } else {
                    $this->markCacheStatus($cache, $cacheData, 'failed');
                }
            } catch (Throwable $reconcileError) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                    'Amelia Stripe: failed to record booking failure for PaymentIntent',
                    [
                        'paymentIntentId' => $paymentIntentId,
                        'exception'       => $reconcileError,
                    ]
                );
            }

            throw $e;
        }

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            if ($stripeStatus === 'requires_capture') {
                $cancelStatus = $this->cancelAuthorizedFunds($stripeService, $paymentIntentId, $transfers);

                if ($cancelStatus === 'succeeded') {
                    $this->refundPaymentIntent($stripeService, $paymentIntentId, $transfers, $cache, $cacheData);
                } elseif ($cancelStatus !== 'canceled') {
                    $this->recordCancelFailure($cache, $cacheData, $paymentIntentId);

                    $resultData = $result->getData() ?: [];
                    $resultData['paymentSuccessful'] = false;
                    $result->setData($resultData);

                    return $result;
                }
            } else {
                $this->refundPaymentIntent($stripeService, $paymentIntentId, $transfers, $cache, $cacheData);
            }

            if (empty($cacheData['needsManualRefund'])) {
                $this->markCacheStatus($cache, $cacheData, 'failed');
            }

            $resultData = $result->getData() ?: [];
            $resultData['paymentSuccessful'] = false;
            $result->setData($resultData);

            return $result;
        }

        $captured = $this->captureAuthorizedFunds($stripeService, $paymentIntentId, $transfers, $stripeStatus);

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

        $cacheData['status'] = $captured ? 'paid' : 'captureFailed';
        $cacheData['response'] = $result->getData();

        $cache->setData(new Json(json_encode($cacheData)));

        $cacheRepository->update(
            $cache->getId()->getValue(),
            $cache
        );

        if (!$captured) {
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

        $this->runPostBookingActionsOnce($result);

        $result->setDataInResponse(true);

        return $result;
    }

    private function restoreStagedCustomFieldFileNames(&$bookingRequest, $cachedRequest): void
    {
        if (
            empty($bookingRequest['bookings'][0]['customFields']) ||
            !is_array($bookingRequest['bookings'][0]['customFields']) ||
            empty($cachedRequest['bookings'][0]['customFields']) ||
            !is_array($cachedRequest['bookings'][0]['customFields'])
        ) {
            return;
        }

        $cachedCustomFields = $cachedRequest['bookings'][0]['customFields'];

        foreach ($bookingRequest['bookings'][0]['customFields'] as $customFieldId => $customField) {
            if (
                empty($customField['type']) ||
                $customField['type'] !== 'file' ||
                empty($customField['value']) ||
                !is_array($customField['value'])
            ) {
                continue;
            }

            foreach (array_keys($customField['value']) as $index) {
                if (empty($cachedCustomFields[$customFieldId]['value'][$index]['fileName'])) {
                    continue;
                }

                $bookingRequest['bookings'][0]['customFields'][$customFieldId]['value'][$index]['fileName'] =
                    $cachedCustomFields[$customFieldId]['value'][$index]['fileName'];
            }
        }
    }

    /**
     * @param array $cacheData
     *
     * @return CommandResult
     */
    private function paidCacheResult($cacheData): CommandResult
    {
        $result = new CommandResult();

        if (!empty($cacheData['response']) && is_array($cacheData['response'])) {
            // Reported through the message rather than the data, because the data is the
            // booking payload the browser receives and this only concerns the caller: it
            // says the booking was finalized elsewhere, so post-booking actions
            // (notifications, integrations) must not run a second time.
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage(self::MESSAGE_ALREADY_FINALIZED);
            $result->setData($cacheData['response']);

            return $result;
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Payment is still processing');
        $result->setData(
            [
                'paymentSuccessful' => false,
                'status'            => 'processing',
            ]
        );

        return $result;
    }

    private function markCacheStatus($cache, $cacheData, $status): void
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
     * @param string        $stripeStatus
     *
     * @return bool
     */
    private function captureAuthorizedFunds($stripeService, $paymentIntentId, &$transfers, $stripeStatus): bool
    {
        $stripeSettings = $this->container->get('domain.settings.service')->getSetting('payments', 'stripe');

        if ($stripeStatus !== 'requires_capture' || !empty($stripeSettings['manualCapture'])) {
            return true;
        }

        try {
            $captureResult = $stripeService->capturePaymentIntent($paymentIntentId, $transfers);
        } catch (Exception $e) {
            $captureResult = ['status' => null, 'message' => $e->getMessage()];
        }

        $captureStatus = !empty($captureResult['status']) ? $captureResult['status'] : null;

        // transfer_failed means the funds were captured and only a Connect transfer failed, which
        // setPaymentsTransfers records from $transfers.
        if ($captureStatus === 'succeeded' || $captureStatus === 'transfer_failed') {
            return true;
        }

        $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
            'Amelia Stripe: failed to capture PaymentIntent after booking was saved',
            ['paymentIntentId' => $paymentIntentId, 'capture' => $captureResult]
        );

        return false;
    }

    /**
     * @param StripeService $stripeService
     * @param string        $paymentIntentId
     * @param array         $transfers
     *
     * @return string|null
     */
    private function cancelAuthorizedFunds($stripeService, $paymentIntentId, $transfers): ?string
    {
        $cancelStatus = $stripeService->cancelPaymentIntent($paymentIntentId, $transfers);

        if ($cancelStatus === 'canceled' || $cancelStatus === 'succeeded') {
            return $cancelStatus;
        }

        return $stripeService->cancelPaymentIntent($paymentIntentId, $transfers);
    }

    /**
     * @param Cache  $cache
     * @param array  $cacheData
     * @param string $paymentIntentId
     *
     * @return void
     * @throws QueryExecutionException
     */
    private function recordCancelFailure($cache, $cacheData, $paymentIntentId): void
    {
        $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
            'Amelia Stripe: failed to cancel PaymentIntent after booking failure',
            ['paymentIntentId' => $paymentIntentId]
        );

        $cacheData['needsManualReview'] = true;
        $cacheData['stripePaymentIntentId'] = $paymentIntentId;
        $cacheData['cancelError'] = 'PaymentIntent cancellation failed';

        $this->markCacheStatus($cache, $cacheData, 'cancelFailed');
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
    private function refundPaymentIntent($stripeService, $paymentIntentId, $transfers, $cache, &$cacheData): void
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
                    'exception'       => $e,
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
