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
use AmeliaVendor\Stripe\Exception\InvalidRequestException;
use AmeliaVendor\Stripe\PaymentMethod;
use AmeliaVendor\Stripe\Stripe;
use AmeliaVendor\Stripe\StripeClient;
use AmeliaVendor\Stripe\Account;
use AmeliaVendor\Stripe\AccountLink;
use AmeliaVendor\Stripe\Transfer;
use AmeliaVendor\Stripe\Event;
use AmeliaVendor\Stripe\PaymentIntent;
use AmeliaVendor\Stripe\Exception\SignatureVerificationException;
use AmeliaVendor\Stripe\Util\ApiVersion;
use AmeliaVendor\Stripe\Webhook;
use AmeliaVendor\Stripe\WebhookEndpoint;
use Exception;
use UnexpectedValueException;

/**
 * Class StripeService
 */
class StripeService extends AbstractPaymentService implements PaymentServiceInterface
{
    /**
     * Events the webhook endpoints subscribe to. Kept in sync with what
     * StripePaymentWebhookCommandHandler acts on - subscribing to more would only
     * produce deliveries that are acknowledged and dropped.
     */
    public const WEBHOOK_EVENTS = [
        'payment_intent.succeeded',
        'payment_intent.amount_capturable_updated',
    ];

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
    public function getPaymentLink($data): array
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

                $method = '';

