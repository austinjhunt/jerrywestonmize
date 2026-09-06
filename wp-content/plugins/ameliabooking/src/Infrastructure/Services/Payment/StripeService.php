<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Payment;

use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Stripe\StripeConnect;
use AmeliaBooking\Domain\Factory\Stripe\StripeFactory;
use AmeliaBooking\Domain\Services\Payment\AbstractPaymentService;
use AmeliaBooking\Domain\Services\Payment\PaymentServiceInterface;
use AmeliaBooking\Domain\ValueObjects\Number\Float\Price;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Domain\ValueObjects\String\Token;
use AmeliaBooking\Infrastructure\Repository\User\CustomerRepository;
use AmeliaVendor\Stripe\Customer;
use AmeliaVendor\Stripe\ErrorObject;
use AmeliaVendor\Stripe\Exception\ApiErrorException;
use AmeliaVendor\Stripe\Exception\AuthenticationException;
use AmeliaVendor\Stripe\PaymentMethod;
use AmeliaVendor\Stripe\Stripe;
use AmeliaVendor\Stripe\StripeClient;
use AmeliaVendor\Stripe\Account;
use AmeliaVendor\Stripe\AccountLink;
use AmeliaVendor\Stripe\Transfer;
use AmeliaVendor\Stripe\PaymentIntent;
use Exception;

/**
 * Class StripeService
 */
