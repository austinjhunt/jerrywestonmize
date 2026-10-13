<?php

namespace AmeliaBooking\Application\Services\Stripe;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Payment\Payment;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\ValueObjects\String\PaymentStatus;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use Exception;

/**
 * Class StripeApplicationService
 *
 * @package AmeliaBooking\Application\Services\Stripe
 */
class StripeApplicationService extends AbstractStripeApplicationService
{
    /**
     * Releases slots that a Stripe checkout reserved and never paid for.
     *
     * A booking is saved with a pending payment before the customer can pay (see
     * CreateStripePaymentIntentCommandHandler), which is what stops two customers from paying for the same
     * slot. Leaving the payment step releases it through /payment/stripe/cancel, but a closed tab or a
     * dead browser never sends that, and a PaymentIntent left in requires_payment_method does not expire
     * on Stripe's side either - so without this the slot would stay reserved forever.
     *
     * The PaymentIntent is what decides, not the clock: an intent Stripe reports as paid or authorized is
     * finalized rather than released, and one that is not is cancelled *before* the booking is deleted, so
     * a customer who confirms during this run either wins the intent and keeps the booking, or is refused
     * by Stripe and pays nothing. Intended to run on a schedule (WP-Cron).
     *
     * @return void
     */
    public function reconcilePendingStripePayments(): void
    {
        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        // Comfortably longer than any checkout: Stripe will not even let its own Checkout Sessions
        // reserve inventory for less than 30 minutes.
        $cutoff = DateTimeService::getNowDateTimeObjectInUtc()->modify('-30 minutes')->format('Y-m-d H:i:s');

        $caches = $cacheRepository->getPendingByGatewayOlderThan(PaymentType::STRIPE, $cutoff);

        foreach ($caches as $cache) {
            try {
                $this->reconcilePendingStripeCache($cache, $paymentRepository, $stripeService);
            } catch (Exception $e) {
                $this->container->getLoggerService()->error(
                    'Stripe reconciliation failed for cache #' . $cache->getId()->getValue() . ': ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * @param Cache             $cache
     * @param PaymentRepository $paymentRepository
     * @param StripeService     $stripeService
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    private function reconcilePendingStripeCache($cache, $paymentRepository, $stripeService): void
    {
        $cacheData = json_decode($cache->getData()->getValue(), true);

        if (!empty($cacheData['status']) && $cacheData['status'] === PaymentStatus::PAID) {
            return;
        }

        $paymentIntentId = !empty($cacheData['stripePaymentIntentId'])
            ? (string)$cacheData['stripePaymentIntentId']
            : null;

        if (!$paymentIntentId || !$cache->getPaymentId()) {
            return;
        }

        // Only a reservation this flow took may be released. The fingerprint is written when the slot is
        // held ahead of the payment, so its absence means a pending Stripe payment that is not a hold at
        // all - one from a checkout that predates the update, or one an admin is settling by hand - and
        // deleting that booking would be destroying somebody's work, not tidying up after a closed tab.
        if (empty($cacheData['reservationKey'])) {
            return;
        }

        /** @var Payment|null $payment */
        $payment = $paymentRepository->getById($cache->getPaymentId()->getValue());

        if (!$payment || $payment->getStatus()->getValue() !== PaymentStatus::PENDING) {
            return;
        }

        $type = $payment->getEntity()->getValue();

        $transfers = !empty($cacheData['stripeTransfers']) ? $cacheData['stripeTransfers'] : [];

        $intentResult = $stripeService->completePaymentIntent($paymentIntentId, $transfers);
        $intentStatus = is_array($intentResult) ? $intentResult['status'] : $intentResult;

        // Still settling - a delayed notification method has not reported back yet.
        if (in_array($intentStatus, ['processing', 'requires_action', 'requires_source_action'], true)) {
            return;
        }

        if ($intentStatus === 'succeeded' || $intentStatus === 'requires_capture') {
            $this->finalizeReconciledStripePayment(
                $cache,
                $type,
                $paymentIntentId,
                $transfers,
                $intentStatus,
                $stripeService
            );

            return;
        }

        // Nothing was paid. Cancelling first is what makes deleting the booking safe: once Stripe reports
        // the intent as canceled it will not accept a confirmation for it, so the customer cannot end up
        // paying for a slot that was just given back.
        $cancelStatus = $stripeService->cancelPaymentIntent($paymentIntentId, $transfers);

        if ($cancelStatus === 'succeeded') {
            $this->finalizeReconciledStripePayment(
                $cache,
                $type,
                $paymentIntentId,
                $transfers,
                $cancelStatus,
                $stripeService
            );

            return;
        }

        if ($cancelStatus !== 'canceled') {
            $this->container->getLoggerService()->error(
                'Stripe reconciliation could not cancel PaymentIntent ' . $paymentIntentId . ' for cache #' .
                $cache->getId()->getValue() . ' - keeping the reservation until the next run'
            );

            return;
        }

        $this->container->getLoggerService()->info(
            'Stripe reconciliation released an unpaid reservation for cache #' . $cache->getId()->getValue() .
            ', PaymentIntent ' . $paymentIntentId
        );

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        $paymentAS->updateAppointmentAndCache($type, 'canceled', $cache, $paymentIntentId);
    }

    /**
     * @param Cache         $cache
     * @param string        $type
     * @param string        $paymentIntentId
     * @param array         $transfers
     * @param string        $intentStatus
     * @param StripeService $stripeService
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    private function finalizeReconciledStripePayment(
        $cache,
        $type,
        $paymentIntentId,
        $transfers,
        $intentStatus,
        $stripeService
    ): void {
        $stripeSettings = $this->container->get('domain.settings.service')->getSetting('payments', 'stripe');

        if ($intentStatus === 'requires_capture' && empty($stripeSettings['manualCapture'])) {
            try {
                $captureResult = $stripeService->capturePaymentIntent($paymentIntentId, $transfers);
            } catch (Exception $e) {
                $captureResult = ['status' => null, 'message' => $e->getMessage()];
            }

            $captureStatus = !empty($captureResult['status']) ? $captureResult['status'] : null;

            // transfer_failed means the funds were captured and only a Connect transfer failed.
            if ($captureStatus !== 'succeeded' && $captureStatus !== 'transfer_failed') {
                $this->container->getLoggerService()->error(
                    'Stripe reconciliation could not capture PaymentIntent ' . $paymentIntentId .
                    ' for cache #' . $cache->getId()->getValue()
                );

                return;
            }
        }

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        $result = $paymentAS->updateAppointmentAndCache($type, PaymentStatus::PAID, $cache, $paymentIntentId);

        if (!empty($transfers['accounts'])) {
            $paymentAS->setPaymentsTransfers($transfers);
        }

        // Whether the actions are still outstanding is settled by runPostBookingActions(), which
        // claims `triggeredActions` on the payment and does nothing when somebody else holds it.
        // It used to be settled by the runInstantPostBookingActions setting instead, and that was
        // wrong in both directions: the setting describes the ordinary AddBooking flow, which a
        // Stripe checkout never travels through - the reservation is saved straight from the
        // PaymentIntent handler - so reading it as proof that the notifications had already been
        // sent meant that, with it switched on, nothing sent them at all.
        if ($result->getResult() === CommandResult::RESULT_SUCCESS) {
            /** @var ReservationServiceInterface $reservationService */
            $reservationService = $this->container->get('application.reservation.service')->get($type);

            $reservationService->runPostBookingActions($result);
        }
    }
}