                if (!empty($data['transfer'])) {
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

                // payment_intent.succeeded carries the PaymentIntent, and Stripe copies neither the
                // link's nor the session's metadata onto it. These keys repeat what the redirect URL
                // tells the callback, so the webhook can record a charge whose customer never came
                // back to that URL - without them it was taken by Stripe and never recorded.
                if (!empty($data['paymentAmeliaId'])) {
                    $paymentLinkData['payment_intent_data'] = array_merge(
                        !empty($paymentLinkData['payment_intent_data']) ? $paymentLinkData['payment_intent_data'] : [],
                        [
                            'metadata' => array_merge(
                                [
                                    'amelia_link_payment_id'     => (string)$data['paymentAmeliaId'],
                                    'amelia_link_charged_amount' => (string)$data['chargedAmount'],
                                ],
                                !empty($data['transfer']) ? [
                                    'amelia_link_account_id'      => (string)$data['transfer']['accountId'],
                                    'amelia_link_transfer_method' => $method,
                                ] : []
                            ),
                        ]
                    );
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
     * The Checkout Session a payment link charge was paid through - what the payment link
     * callback verifies a charge by, and what Stripe appends to the redirect URL.
     *
     * @param string      $paymentIntentId
     * @param string|null $accountId Connected account that owns the charge, for direct charges
     *
     * @return string|null
     * @throws ApiErrorException
     */
    public function getCheckoutSessionIdByPaymentIntent(string $paymentIntentId, ?string $accountId): ?string
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $stripe = new StripeClient(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $sessions = $stripe->checkout->sessions->all(
            ['payment_intent' => $paymentIntentId, 'limit' => 1],
            $accountId ? ['stripe_account' => $accountId] : []
        );

        return !empty($sessions->data[0]->id) ? (string)$sessions->data[0]->id : null;
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
     * Payment method types Stripe can authorize now and capture later.
     *
     * Every other type it offers (bancontact, eps, ideal, p24, mb_way, ...) settles the moment the
     * customer confirms and cannot hold funds at all - Stripe rejects
     * payment_method_options[<type>][capture_method] for them outright.
     */
    private static $authorizablePaymentMethods = [
        'card',
        'link',
        'klarna',
        'paypal',
        'revolut_pay',
        'amazon_pay',
        'affirm',
        'afterpay_clearpay',
        'cashapp',
        'mobilepay',
    ];

    /**
     * Ask Stripe to authorize instead of charge, so a booking that fails validation after the customer
     * confirms releases a hold instead of taking money and giving it back.
     *
     * The hold is requested per payment method type on purpose. A top level capture_method of "manual"
     * makes Stripe drop every method that cannot authorize from the returned payment_method_types, which
     * silently removes bank redirects (bancontact, eps, ideal, p24, mb_way, ...) from the Payment
     * Element. Per type options keep the full method list and still authorize the ones that can.
     *
     * @param array $params
     * @param array $stripeSettings
     *
     * @return array
     */
    public static function addAuthorizationParams($params, $stripeSettings): array
    {
        // "Authorize now, capture later" is the admin's own choice: capture is never automatic then, so
        // restricting the offered methods to the ones that can hold funds is the intended behaviour.
        if (!empty($stripeSettings['manualCapture'])) {
            $params['capture_method'] = 'manual';

            return $params;
        }

        $paymentMethodOptions = !empty($params['payment_method_options']) ? $params['payment_method_options'] : [];

        foreach (self::$authorizablePaymentMethods as $paymentMethod) {
            $paymentMethodOptions[$paymentMethod] = array_merge(
                !empty($paymentMethodOptions[$paymentMethod]) ? $paymentMethodOptions[$paymentMethod] : [],
                ['capture_method' => 'manual']
            );
        }

        $params['payment_method_options'] = $paymentMethodOptions;

        return $params;
    }

    /**
     * Create a PaymentIntent for Stripe Payment Element / Express Checkout.
     *
     * @param array  $data
     * @param array  $transfers
     *
     * @return array
     * @throws ApiErrorException
     */
    public function createPaymentIntent($data, &$transfers): array
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

        $params = self::addAuthorizationParams($params, $stripeSettings);

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

        $stripeCustomerId = null;

        $hasCustomerDetails =
            !empty($data['customerId']) ||
            !empty($data['customerData']['email']) ||
            !empty($data['customerData']['name']);

        if ($hasCustomerDetails) {
            try {
                $stripeCustomerId = $this->createCustomer(
                    [
                        'customerId'   => !empty($data['customerId']) ? $data['customerId'] : null,
                        'customerData' => !empty($data['customerData']) ? $data['customerData'] : null,
                        'address'      => !empty($data['address']) ? $data['address'] : null,
                    ],
                    $additionalStripeData
                );
            } catch (Exception $e) {
                $this->logger->error(
                    'Stripe customer creation failed',
                    [
                        'exception' => $e,
                        'gateway'   => 'stripe',
                    ]
                );
            }
        }

        if ($stripeCustomerId) {
            $params['customer'] = $stripeCustomerId;
        }

        $params = apply_filters('amelia_before_stripe_payment_intent', $params);

        try {
            $paymentIntent = $stripe->paymentIntents->create($params, $additionalStripeData);
        } catch (InvalidRequestException $e) {
            // Stripe rejects the whole request when one of the per type holds above is not valid for the
            // account or API version in use. Checkout matters more than the hold, so retry without them:
            // a booking that then fails validation is refunded instead of released, as it was before.
            // Stripe names the offending parameter (payment_method_options[<type>][capture_method]) in
            // getStripeParam(), which is a stabler signal than the message text; the message is still
            // checked because older API versions do not always set the param.
            $rejectedParam = (string)$e->getStripeParam();

            $rejectedTheHold = strpos($rejectedParam, 'payment_method_options') !== false
                || strpos((string)$e->getMessage(), 'payment_method_options') !== false;

            if (empty($params['payment_method_options']) || !$rejectedTheHold) {
                throw $e;
            }

            $this->logger->error(
                'Stripe rejected per payment method authorization, creating PaymentIntent without it',
                ['message' => $e->getMessage()]
            );

            unset($params['payment_method_options']);

            $paymentIntent = $stripe->paymentIntents->create($params, $additionalStripeData);
        }

        return [
            'clientSecret'    => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'connectAccountId' => !empty($additionalStripeData['stripe_account']) ? $additionalStripeData['stripe_account'] : null,
            'stripeCustomerId' => $stripeCustomerId,
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
                        $this->logger->error(
                            'Amelia Stripe: failed to create Connect transfer',
                            [
                                'exception' => $e,
                                'gateway'   => 'stripe',
                            ]
                        );

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
     * Reads back a PaymentIntent the browser can still confirm, so a customer returning to the payment
     * step continues with the one their reservation already holds.
     *
     * @param string $paymentIntentId
     * @param array  $transfers
     *
     * @return array{status: string|null, clientSecret: string|null, connectAccountId: string|null}
     */
    public function getConfirmablePaymentIntent($paymentIntentId, $transfers = []): array
    {
        $empty = ['status' => null, 'clientSecret' => null, 'connectAccountId' => null];

        if (empty($paymentIntentId)) {
            return $empty;
        }

        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $stripe = new StripeClient(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $connectAccountId = !empty($transfers['method']) && !empty($transfers['accounts']) &&
            $transfers['method'] === 'direct' && count($transfers['accounts']) === 1
                ? array_keys($transfers['accounts'])[0] : null;

        try {
            $paymentIntent = $stripe->paymentIntents->retrieve(
                $paymentIntentId,
                [],
                $connectAccountId ? ['stripe_account' => $connectAccountId] : []
            );
        } catch (ApiErrorException $e) {
            $this->logger->error(
                'Stripe: failed to retrieve PaymentIntent for reuse',
                ['paymentIntentId' => $paymentIntentId, 'message' => $e->getMessage()]
            );

            return $empty;
        }

        // Anything further along than this has money attached to it and must be completed, not handed
        // back to the browser as a fresh payment form.
        if (
            !in_array(
                $paymentIntent->status,
                ['requires_payment_method', 'requires_confirmation', 'requires_action'],
                true
            )
        ) {
            return ['status' => $paymentIntent->status, 'clientSecret' => null, 'connectAccountId' => $connectAccountId];
        }

        return [
            'status'           => $paymentIntent->status,
            'clientSecret'     => $paymentIntent->client_secret,
            'connectAccountId' => $connectAccountId,
        ];
    }

    /**
     * @param string $paymentIntentId
     * @param array  $transfers
     *
     * @return array{status: string, message: string|null}
     * @throws ApiErrorException
     */
    public function capturePaymentIntent($paymentIntentId, &$transfers): array
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $stripe = new StripeClient(
            $stripeSettings['testMode'] === true ? $stripeSettings['testSecretKey'] : $stripeSettings['liveSecretKey']
        );

        $stripe->paymentIntents->capture(
            $paymentIntentId,
            [],
            !empty($transfers['method']) && !empty($transfers['accounts']) &&
            $transfers['method'] === 'direct' && count($transfers['accounts']) === 1
                ? ['stripe_account' => array_keys($transfers['accounts'])[0]] : []
        );

        return $this->completePaymentIntent($paymentIntentId, $transfers);
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
            $this->logger->error(
                'Amelia Stripe: failed to cancel PaymentIntent',
                [
                    'exception' => $e,
                    'gateway'   => 'stripe',
                ]
            );

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

    /**
     * @param string $payload
     * @param string $signature
     *
     * @return Event
     * @throws SignatureVerificationException
     * @throws UnexpectedValueException
     */
    public function constructWebhookEvent($payload, $signature): Event
    {
        // Only the secrets of the currently active mode may sign incoming events, so that
        // events from the other environment are never accepted.
        $secrets = $this->getWebhookSecrets();

        if ($secrets === []) {
            throw new UnexpectedValueException('Stripe webhook secret is not configured');
        }

        // Two endpoints can legitimately deliver here: the account endpoint signs with its
        // own secret, and the Connect endpoint - which is the only one that carries events
        // for direct charges made on a connected account - signs with a different one. The
        // payload does not say which, so both are tried before the delivery is refused. An
        // invalid payload throws UnexpectedValueException on the first attempt and is not
        // retried, since no secret could rescue it.
        $signatureException = null;

        foreach ($secrets as $secret) {
            try {
                return Webhook::constructEvent($payload, $signature, $secret);
            } catch (SignatureVerificationException $e) {
                $signatureException = $e;
            }
        }

        throw $signatureException;
    }

    /**
     * Webhook signing secrets of the active mode: the account endpoint first, since it
     * signs every delivery that is not a connected account's direct charge.
     *
     * @return array
     */
    private function getWebhookSecrets(): array
    {
        $stripeSettings = $this->settingsService->getSetting('payments', 'stripe');

        $prefix = !empty($stripeSettings['testMode']) ? 'test' : 'live';

        $secrets = [];

        foreach (['WebhookSecret', 'ConnectWebhookSecret'] as $suffix) {
            $key = $prefix . $suffix;

            if (!empty($stripeSettings[$key])) {
                $secrets[] = (string)$stripeSettings[$key];
            }
        }

        return $secrets;
    }

    /**
     * The URL Stripe delivers events to.
     *
     * An account endpoint and a Connect endpoint are indistinguishable in the endpoint list
     * response (it carries no `connect` flag), so they are registered under distinct URLs.
     * That keeps "replace the endpoint registered earlier" an exact URL match rather than a
     * guess, and the extra query parameter is ignored while the request is routed.
     *
     * @param bool $connect
     *
     * @return string
     */
    public function getWebhookUrl($connect = false): string
    {
        $url = (defined('AMELIA_ACTION_URL') ? AMELIA_ACTION_URL : '') . '__payment__stripe__webhook';

        if ($connect) {
            $url .= '&amelia_connect=1';
        }

        // Escape hatch for installs where admin_url() is not the address Stripe can reach:
        // admin-ajax behind a reverse proxy, or served under a different public host.
        return function_exists('apply_filters')
            ? (string)apply_filters('amelia_stripe_webhook_url', $url, $connect)
            : $url;
    }

    /**
     * Whether Stripe could reach this site's webhook URL at all.
     *
     * Registration is refused up front for hosts Stripe can never deliver to, so that a
     * local or staging install does not end up with an endpoint that only produces failed
     * deliveries - and with a stored secret that hides the fact that nothing arrives.
     *
     * @param bool $testMode
     *
     * @return string|null Error code, or null when the URL is registrable
     */
    public function getWebhookUrlError($testMode): ?string
    {
        return $this->getUrlWebhookError($this->getWebhookUrl(), $testMode);
    }

    /**
     * @param string $url
     * @param bool   $testMode
     *
     * @return string|null
     */
    private function getUrlWebhookError($url, $testMode): ?string
    {
        $parts = parse_url($url);

        if (empty($parts['host'])) {
            return 'invalidUrl';
        }

        $scheme = !empty($parts['scheme']) ? strtolower($parts['scheme']) : '';

        if (empty($testMode) && $scheme !== 'https') {
            return 'httpsRequired';
        }

        $host = $this->normalizeWebhookHost($parts['host']);

        if ($host === '') {
            return 'invalidUrl';
        }

        return $this->isLocalWebhookHost($host) ? 'localUrl' : null;
    }

    /**
     * Strips what belongs to the URL rather than to the host itself.
     *
     * The brackets around an IPv6 literal keep FILTER_VALIDATE_IP from recognising the
     * address, and the trailing dot of a fully qualified name keeps both the exact match and
     * the suffix match from firing - so "[fd00::1]" and "localhost." would otherwise be
     * handed to Stripe as public hosts.
     *
     * @param string $host
     *
     * @return string
     */
    private function normalizeWebhookHost($host): string
    {
        $host = strtolower(trim((string)$host));

        if (strlen($host) > 1 && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }

        if (substr($host, -1) === '.') {
            $host = substr($host, 0, -1);
        }

        return $host;
    }

    /**
     * @param string $host Already normalized by normalizeWebhookHost()
     *
     * @return bool
     */
    private function isLocalWebhookHost($host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        foreach (['.test', '.local', '.localhost', '.invalid', '.example', '.internal'] as $suffix) {
            if (substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return filter_var($host, FILTER_VALIDATE_IP)
            && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Creates (or replaces) this site's Stripe webhook endpoints and returns their signing
     * secrets, so the customer never has to copy one out of the Stripe Dashboard.
     *
     * Both scopes are registered in one pass: the account endpoint, and the Connect endpoint
     * that carries events for direct charges made on connected accounts. The Connect one is
     * reported as skipped rather than failed when the account is not a Connect platform.
     *
     * @param string $secretKey
     * @param bool   $testMode
     *
     * @return array
     */
    public function registerWebhookEndpoints(string $secretKey, bool $testMode): array
    {
        if (strpos($secretKey, $testMode ? 'sk_test_' : 'sk_live_') !== 0) {
            return ['error' => 'invalidSecretKey'];
        }

        $urlError = $this->getWebhookUrlError($testMode);

        if ($urlError !== null) {
            return ['error' => $urlError];
        }

        try {
            $stripe = new StripeClient($secretKey);

            // Listed once for both scopes. The two endpoints are told apart by their URL, so
            // one pass over the account's endpoints answers for both - and the second scope
            // asking Stripe the same question again is a round trip the customer waits for.
            // The snapshot stays valid across the account pass: whatever it creates or
            // removes carries the account URL, which the Connect pass never looks at.
            $endpointIdsByUrl = $this->getWebhookEndpointIdsByUrl($stripe);

            return [
                'url'        => $this->getWebhookUrl(),
                'connectUrl' => $this->getWebhookUrl(true),
                'account'    => $this->registerWebhookEndpoint($stripe, false, $testMode, $endpointIdsByUrl),
                'connect'    => $this->registerWebhookEndpoint($stripe, true, $testMode, $endpointIdsByUrl),
            ];
        } catch (Exception $e) {
            return [
                'error'      => 'requestFailed',
                'message'    => $e->getMessage(),
                // Tells a key Stripe refuses apart from a Stripe that is briefly unreachable,
                // so the caller knows whether retrying can ever change the outcome.
                'httpStatus' => $e instanceof ApiErrorException ? (int)$e->getHttpStatus() : 0,
            ];
        }
    }

    /**
     * @param StripeClient $stripe
     * @param bool         $connect
     * @param bool         $testMode
     * @param array        $endpointIdsByUrl Endpoint ids of the whole account, keyed by URL
     *
     * @return array
     */
    private function registerWebhookEndpoint($stripe, bool $connect, bool $testMode, array $endpointIdsByUrl): array
    {
        $url = $this->getWebhookUrl($connect);

        // The filter rewrites the two URLs independently, so the one being registered here is
        // the one that has to pass the check - the caller only validated the account URL.
        $urlError = $this->getUrlWebhookError($url, $testMode);

        if ($urlError !== null) {
            return ['status' => 'failed', 'error' => $urlError];
        }

        // The two scopes are told apart by their URL alone. A filter that drops the query
        // marker collapses them into one, and registering the second would then delete the
        // endpoint the first one just created.
        if ($connect && $url === $this->getWebhookUrl()) {
            return ['status' => 'skipped', 'message' => 'connectUrlNotDistinct'];
        }

        // Read from the caller's snapshot rather than asked for again. A blind create is what
        // has to be avoided here - that is how duplicate endpoints accumulate against the
        // per-account endpoint limit - and the snapshot answers that just as well.
        $stale = !empty($endpointIdsByUrl[$url]) ? $endpointIdsByUrl[$url] : [];

        // Counted before anything is deleted, so a cleanup that only partly succeeds is still
        // reported for what it was.
        $staleTotal     = count($stale);
        $staleRemaining = 0;

        try {
            $endpoint = $this->createWebhookEndpoint($stripe, $url, $connect);
        } catch (ApiErrorException $e) {
            // The previous endpoint is removed only once it is established that it blocks
            // the replacement. Deleting first would leave the site with no endpoint at all
            // whenever the create that follows fails, and deleting because the create was
            // rate limited, unauthorized or simply down would take away a working endpoint
            // over something that has nothing to do with it - after which the stored secret
            // and endpoint id still look registered, so nothing tries again.
            if (!$this->isEndpointLimitError($e)) {
                // An account that is not a Connect platform cannot have a Connect endpoint,
                // and that is not a fault of this site's setup - it is the one Connect
                // outcome the caller is meant to pass over rather than report. Every other
                // rejection leaves Connect events unverifiable for a reason worth hearing.
                if ($connect && $this->isConnectUnavailableError($e)) {
                    return ['status' => 'skipped', 'message' => $e->getMessage()];
                }

                return $this->failedEndpoint($e);
            }

            if ($stale === []) {
                return $this->failedEndpoint($e);
            }

            $staleRemaining = $this->deleteWebhookEndpoints($stripe, $stale);
            $stale          = [];

            try {
                $endpoint = $this->createWebhookEndpoint($stripe, $url, $connect);
            } catch (ApiErrorException $retryException) {
                return [
                    'status'         => 'failed',
                    'message'        => $retryException->getMessage(),
                    'httpStatus'     => (int)$retryException->getHttpStatus(),
                    // Room was made and nothing took the place of what was removed, so the
                    // secret and endpoint id the caller has stored describe an endpoint that
                    // no longer exists. Left in place they read as "already registered" and
                    // no later attempt would ever repair this.
                    'deleted'        => $staleTotal - $staleRemaining,
                    'staleRemaining' => $staleRemaining,
                ];
            }
        }

        // Whatever survives here still delivers to this URL, but signed with a secret that
        // is no longer stored, so it can only produce refused deliveries.
        $staleRemaining += $this->deleteWebhookEndpoints($stripe, $stale);

        return [
            'status'         => 'registered',
            'id'             => (string)$endpoint->id,
            'secret'         => !empty($endpoint->secret) ? (string)$endpoint->secret : '',
            'replaced'       => $staleTotal - $staleRemaining,
            'staleRemaining' => $staleRemaining,
        ];
    }

    /**
     * Whether the Connect endpoint was refused because the account is not a Connect platform.
     *
     * Stripe rejects that as an ordinary invalid_request_error without a dedicated error
     * code, so the rejected parameter - or, when the response carries no error object, the
     * message - is what separates it from a Connect create that failed for a reason the site
     * owner has to hear about.
     *
     * @param ApiErrorException $e
     *
     * @return bool
     */
    private function isConnectUnavailableError($e): bool
    {
        if ((int)$e->getHttpStatus() !== 400) {
            return false;
        }

        $error = $e->getError();

        if ($error !== null && !empty($error->param)) {
            return $error->param === 'connect';
        }

        return strpos(strtolower((string)$e->getMessage()), 'connect') !== false;
    }

    /**
     * Whether the create failed because the account has no room for another endpoint, which
     * is the only failure an existing endpoint at this URL can be responsible for.
     *
     * Stripe reports the per-account endpoint limit as an ordinary invalid_request_error
     * without a dedicated error code, so the message is what separates it from every other
     * rejected request.
     *
     * @param ApiErrorException $e
     *
     * @return bool
     */
    private function isEndpointLimitError($e): bool
    {
        if ((int)$e->getHttpStatus() !== 400) {
            return false;
        }

        $message = strtolower((string)$e->getMessage());

        return strpos($message, 'maximum') !== false || strpos($message, 'limit') !== false;
    }

    /**
     * @param StripeClient $stripe
     * @param string       $url
     * @param bool         $connect
     *
     * @return \AmeliaVendor\Stripe\WebhookEndpoint
     * @throws ApiErrorException
     */
    private function createWebhookEndpoint($stripe, string $url, bool $connect): WebhookEndpoint
    {
        $params = [
            'url'            => $url,
            'enabled_events' => self::WEBHOOK_EVENTS,
            // Pinned to the version this SDK deserializes, so the payload shape does not
            // change with the account's default API version.
            'api_version'    => ApiVersion::CURRENT,
            'description'    => 'Amelia booking' . ($connect ? ' (connected accounts)' : ''),
            'metadata'       => [
                'amelia'       => 'webhook',
                'amelia_scope' => $connect ? 'connect' : 'account',
                'amelia_site'  => substr((string)get_site_url(), 0, 500),
            ],
        ];

        if ($connect) {
            $params['connect'] = true;
        }

        return $stripe->webhookEndpoints->create($params);
    }

    /**
     * How a create that Stripe rejected is reported, with the status code the caller needs
     * to tell a rejection an admin has to fix from one that may pass on the next attempt.
     *
     * @param ApiErrorException $e
     *
     * @return array
     */
    private function failedEndpoint($e): array
    {
        return [
            'status'     => 'failed',
            'message'    => $e->getMessage(),
            'httpStatus' => (int)$e->getHttpStatus(),
        ];
    }

    /**
     * Every webhook endpoint on the account, its ids grouped by the URL they deliver to.
     *
     * @param StripeClient $stripe
     *
     * @return array
     * @throws ApiErrorException
     */
    private function getWebhookEndpointIdsByUrl($stripe): array
    {
        $ids = [];

        foreach ($stripe->webhookEndpoints->all(['limit' => 100])->autoPagingIterator() as $endpoint) {
            if (!empty($endpoint->url)) {
                $ids[(string)$endpoint->url][] = (string)$endpoint->id;
            }
        }

        return $ids;
    }

    /**
     * @param StripeClient $stripe
     * @param array        $ids
     *
     * @return int Number of endpoints that could not be deleted
     */
    private function deleteWebhookEndpoints($stripe, array $ids): int
    {
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $stripe->webhookEndpoints->delete($id);
            } catch (Exception $e) {
                $failed++;
            }
        }

        return $failed;
    }
}