class StripeService extends AbstractPaymentService implements PaymentServiceInterface
{
    /**
     * @param array $data
     * @param array $transfers
     *
     * @return mixed
     * @throws \Exception
     */
    public function execute($data, &$transfers)
    {
        try {
            $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

            Stripe::setApiKey(
                $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
            );

            $stripeConnectSettings = $stripeSettings['connect'];

            $intent = null;

            $customerId = null;

            if ($data['paymentMethodId']) {
                $stripeData = [
                    'payment_method'       => $data['paymentMethodId'],
                    'amount'               => $data['amount'],
                    'currency'             => $this->settingsService->getCategorySettings('payments')['currency'],
                    'confirm'              => true,
                    'automatic_payment_methods' => [
                        'enabled'         => 'true',
                        'allow_redirects' => 'never'
                    ]
                ];

                if ($stripeSettings['returnUrl']) {
                    $stripeData['return_url'] = $stripeSettings['returnUrl'];
                }

                if (
                    $stripeConnectSettings['enabled'] &&
                    $stripeConnectSettings['method'] === 'transfer' &&
                    sizeof($transfers['accounts']) > 0
                ) {
                    $hasTransfer = false;

                    foreach ($transfers['accounts'] as $payments) {
                        foreach ($payments as $payment) {
                            if ($payment['amount'] && $payment['amount'] > 0) {
                                $hasTransfer = true;
                            }
                        }
                    }

                    if ($hasTransfer) {
                        $token = new Token();

                        $stripeData['transfer_group'] = $token->getValue();
                    }
                }

                $additionalStripeData = [];

                if (
                    $stripeConnectSettings['enabled'] &&
                    sizeof($transfers['accounts']) === 1 &&
                    $stripeConnectSettings['method'] === 'direct'
                ) {
                    $platformFee = 0;

                    foreach ($transfers['accounts'] as $payments) {
                        foreach ($payments as $payment) {
                            $platformFee += $payment['amount'];
                        }
                    }

                    $stripeData['application_fee_amount'] = $platformFee;

                    $additionalStripeData = ['stripe_account' => array_keys($transfers['accounts'])[0]];
                }

                if ($stripeSettings['manualCapture']) {
                    $stripeData['capture_method'] = 'manual';
                }

                if ($data['metaData']) {
                    $stripeData['metadata'] = $data['metaData'];
                }

                if ($data['description']) {
                    $stripeData['description'] = $data['description'];
                }

                $customerId = $this->createCustomer($data, $additionalStripeData);

                if ($customerId) {
                    $stripeData = array_merge($stripeData, ['customer' => $customerId]);
                }

                if (!empty($data['customerData']) && !empty($data['customerData']['email'])) {
                    $stripeData['receipt_email'] = $data['customerData']['email'];
                }

                $stripeData = apply_filters(
                    'amelia_before_stripe_payment',
                    $stripeData
                );

                $intent = PaymentIntent::create($stripeData, $additionalStripeData);


                if (
                    $stripeConnectSettings['enabled'] &&
                    $stripeConnectSettings['method'] === 'transfer'
                ) {
                    foreach ($transfers['accounts'] as $accountId => $payments) {
                        foreach ($payments as $paymentId => $payment) {
                            if (!$payment['amount']) {
                                unset($transfers['accounts'][$accountId][$paymentId]);

                                continue;
                            }

                            try {
                                $transfer = Transfer::create(
                                    [
                                        'amount'         => $payment['amount'],
                                        'currency'       => $stripeData['currency'],
                                        'destination'    => $accountId,
                                        'transfer_group' => $stripeData['transfer_group'],
                                    ]
                                );

                                $transfers['accounts'][$accountId][$paymentId]['transferId'] = $transfer->id;
                            } catch (Exception $e) {
                                unset($transfers['accounts'][$accountId][$paymentId]);
                            }
                        }
                    }
                }
            }


            if ($data['paymentIntentId']) {
                $additionalData = [];

                if (
                    $stripeConnectSettings['enabled'] &&
                    sizeof($transfers['accounts']) === 1 &&
                    $stripeConnectSettings['method'] === 'direct'
                ) {
                    $additionalData['stripe_account'] = array_keys($transfers['accounts'])[0];
                }

                $intent = PaymentIntent::retrieve(
                    $data['paymentIntentId'],
                    $additionalData
                );

                if ($intent->status === 'requires_confirmation') {
                    $intent->confirm();
                }
            }

            if (
                $intent &&
                ($intent->status === 'requires_action' || $intent->status === 'requires_source_action') &&
                $intent->next_action->type === 'use_stripe_sdk'
            ) {
                return  [
                    'requiresAction'            => true,
                    'paymentIntentClientSecret' => $intent->client_secret,
                    'paymentIntentId'           => $intent->getLastResponse()->json['id'],
                    'customerId'                => $customerId
                ];
            } elseif ($intent && ($intent->status === 'succeeded' || ($stripeSettings['manualCapture'] && $intent->status === 'requires_capture'))) {
                return  [
                    'paymentSuccessful' => true,
                    'paymentIntentId'   => $intent->getLastResponse()->json['id'],
                    'customerId'        => $customerId
                ];
            }

            return  [
                'paymentSuccessful' => false
            ];
        } catch (Exception $e) {
            $this->logger->error(
                'Stripe charge creation failed',
                [
                    'exception' => $e,
                    'gateway'   => 'stripe',
                ]
            );

            throw $e;
        }
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws \AmeliaVendor\Stripe\Exception\ApiErrorException
     */
    public function getPaymentLink($data)
    {
        try {
            $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

            $stripe = new StripeClient(
                $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
            );

            $additionalStripeData = [];

            $fromPanel = !empty($data['fromPanel']);

            $redirectUrl = $data['returnUrl'] . '&session_id={CHECKOUT_SESSION_ID}';

            $customerId = null;

            if (!empty($data['transfer']) && $stripeSettings['connect']['method'] === 'direct') {
                $additionalStripeData = ['stripe_account' => $data['transfer']['accountId']];
            }

            $price = $stripe->prices->create(
                [
                    'unit_amount'  => $data['amount'],
                    'currency'     => $data['currency'],
                    'product_data' => ['name' => $data['description']],
                ],
                $additionalStripeData
            );

            if ($price) {
                $paymentLinkData = [
                    'line_items' => [
                        [
                            'price' => $price['id'],
                            'quantity' => 1,
                        ],
                    ],
                ];


                if (!empty($data['metaData'])) {
                    $paymentLinkData['metadata'] = $data['metaData'];
                }

                if (!empty($data['transfer'])) {
                    $method = '';

                    $transferData = [];

                    if ($stripeSettings['connect']['method'] === 'direct') {
                        $transferData['application_fee_amount'] = $data['amount'] - $data['transfer']['amount'];

                        $method = 'direct';
                    } elseif ($stripeSettings['connect']['method'] === 'transfer') {
                        $transferData['transfer_data'] = ['destination' => $data['transfer']['accountId']];

                        $transferData['transfer_data']['amount'] = $data['transfer']['amount'];

                        $method = 'destination';
                    }

                    if (!empty($transferData)) {
                        if ($fromPanel) {
                            $paymentLinkData['payment_intent_data'] = $transferData;
                        } else {
                            $paymentLinkData = array_merge($paymentLinkData, $transferData);
                        }
                    }

                    $redirectUrl .= '&accountId=' . $data['transfer']['accountId'] . '&method=' . $method;
                }

                if (!empty($stripeSettings['address'])) {
                    $paymentLinkData['billing_address_collection'] = 'required';
                }

                if ($fromPanel) {
                    $paymentLinkData['success_url'] = $redirectUrl;

                    $customerId = $this->createCustomer($data, $additionalStripeData);

                    if ($customerId) {
                        $paymentLinkData = array_merge($paymentLinkData, ['customer' => $customerId]);
                    }

                    $paymentLinkData['mode'] = 'payment';

                    $response = $stripe->checkout->sessions->create($paymentLinkData, $additionalStripeData);
                } else {
                    $paymentLinkData['after_completion'] = [
                        'type' => 'redirect',
                        'redirect' => [
                            'url' => $redirectUrl
                        ]
                    ];

                    $paymentLinkData['customer_creation'] = 'always';

                    $response = $stripe->paymentLinks->create($paymentLinkData, $additionalStripeData);
                }

                return $response && $response['url'] ?
                    ['link' => $response['url'], 'status' => 200, 'customerId' => $customerId] :
                    ['message' => $response['message'], 'status' => $response['status']];
            }

            return ['message' => $price['message'], 'status' => $price['status']];
        } catch (Exception $e) {
            $this->logger->error(
                'Stripe payment link creation failed',
                [
                    'exception' => $e,
                    'gateway'   => 'stripe',
                ]
            );

            throw $e;
        }
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws \Exception
     */
    public function refund($data)
    {
        try {
            $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

            $secretKey = $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey'];

            $stripe = new StripeClient($secretKey);

            $props = [
                'payment_intent' => $data['id'],
            ];

            if (!empty($data['amount'])) {
                $props['amount'] = $this->currencyService->getAmountInFractionalUnit(new Price($data['amount']));
            }

            $additionalProps = [];

            if (!empty($data['transfers']) && $data['transfers']['method'] === 'destination') {
                $props['refund_application_fee'] = true;

                $props['reverse_transfer'] = true;
            }

            if (!empty($data['transfers']) && $data['transfers']['method'] === 'direct') {
                $props['refund_application_fee'] = true;

                $additionalProps = ['stripe_account' => array_keys($data['transfers']['accounts'])[0]];
            }

            $response = $stripe->refunds->create($props, $additionalProps);

            if (!empty($data['transfers']) && $data['transfers']['method'] === 'transfer') {
                foreach ($data['transfers']['accounts'] as $transfers) {
                    foreach ($transfers as $transferId => $amount) {
                        $stripe->transfers->createReversal($transferId, ['amount' => $amount]);
                    }
                }
            }

            $hasError = $response->getLastResponse()->code !== 200;

            if ($hasError) {
                $this->logger->error(
                    'Stripe refund failed',
                    [
                        'gateway'    => 'stripe',
                        'payment_id' => $data['id'] ?? null,
                        'http_code'  => $response->getLastResponse()->code,
                    ]
                );
            }

            return ['error' => $hasError];
        } catch (Exception $e) {
            $this->logger->error(
                'Stripe refund failed',
                [
                    'exception'  => $e,
                    'payment_id' => $data['id'] ?? null,
                ]
            );

            throw $e;
        }
    }

    /**
     * @param string $sessionId
     * @param string $method
     * @param string $accountId
     *
     * @return array
     */
    public function getPaymentIntent($sessionId, $method, $accountId)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $secretKey = $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey'];

        $stripe = new StripeClient($secretKey);

        $additionalStripeData = [];

        if ($method === 'direct' && $accountId) {
            $additionalStripeData = ['stripe_account' => $accountId];
        }

        $response =  $stripe->checkout->sessions->retrieve($sessionId, [], $additionalStripeData);

        return $response->getLastResponse()->code === 200 ? ['payment_intent' => $response['payment_intent'], 'customer' => $response['customer']] : null;
    }

    /**
     * @param string $id
     * @param array|null $transfers
     *
     * @return mixed
     * @throws \Exception
     */
    public function getTransactionAmount($id, $transfers)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $secretKey = $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey'];

        $stripe = new StripeClient($secretKey);

        $response = $stripe->paymentIntents->retrieve(
            $id,
            [],
            !empty($transfers['method']) && !empty($transfers['accounts']) && $transfers['method'] === 'direct' ?
                ['stripe_account' => array_keys($transfers['accounts'])[0]] : []
        );

        return $response->getLastResponse()->code === 200 ? $response->toArray()['amount'] / 100 : null;
    }

