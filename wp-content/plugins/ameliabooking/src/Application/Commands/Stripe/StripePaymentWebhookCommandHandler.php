<?php

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Commands\Payment\PaymentCallbackCommand;
use AmeliaBooking\Application\Commands\Payment\PaymentCallbackCommandHandler;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use AmeliaVendor\Stripe\Exception\SignatureVerificationException;
use AmeliaVendor\Stripe\PaymentIntent;
use Exception;
use UnexpectedValueException;

/**
 * Class StripePaymentWebhookCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class StripePaymentWebhookCommandHandler extends CommandHandler
{
    /**
     * @param StripePaymentWebhookCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     */
    public function handle(StripePaymentWebhookCommand $command): CommandResult
    {
        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        $payload = (string)$command->getField('payload');
        $signature = (string)$command->getField('signature');

        try {
            $event = $stripeService->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            $this->logPaymentError(
                'Stripe webhook signature verification failed',
                [
                    'exception' => $e,
                ]
            );

            throw new AccessDeniedException('Stripe webhook signature mismatch.');
        } catch (UnexpectedValueException $e) {
            $this->logPaymentError(
                'Stripe webhook event could not be constructed',
                [
                    'exception' => $e,
                ]
            );

            throw new AccessDeniedException(
                $e->getMessage() === 'Stripe webhook secret is not configured'
                    ? 'Stripe webhook secret is not configured.'
                    : 'Stripe webhook payload is invalid.'
            );
        }

        $result = new CommandResult();
        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Stripe webhook received');
        $result->setData(
            [
                'received' => true,
                'type'     => $event->type,
            ]
        );

        if (
            $event->type !== 'payment_intent.succeeded' &&
            $event->type !== 'payment_intent.amount_capturable_updated'
        ) {
            return $result;
        }

        $paymentIntent = $event->data->object;

        if (!$paymentIntent instanceof PaymentIntent) {
            $this->logPaymentError('Stripe webhook object is not a PaymentIntent', ['type' => $event->type]);

            return $result;
        }

        $paymentIntentId = !empty($paymentIntent->id) ? (string)$paymentIntent->id : '';

        if ($paymentIntentId === '') {
            $this->logPaymentError('Stripe webhook PaymentIntent id is missing', ['type' => $event->type]);

            return $result;
        }

        $cacheName = null;

        try {
            // A payment link charge has no cache row - it is recorded by the payment link callback,
            // which this delivery stands in for when the customer closed the tab after paying.
            $linkMetaData = $this->getPaymentLinkMetaData($paymentIntent);

            if ($linkMetaData !== null) {
                return $event->type === 'payment_intent.succeeded'
                    ? $this->completePaymentLink($paymentIntentId, $linkMetaData, $result)
                    : $result;
            }

            $cacheName = $this->resolveCacheName($paymentIntent, $paymentIntentId);

            if (!$cacheName) {
                // The account is logged because it is what tells a platform delivery apart
                // from a connected account's direct charge, which is the case where a
                // missing Connect endpoint or secret shows up as an unresolvable event.
                $this->logPaymentError(
                    'Stripe webhook cache not found',
                    [
                        'paymentIntentId' => $paymentIntentId,
                        'type'            => $event->type,
                        'account'         => !empty($event->account) ? $event->account : null,
                    ]
                );

                return $result;
            }

            /** @var CompleteStripePaymentIntentCommandHandler $completeHandler */
            $completeHandler = $this->container->get(CompleteStripePaymentIntentCommandHandler::class);
            $completeResult = $completeHandler->complete($cacheName, $paymentIntentId);

            $completeData = $completeResult->getData() ?: [];

            // Post-booking actions are not run from here any more. complete() runs them for
            // every caller that finalizes a payment, under a claim on the payment row, which is
            // what makes them fire exactly once no matter who gets here first. The old gate
            // skipped them whenever the booking was "already finalized", and that is precisely
            // the closed-tab case: the browser's finalize request had landed and marked the row
            // paid, then died before it could ask for any notification, so this delivery stood
            // down and nobody ever sent one.

            if ($completeResult->getResult() === CommandResult::RESULT_ERROR) {
                $this->logPaymentError(
                    'Stripe webhook failed to complete PaymentIntent',
                    [
                        'paymentIntentId' => $paymentIntentId,
                        'cacheName'       => $cacheName,
                        'message'         => $completeResult->getMessage(),
                    ]
                );

                if ($this->isTransientCompleteFailure($completeResult)) {
                    return $this->retryableResult();
                }

                return $result;
            }

            // Succeeded without finalizing a payment: somebody else holds the finalization
            // claim right now, or held it and died, or Stripe still reports the intent as
            // processing. Acknowledging would spend the delivery, so the event is refused and
            // the redelivery finds either a finished booking or a claim stale enough to take
            // over. Only this explicit flag counts - a result that carries no data at all is
            // the "payment already recorded on an existing booking" path, which is finished.
            if (isset($completeData['paymentSuccessful']) && $completeData['paymentSuccessful'] === false) {
                return $this->retryableResult();
            }
        } catch (Exception $e) {
            $this->logPaymentError(
                'Stripe webhook exception while completing PaymentIntent',
                [
                    'paymentIntentId' => $paymentIntentId,
                    'cacheName'       => $cacheName,
                    'exception'       => $e,
                ]
            );

            // Transient failure (database, Stripe API, ...) - answer with a non-2xx
            // status so that Stripe redelivers the event instead of dropping it.
            return $this->retryableResult();
        }

        return $result;
    }

    /**
     * @param PaymentIntent $paymentIntent
     * @param string        $paymentIntentId
     *
     * @return string|null
     * @throws Exception
     */
    private function resolveCacheName($paymentIntent, $paymentIntentId): ?string
    {
        $intentData = $paymentIntent->toArray();
        $metadataName = !empty($intentData['metadata']['amelia_cache_name'])
            ? (string)$intentData['metadata']['amelia_cache_name']
            : '';

        if ($this->isValidCacheName($metadataName)) {
            return $metadataName;
        }

        try {
            /** @var CacheRepository $cacheRepository */
            $cacheRepository = $this->container->get('domain.cache.repository');

            /** @var Cache|null $cache */
            $cache = $cacheRepository->getByStripePaymentIntentId($paymentIntentId);
        } catch (Exception $e) {
            $this->logPaymentError(
                'Stripe webhook cache lookup failed',
                [
                    'paymentIntentId' => $paymentIntentId,
                    'exception'       => $e,
                ]
            );

            throw $e;
        }

        if (!$cache || !$cache->getId() || !$cache->getName()) {
            return null;
        }

        $cacheData = $cache->getData() ? json_decode($cache->getData()->getValue(), true) : [];
        $type = !empty($cacheData['type']) ? $cacheData['type'] : Entities::APPOINTMENT;

        if (!in_array($type, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true)) {
            $type = Entities::APPOINTMENT;
        }

        return $cache->getId()->getValue() . '_' . $cache->getName()->getValue() . '_' . $type;
    }

    /**
     * The keys a payment link puts on its PaymentIntent, or null when the charge did not come
     * from a payment link.
     *
     * @param PaymentIntent $paymentIntent
     *
     * @return array|null
     */
    private function getPaymentLinkMetaData($paymentIntent): ?array
    {
        $intentData = $paymentIntent->toArray();

        $metaData = !empty($intentData['metadata']) && is_array($intentData['metadata'])
            ? $intentData['metadata']
            : [];

        if (empty($metaData['amelia_link_payment_id']) || !isset($metaData['amelia_link_charged_amount'])) {
            return null;
        }

        return $metaData;
    }

    /**
     * Records a payment link charge through the callback the customer's browser is sent to after
     * paying. That callback locks the payment and skips a charge it has already recorded, so it
     * does not matter which of the two gets there first, or whether both do.
     *
     * @param string        $paymentIntentId
     * @param array         $linkMetaData
     * @param CommandResult $result
     *
     * @return CommandResult
     * @throws Exception
     */
    private function completePaymentLink(string $paymentIntentId, array $linkMetaData, CommandResult $result): CommandResult
    {
        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        $method = !empty($linkMetaData['amelia_link_transfer_method'])
            ? (string)$linkMetaData['amelia_link_transfer_method']
            : '';

        $accountId = !empty($linkMetaData['amelia_link_account_id'])
            ? (string)$linkMetaData['amelia_link_account_id']
            : '';

        $sessionId = $stripeService->getCheckoutSessionIdByPaymentIntent(
            $paymentIntentId,
            $method === 'direct' && $accountId ? $accountId : null
        );

        if (!$sessionId) {
            $this->logPaymentError(
                'Stripe webhook payment link session not found',
                [
                    'paymentIntentId' => $paymentIntentId,
                    'paymentId'       => $linkMetaData['amelia_link_payment_id'],
                ]
            );

            return $result;
        }

        // The same fields the redirect URL carries, so the charge is verified and recorded exactly
        // as it is when the customer comes back.
        $command = new PaymentCallbackCommand([]);
        $command->setField('fromLink', true);
        $command->setField('paymentAmeliaId', (int)$linkMetaData['amelia_link_payment_id']);
        $command->setField('chargedAmount', (string)$linkMetaData['amelia_link_charged_amount']);
        $command->setField('paymentMethod', 'stripe');
        $command->setField('session_id', $sessionId);

        if ($method && $accountId) {
            $command->setField('method', $method);
            $command->setField('accountId', $accountId);
        }

        try {
            $callbackResult = (new PaymentCallbackCommandHandler($this->container))->handle($command);
        } catch (NotFoundException $e) {
            // The booking was deleted after the customer paid, so there is nothing left to record
            // the charge on - redelivering would not change that.
            $this->logPaymentError(
                'Stripe webhook payment link payment not found',
                [
                    'paymentIntentId' => $paymentIntentId,
                    'paymentId'       => $linkMetaData['amelia_link_payment_id'],
                ]
            );

            return $result;
        }

        if ($callbackResult->getResult() === CommandResult::RESULT_ERROR) {
            $this->logPaymentError(
                'Stripe webhook failed to record payment link payment',
                [
                    'paymentIntentId' => $paymentIntentId,
                    'paymentId'       => $linkMetaData['amelia_link_payment_id'],
                    'message'         => $callbackResult->getMessage(),
                ]
            );

            return $this->retryableResult();
        }

        return $result;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function isValidCacheName($name): bool
    {
        $parts = explode('_', $name);

        return count($parts) >= 3 &&
            in_array($parts[2], [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true);
    }

    /**
     * Permanent complete() failures should not trigger Stripe retries.
     *
     * @param CommandResult $completeResult
     *
     * @return bool
     */
    private function isTransientCompleteFailure($completeResult): bool
    {
        $data = $completeResult->getData() ?: [];

        $permanentMessages = [
            'Invalid cache name format',
            'Cache object not saved',
            'Invalid entity type',
            'Payment intent mismatch',
            'Booking request not found',
        ];

        if (in_array($data['message'] ?? '', $permanentMessages, true)) {
            return false;
        }

        $status = isset($data['status']) ? (string)$data['status'] : '';

        return !in_array($status, ['canceled', 'requires_payment_method', 'transfer_failed'], true);
    }


    /**
     * A result the controller turns into a non-2xx response, which is how Stripe is told to
     * redeliver the event instead of dropping it.
     *
     * @return CommandResult
     */
    private function retryableResult(): CommandResult
    {
        $result = new CommandResult();

        $result->setResult(CommandResult::RESULT_ERROR);
        $result->setMessage('Stripe webhook could not be processed');
        $result->setData(['received' => true]);

        return $result;
    }

    /**
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    private function logPaymentError($message, $context = []): void
    {
        $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
            $message,
            $context
        );
    }
}
