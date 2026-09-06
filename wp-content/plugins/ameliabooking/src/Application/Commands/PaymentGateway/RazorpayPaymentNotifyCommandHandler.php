<?php

namespace AmeliaBooking\Application\Commands\PaymentGateway;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;
use AmeliaBooking\Infrastructure\Services\Payment\RazorpayService;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;
use Interop\Container\Exception\ContainerException;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * Class RazorpayPaymentNotifyCommandHandler
 *
 * Finalizes a pending Razorpay booking after checkout success (client callback).
 *
 * @package AmeliaBooking\Application\Commands\PaymentGateway
 */
class RazorpayPaymentNotifyCommandHandler extends CommandHandler
{
    /**
     * @param RazorpayPaymentNotifyCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ContainerException
     */
    public function handle(RazorpayPaymentNotifyCommand $command)
    {
        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var RazorpayService $paymentService */
        $paymentService = $this->container->get('infrastructure.payment.razorpay.service');

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        $result = new CommandResult();

        $name = $command->getField('name');
        $paymentId = $command->getField('paymentId');
        $signature = $command->getField('signature');
        $orderId = $command->getField('orderId');

        if (!$name || !$paymentId || !$signature || !$orderId) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Missing Razorpay payment fields',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        try {
            $paymentService->verify(
                [
                    'razorpay_order_id'   => $orderId,
                    'razorpay_payment_id' => $paymentId,
                    'razorpay_signature'  => $signature,
                ]
            );
        } catch (SignatureVerificationError $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Invalid Razorpay signature',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        } catch (Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Razorpay signature verification failed',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        try {
            $order = $paymentService->fetchOrder($orderId);
        } catch (Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Razorpay order lookup failed',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        $orderIdentifier = !empty($order['notes']['ameliaCache']) ? $order['notes']['ameliaCache'] : null;

        if (!$orderIdentifier || $orderIdentifier !== $name) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Razorpay order does not match the requested booking',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        $cacheParts = explode('_', $orderIdentifier);

        if (!isset($cacheParts[0], $cacheParts[1])) {
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

        $transactionOpen = false;

        try {
            $cacheRepository->beginTransaction();
            $transactionOpen = true;

            /** @var Cache|null $cache */
            $cache = $cacheRepository->getByIdForUpdate((int) $cacheParts[0]);

            if (
                !$cache ||
                !$cache->getPaymentId() ||
                !$cache->getName() ||
                $cache->getName()->getValue() !== $cacheParts[1]
            ) {
                $cacheRepository->rollback();
                $transactionOpen = false;

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

            if (!empty($cacheData['status']) && $cacheData['status'] === 'paid') {
                $cacheRepository->commit();
                $transactionOpen = false;

                $result->setResult(CommandResult::RESULT_SUCCESS);
                $result->setMessage('Razorpay payment already finalized');
                $result->setData(
                    array_merge(
                        !empty($cacheData['response']) && is_array($cacheData['response']) ? $cacheData['response'] : [],
                        ['paymentSuccessful' => true]
                    )
                );

                return $result;
            }

            $paymentAmount = $this->getCachedPaymentAmount($cache);

            if (!$paymentService->amountMatches($order['amount'] ?? 0, $paymentAmount)) {
                $cacheRepository->rollback();
                $transactionOpen = false;

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                $result->setData(
                    [
                        'message'           => 'Razorpay order amount does not match the expected booking amount',
                        'paymentSuccessful' => false,
                    ]
                );

                return $result;
            }

            try {
                $response = $paymentService->capture($paymentId, $paymentAmount);
            } catch (Exception $e) {
                $cacheRepository->rollback();
                $transactionOpen = false;

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                $result->setData(
                    [
                        'message'           => 'Razorpay capture failed',
                        'paymentSuccessful' => false,
                    ]
                );

                return $result;
            }

            if (is_object($response) && method_exists($response, 'toArray')) {
                $response = $response->toArray();
            }

            $captureSucceeded = is_array($response) && (
                (!empty($response['status']) && $response['status'] === 'captured') ||
                (isset($response['error_code']) && (int) $response['error_code'] === 0)
            );

            if (!$captureSucceeded) {
                $cacheRepository->rollback();
                $transactionOpen = false;

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                $result->setData(
                    [
                        'message'           => 'Razorpay capture failed',
                        'paymentSuccessful' => false,
                    ]
                );

                return $result;
            }

            $cacheRepository->commit();
            $transactionOpen = false;
        } catch (Exception $e) {
            if ($transactionOpen) {
                $cacheRepository->rollback();
            }

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'message'           => 'Razorpay payment processing failed',
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        $type = isset($cacheParts[2]) ? $cacheParts[2] : 'appointment';

        $result = $paymentAS->updateAppointmentAndCache($type, 'paid', $cache, $paymentId);
        $result->setDataInResponse(true);

        return $result;
    }

    /**
     * @param Cache $cache
     *
     * @return float
     */
    private function getCachedPaymentAmount(Cache $cache)
    {
        if (!$cache->getPaymentId()) {
            return 0;
        }

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        $payment = $paymentRepository->getById($cache->getPaymentId()->getValue());

        return $payment && $payment->getAmount() ? (float) $payment->getAmount()->getValue() : 0;
    }
}