    /**
     * Create a PaymentIntent for Stripe Payment Element / Express Checkout.
     *
     * @param array  $data
     * @param array  $transfers
     *
     * @return array|null
     * @throws ApiErrorException
     */
    public function createPaymentIntent($data, &$transfers)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $stripe = new StripeClient(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $stripeConnectSettings = $stripeSettings['connect'];

        $params = [
            'amount'                    => $data['amount'],
            'currency'                  => $data['currency'],
            'description'               => $data['description'],
            'metadata'                  => $data['metaData'],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'excluded_payment_method_types' => [
                'acss_debit',
                'au_becs_debit',
                'bacs_debit',
                'boleto',
                'customer_balance',
                'konbini',
                'multibanco',
                'oxxo',
                'paynow',
                'promptpay',
                'sepa_debit',
                'us_bank_account',
            ],
        ];

        if (!empty($data['receiptEmail'])) {
            $params['receipt_email'] = $data['receiptEmail'];
        }

        if (!empty($stripeSettings['manualCapture'])) {
            $params['capture_method'] = 'manual';
        }

        if (
            !empty($stripeConnectSettings['enabled']) &&
            !empty($transfers['accounts']) &&
            $stripeConnectSettings['method'] === 'transfer'
        ) {
            $hasTransfer = false;

            foreach ($transfers['accounts'] as $payments) {
                foreach ($payments as $payment) {
                    if (!empty($payment['amount']) && $payment['amount'] > 0) {
                        $hasTransfer = true;
                    }
                }
            }

            if ($hasTransfer) {
                $token = new Token();
                $params['transfer_group'] = $token->getValue();
                $transfers['transferGroup'] = $params['transfer_group'];
            }
        }

        $additionalStripeData = [];

        if (
            !empty($stripeConnectSettings['enabled']) &&
            !empty($transfers['accounts']) &&
            count($transfers['accounts']) === 1 &&
            $stripeConnectSettings['method'] === 'direct'
        ) {
            $platformFee = 0;

            foreach ($transfers['accounts'] as $payments) {
                foreach ($payments as $payment) {
                    $platformFee += $payment['amount'];
                }
            }

            $params['application_fee_amount'] = $platformFee;
            $additionalStripeData = ['stripe_account' => array_keys($transfers['accounts'])[0]];
        }

        $params = apply_filters('amelia_before_stripe_payment_intent', $params);

        $paymentIntent = $stripe->paymentIntents->create($params, $additionalStripeData);

        return [
            'clientSecret'    => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'connectAccountId' => !empty($additionalStripeData['stripe_account']) ? $additionalStripeData['stripe_account'] : null,
        ];
    }

