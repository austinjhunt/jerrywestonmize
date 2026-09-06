<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Payment;

use AmeliaBooking\Domain\Services\Payment\AbstractPaymentService;
use AmeliaBooking\Domain\Services\Payment\PaymentServiceInterface;
use AmeliaBooking\Domain\ValueObjects\Number\Float\Price;
use Exception;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Razorpay\Api\Api;

/**
 * Class RazorpayService
 */
class RazorpayService extends AbstractPaymentService implements PaymentServiceInterface
{
    private $keyId = '';


    /**
     *
     * @return string
     */
    public function getKeyId()
    {
        return $this->keyId;
    }

    /**
     *
     * @return Api
     * @throws Exception
     */
    private function getApi()
    {
        $keyId = $this->settingsService->getCategorySettings('payments')['razorpay']['testMode'] ?
            $this->settingsService->getCategorySettings('payments')['razorpay']['testKeyId'] :
            $this->settingsService->getCategorySettings('payments')['razorpay']['liveKeyId'];

        $this->keyId = $keyId;

        $keySecret = $this->settingsService->getCategorySettings('payments')['razorpay']['testMode'] ?
            $this->settingsService->getCategorySettings('payments')['razorpay']['testKeySecret'] :
            $this->settingsService->getCategorySettings('payments')['razorpay']['liveKeySecret'];


        return new Api($keyId, $keySecret);
    }

    /**
     * @param array $data
     * @param array $transfers
     *
     * @return mixed
     * @throws Exception
     */
    public function execute($data, &$transfers)
    {
        $orderData = [
            'amount'   => $data['amount'],
            'currency' => $this->settingsService->getCategorySettings('payments')['currency'],
        ];

        if (!empty($data['notes']) && is_array($data['notes'])) {
            $orderData['notes'] = $data['notes'];
        }

        try {
            return $this->getApi()->order->create($orderData);
        } catch (Exception $e) {
            $this->logger->error('Razorpay order create failed', ['gateway' => 'razorpay', 'exception' => $e]);

            throw $e;
        }
    }

    /**
     * @param string $orderId
     *
     * @return array
     * @throws Exception
     */
    public function fetchOrder($orderId)
    {
        $order = $this->getApi()->order->fetch($orderId);

        return $order ? $order->toArray() : [];
    }

    /**
     * @param string $orderId
     *
     * @return array
     * @throws Exception
     */
    public function fetchOrderPayments($orderId)
    {
        $payments = $this->getApi()->order->fetch($orderId)->payments();

        if (!$payments) {
            return [];
        }

        $items = [];

        foreach ($payments->items as $payment) {
            $items[] = is_array($payment) ? $payment : $payment->toArray();
        }

        return $items;
    }


    /**
     * @param $paymentId
     * @param $paymentAmount
     *
     * @return mixed
     * @throws Exception
     */
    public function capture($paymentId, $paymentAmount)
    {
        $payment = $this->getApi()->payment->fetch($paymentId);

        if (
            $payment &&
            ($paymentData = $payment->toArray()) &&
            !empty($paymentData['status']) &&
            $paymentData['status'] === 'captured'
        ) {
            if (!$this->amountMatches($paymentData['amount'] ?? 0, $paymentAmount)) {
                return [
                    'error_code'        => 1,
                    'error_description' => 'Captured amount does not match the expected booking amount',
                ];
            }

            return [
                'error_code' => 0,
            ];
        }

        return $payment->capture(
            [
                'amount'   => $this->toPaise($paymentAmount),
                'currency' => $this->settingsService->getCategorySettings('payments')['currency']
            ]
        );
    }

    /**
     * Convert a major-unit amount into the payment currency's smallest unit.
     *
     * @param float $amount
     *
     * @return int
     */
    public function toPaise($amount)
    {
        return (int) $this->currencyService->getAmountInFractionalUnit(new Price($amount));
    }

    /**
     * Convert a smallest-unit amount into major currency units.
     *
     * @param int|float $minorAmount
     *
     * @return float
     */
    public function fromPaise($minorAmount)
    {
        $exponent = $this->getCurrencySubunitExponent();

        if ($exponent <= 0) {
            return (float) $minorAmount;
        }

        return ((int) $minorAmount) / (10 ** $exponent);
    }

    /**
     * @return int
     */
    private function getCurrencySubunitExponent()
    {
        $currencies = new ISOCurrencies();
        $currency = new Currency(
            $this->settingsService->getCategorySettings('payments')['currency']
        );

        return $currencies->subunitFor($currency);
    }

    /**
     * @param int   $actualPaise
     * @param float $expectedAmount
     *
     * @return bool
     */
    public function amountMatches($actualPaise, $expectedAmount)
    {
        return (int) $actualPaise === $this->toPaise($expectedAmount);
    }

    /**
     * @param $attributes
     *
     * @return mixed
     * @throws Exception
     */
    public function verify($attributes)
    {
        try {
            return $this->getApi()->utility->verifyPaymentSignature($attributes);
        } catch (Exception $e) {
            $this->logger->error(
                'Razorpay signature verification failed',
                ['gateway' => 'razorpay', 'exception' => $e]
            );

            throw $e;
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function getPaymentLink($data)
    {
        $paymentLink = $this->getApi()->paymentLink->create($data);
        if ($paymentLink['status'] === 'created' && !empty($paymentLink['short_url'])) {
            return ['link' => $paymentLink['short_url'], 'status' => 200];
        }
        return ['message' => $paymentLink['message'], 'status' => $paymentLink['status']];
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws \Exception
     */
    public function refund($data)
    {
        $props = [];

        if (!empty($data['amount'])) {
            $props['amount'] = $this->toPaise($data['amount']);
        }

        try {
            $refund = $this->getApi()->payment->fetch($data['id'])->refund($props);

            $result = ['error' => $refund->toArray()['status'] !== 'processed'];

            if ($result['error']) {
                $this->logger->error('Razorpay refund failed', ['gateway' => 'razorpay']);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Razorpay refund failed', ['gateway' => 'razorpay', 'exception' => $e]);

            throw $e;
        }
    }

    /**
     * @param string     $id
     * @param array|null $transfers
     *
     * @return mixed
     * @throws \Exception
     */
    public function getTransactionAmount($id, $transfers)
    {
        $payment = $this->getApi()->payment->fetch($id);
        return $payment ? $this->fromPaise($payment->amount) : null;
    }
}