    /**
     * Retrieve a PaymentIntent and create Connect transfers after a successful platform charge.
     *
     * @param string $paymentIntentId
     * @param array  $transfers
     *
     * @return array{status: string, message: string|null}
     * @throws ApiErrorException
     */
    public function completePaymentIntent($paymentIntentId, &$transfers)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $stripe = new StripeClient(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $additionalStripeData = [];

        if (
            !empty($transfers['method']) &&
            !empty($transfers['accounts']) &&
            $transfers['method'] === 'direct' &&
            count($transfers['accounts']) === 1
        ) {
            $additionalStripeData = ['stripe_account' => array_keys($transfers['accounts'])[0]];
        }

        $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId, [], $additionalStripeData);
        $status = $paymentIntent->status;
        $message = $this->getPaymentIntentErrorMessage($paymentIntent);

        if (
            $status === 'succeeded' &&
            !empty($transfers['method']) &&
            !empty($transfers['accounts']) &&
            $transfers['method'] === 'transfer'
        ) {
            $transferFailed = false;

            foreach ($transfers['accounts'] as $accountId => $payments) {
                foreach ($payments as $paymentId => $payment) {
                    if (empty($payment['amount'])) {
                        unset($transfers['accounts'][$accountId][$paymentId]);
                        continue;
                    }

                    if (!empty($payment['transferId'])) {
                        continue;
                    }

                    try {
                        $transferData = [
                            'amount'      => $payment['amount'],
                            'currency'    => $paymentIntent->currency,
                            'destination' => $accountId,
                        ];

                        if (!empty($transfers['transferGroup'])) {
                            $transferData['transfer_group'] = $transfers['transferGroup'];
                        }

                        $transfer = $stripe->transfers->create($transferData);

                        $transfers['accounts'][$accountId][$paymentId]['transferId'] = $transfer->id;
                        $transfers['accounts'][$accountId][$paymentId]['transferStatus'] = 'succeeded';
                    } catch (Exception $e) {
                        error_log('Amelia Stripe: failed to create Connect transfer: ' . $e->getMessage());

                        $transfers['accounts'][$accountId][$paymentId]['transferStatus'] = 'failed';
                        $transfers['accounts'][$accountId][$paymentId]['transferError'] = $e->getMessage();
                        $transferFailed = true;
                    }
                }
            }

            if ($transferFailed) {
                return [
                    'status'  => 'transfer_failed',
                    'message' => $message,
                ];
            }
        }

        return [
            'status'  => $status,
            'message' => $message,
        ];
    }

    /**
     * @param object $paymentIntent
     *
     * @return string|null
     */
    private function getPaymentIntentErrorMessage($paymentIntent)
    {
        if (empty($paymentIntent->last_payment_error)) {
            return null;
        }

        $lastPaymentError = $paymentIntent->last_payment_error;

        if (is_object($lastPaymentError) && !empty($lastPaymentError->message)) {
            return (string)$lastPaymentError->message;
        }

        if (is_array($lastPaymentError) && !empty($lastPaymentError['message'])) {
            return (string)$lastPaymentError['message'];
        }

        return null;
    }

    /**
     * Cancel a PaymentIntent so its client secret can no longer confirm a charge.
     *
     * @param string $paymentIntentId
     * @param array  $transfers
     *
     * @return string|null
     */
    public function cancelPaymentIntent($paymentIntentId, $transfers = [])
    {
        if (empty($paymentIntentId)) {
            return null;
        }

        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $stripe = new StripeClient(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $additionalStripeData = [];

        if (
            !empty($transfers['method']) &&
            !empty($transfers['accounts']) &&
            $transfers['method'] === 'direct' &&
            count($transfers['accounts']) === 1
        ) {
            $additionalStripeData = ['stripe_account' => array_keys($transfers['accounts'])[0]];
        }

        try {
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId, [], $additionalStripeData);

            if (in_array($paymentIntent->status, ['canceled', 'succeeded'], true)) {
                return $paymentIntent->status;
            }

            $paymentIntent = $stripe->paymentIntents->cancel($paymentIntentId, [], $additionalStripeData);

            return $paymentIntent->status;
        } catch (ApiErrorException $e) {
            error_log('Amelia Stripe: failed to cancel PaymentIntent: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param string $providerEmail
     * @param string $providerStripeConnectId
     * @param string $returnUrl
     * @param string $accountType
     *
     * @return array
     * @throws ApiErrorException
     */
    public function onBoardProvider($providerEmail, $providerStripeConnectId, $returnUrl, $accountType)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        Stripe::setApiKey(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        if (!$providerStripeConnectId) {
            $accountData = [
                'type'                   => $accountType,
                'requested_capabilities' => $accountType === 'express'
                    ? $stripeSettings['connect']['capabilities']
                    : [],
            ];

            if ($providerEmail && $accountType === 'express') {
                $accountData['email'] = $providerEmail;
            }

            $account = Account::create($accountData);

            $providerStripeConnectId = $account->id;
        }

        $accountLinks = AccountLink::create(
            [
                'account'     => $providerStripeConnectId,
                'refresh_url' => $returnUrl,
                'return_url'  => $returnUrl,
                'type'        => 'account_onboarding',
            ]
        );

        return [
            'id'  => $providerStripeConnectId,
            'url' => $accountLinks->url,
        ];
    }

    /**
     * @param string $stripeId
     *
     * @return Account
     * @throws Exception
     */
    public function getAccount($stripeId)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        Stripe::setApiKey(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        return Account::retrieve($stripeId);
    }

    /**
     * @throws Exception
     */
    public function getAccounts(): array
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        Stripe::setApiKey(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $accounts = Account::all(['limit' => 100]);

        $result = [];

        foreach ($accounts->toArray()['data'] as $account) {
            $result[] = [
                'id'    => $account['id'],
                'email' => $account['email'],
                'type'  => $this->resolveStripeAccountType($account),
            ];
        }

        return $result;
    }

    private function resolveStripeAccountType(array $account): ?string
    {
        $type = $account['type'] ?? null;

        if ($type && $type !== 'none') {
            return $type;
        }

        $controller = $account['controller'] ?? [];
        $controllerType = $controller['type'] ?? null;

        if ($controllerType === 'account') {
            return 'standard';
        }

        if ($controllerType === 'application') {
            $dashboardType = is_array($controller['stripe_dashboard'] ?? null)
                ? ($controller['stripe_dashboard']['type'] ?? 'none')
                : 'none';

            return ($dashboardType === 'express') ? 'express' : 'custom';
        }

        return null;
    }

    /**
     * @throws ApiErrorException
     */
    public function getExpressAccountLink($id)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $secretKey = $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey'];

        $stripe = new StripeClient($secretKey);

        $response = $stripe->accounts->createLoginLink($id, []);

        return $response->getLastResponse()->code === 200 ? $response->toArray()['url'] : null;
    }

    /**
     * @param array $data
     * @param array $additionalStripeData
     *
     * @return string|null
     * @throws Exception
     */
    private function createCustomer($data, $additionalStripeData)
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        Stripe::setApiKey(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $customerId = $data['customerId'];
        if (!empty($customerId)) {
            try {
                $customer = Customer::retrieve($customerId, $additionalStripeData);
            } catch (Exception $e) {
                $customerId = null;
            }
        }

        if (empty($customerId)) {
            $customer = Customer::create(
                [
                'address' => !empty($data['address']) ? [
                    'city' => $data['address']['address']['city'],
                    'country' => $data['address']['address']['country'],
                    'line1' => $data['address']['address']['line1'],
                    'line2' => $data['address']['address']['line2'],
                    'postal_code' => $data['address']['address']['postal_code'],
                    'state' => $data['address']['address']['state'],
                ] : null,
                'email' => !empty($data['customerData']) ? $data['customerData']['email'] : '',
                'name' => !empty($data['customerData']) ? $data['customerData']['name'] : (!empty($data['address']) ? $data['address']['name'] : ''),
                'phone' => $data['customerData'] ? $data['customerData']['phone'] : ''
                ],
                $additionalStripeData
            );

            if ($customer) {
                $customerId = $customer['id'];
            }
        }

        return $customerId;
    }

    /**
     * @param \AmeliaBooking\Domain\Entity\User\Customer $customer
     * @param array $transfers
     *
     * @return string|null
     * @throws \Exception
     */
    public function getStripeCustomerId($customer, $transfers)
    {
        $stripeConnectSettings = $this->settingsService->getSetting('payments', 'stripe')['connect'];

        $connectToEmployeeStripeAccount =
            $stripeConnectSettings['enabled'] &&
            sizeof($transfers['accounts']) === 1 &&
            $stripeConnectSettings['method'] === 'direct';

        $existingStripeConnects = $customer->getStripeConnect() ?: new Collection();

        /** @var StripeConnect $stripeConnect */
        foreach ($existingStripeConnects->getItems() as $stripeConnect) {
            if (
                (!$connectToEmployeeStripeAccount && !$stripeConnect->getAccountId()) ||
                (
                    $stripeConnect->getAccountId() &&
                    $stripeConnect->getAccountId()->getValue() === array_keys($transfers['accounts'])[0]
                )
            ) {
                return $stripeConnect->getId()->getValue();
            }
        }

        return null;
    }

    /**
     * @param \AmeliaBooking\Domain\Entity\User\Customer $customer
     * @param string $newCustomerId
     * @param array $transfers
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function setNewStripeCustomerId($customer, $newCustomerId, array $transfers)
    {
        $stripeConnectSettings = $this->settingsService->getSetting('payments', 'stripe')['connect'];

        $connectToEmployeeStripeAccount =
            $stripeConnectSettings['enabled'] &&
            sizeof($transfers['accounts']) === 1 &&
            $stripeConnectSettings['method'] === 'direct';

        $existingStripeConnects = $customer->getStripeConnect() ?: new Collection();

        $stripeConnectExists = false;
        /** @var StripeConnect $stripeConnect */
        foreach ($existingStripeConnects->getItems() as $stripeConnect) {
            if (
                (!$connectToEmployeeStripeAccount && !$stripeConnect->getAccountId()) ||
                (
                    $stripeConnect->getAccountId() &&
                    $stripeConnect->getAccountId()->getValue() === array_keys($transfers['accounts'])[0]
                )
            ) {
                $stripeConnectExists = true;
                $stripeConnect->setId(new Name($newCustomerId));
                if ($connectToEmployeeStripeAccount) {
                    $stripeConnect->setAccountId(new Name(array_keys($transfers['accounts'])[0]));
                }
                break;
            }
        }

        if (!$stripeConnectExists) {
            $existingStripeConnects->addItem(
                StripeFactory::create(
                    [
                        'id' => $newCustomerId,
                        'accountId' => $connectToEmployeeStripeAccount ?
                            array_keys($transfers['accounts'])[0] : null
                    ]
                )
            );
        }

        return $existingStripeConnects->toArray();
    }

    /**
     * Validates Stripe API keys by checking their format and making test API calls.
     */
    public function validateKeys(string $publishableKey, string $secretKey, bool $testMode): array
    {
        $expectedPrefix = $testMode ? 'pk_test_' : 'pk_live_';
        $expectedSecretPrefix = $testMode ? 'sk_test_' : 'sk_live_';
        $invalidFields = [];

        if (strpos($publishableKey, $expectedPrefix) !== 0) {
            $invalidFields[] = 'publishableKey';
        }

        if (strpos($secretKey, $expectedSecretPrefix) !== 0) {
            $invalidFields[] = 'secretKey';
        }

        if ($invalidFields !== []) {
            return [
                'valid' => false,
                'message' => 'Invalid Stripe API keys.',
                'invalidFields' => $invalidFields,
            ];
        }

        try {
            $stripe = new StripeClient($publishableKey);

            try {
                $stripe->customers->retrieve('cus_invalid');
            } catch (AuthenticationException $e) {
                return [
                    'valid' => false,
                    'message' => 'Invalid Stripe publishable key.',
                    'invalidFields' => ['publishableKey'],
                ];
            } catch (ApiErrorException $e) {
                if ($e->getStripeCode() !== ErrorObject::CODE_SECRET_KEY_REQUIRED) {
                    return [
                        'valid' => false,
                        'message' => 'Invalid Stripe publishable key.',
                        'invalidFields' => ['publishableKey'],
                    ];
                }
            }

            $stripe = new StripeClient($secretKey);
            $stripe->accounts->retrieve();

            return [
                'valid' => true,
                'message' => 'Stripe keys are valid',
                'invalidFields' => [],
            ];
        } catch (ApiErrorException $e) {
            return [
                'valid' => false,
                'message' => 'Invalid Stripe secret key.',
                'invalidFields' => ['secretKey'],
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Invalid Stripe API keys.',
                'invalidFields' => ['publishableKey', 'secretKey'],
            ];
        }
    }
}
