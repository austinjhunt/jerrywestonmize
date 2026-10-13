<?php

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\CustomField\AbstractCustomFieldApplicationService;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Application\Services\Payment\StripeWebhookRegistrationService;
use AmeliaBooking\Application\Services\Reservation\AbstractReservationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Factory\Cache\CacheFactory;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\Number\Float\Price;
use AmeliaBooking\Domain\ValueObjects\String\BookingType;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Domain\ValueObjects\String\Token;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Repository\User\CustomerRepository;
use AmeliaBooking\Infrastructure\Services\Payment\CurrencyService;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use AmeliaBooking\Infrastructure\WP\HelperService\HelperService;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;

/**
 * Class CreateStripePaymentIntentCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Stripe
 */
class CreateStripePaymentIntentCommandHandler extends CommandHandler
{
    public $mandatoryFields = [
        'bookings',
        'payment',
        'componentProps',
        'returnUrl',
    ];

    /**
     * @param CreateStripePaymentIntentCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handle(CreateStripePaymentIntentCommand $command): CommandResult
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $appointmentData = $this->getAppointmentData($command->getFields(), [PaymentType::STRIPE]);

        $type = $command->getField('type') ?: Entities::APPOINTMENT;

        if (!in_array($type, [Entities::APPOINTMENT, Entities::EVENT, Entities::PACKAGE], true)) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Invalid booking type');
            $result->setData(
                [
                    'message'           => 'Invalid booking type',
                    'paymentSuccessful' => false,
                    'status'            => 400,
                ]
            );

            return $result;
        }

        /** @var AbstractReservationService $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        /** @var CurrencyService $currencyService */
        $currencyService = $this->container->get('infrastructure.payment.currency.service');

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        // safe now - validation guarantees payment is an array and payment.data is an array or absent
        $appointmentData['payment']['data']['createPaymentIntent'] = true;

        $appointmentData = apply_filters('amelia_before_stripe_redirect_filter', $appointmentData);

        do_action('amelia_before_stripe_redirect', $appointmentData);

        $reservationKey = $this->getReservationKey($appointmentData);

        // A reservation this request supersedes: the customer came back to the payment step. Settling it
        // here, before anything is validated, is what stops their own hold from telling them the slot is
        // taken - and it has to happen here rather than being left to the browser's cancel call, which
        // never arrives after a reload and is fired and forgotten on a navigation.
        $previousCache = $this->getSupersededCache($command->getField('cacheName'), $type);

        if ($previousCache) {
            $reused = $this->reuseReservation($previousCache, $reservationKey, $command->getField('returnUrl'));

            if ($reused) {
                return $reused;
            }

            $this->releaseReservation($previousCache, $type);
        }

        // A pre-flight only: it decides whether there is anything to pay for, and rejects a request that
        // cannot be booked before a cache row or a Stripe object is created for it. reCAPTCHA is verified
        // by processRequest below, on the reservation that is actually saved - the token is single use, so
        // it can only be spent once, and Mollie, Barion and Razorpay all order it this way.
        $reservation = $reservationService->getNew(true, true, true);

        $reservationService->processBooking(
            $result,
            $appointmentData,
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
                    'onSitePayment'     => true,
                ]
            );

            return $result;
        }

        $this->stageUploadedCustomFieldFiles($appointmentData);

        $token = new Token();

        /** @var Cache $cache */
        $cache = CacheFactory::create(
            [
                'name' => $token->getValue(),
                // No bookingRequest: the reservation is saved below instead of being replayed after the
                // payment, and a row that carries one could be replayed into a second booking.
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

        // Reserve the slot before the customer can pay for it.
        //
        // This is the order Mollie, Barion and Razorpay already use: the booking is saved with a pending
        // payment, and deleted again if the payment never completes. It is what makes a second customer
        // racing for the same slot fail here, with nothing charged, instead of paying first and being told
        // afterwards that the slot is gone - at which point the money can only be given back.
        // isDeferredPaymentGateway() already lists Stripe-with-createPaymentIntent, so the payment row is
        // written as pending, and processPayment() returns without charging anything.
        $reservation = $reservationService->getNew(true, true, true);

        $result = $reservationService->processRequest($appointmentData, $reservation, true);

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            $cacheRepository->delete($cacheId);

            return $result;
        }

        $transfers = [];

        $paymentAS->setTransfers(
            $appointmentData['payment'],
            $reservation,
            new BookingType($type),
            $transfers,
            false
        );

        $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings(
            $reservation,
            PaymentType::STRIPE
        );

        $identifier = $cacheId . '_' . $token->getValue() . '_' . $type;

        $returnUrl = $this->getStripeCallbackUrl($identifier, $this->getValidReturnUrl($command->getField('returnUrl')));

        $metaData = !empty($additionalInformation['metaData']) && is_array($additionalInformation['metaData'])
            ? $additionalInformation['metaData']
            : [];
        $metaData['amelia_cache_name'] = $identifier;
        $metaData['amelia_type'] = $type;

        // The fallback for a site whose settings save never armed the webhook - a restored
        // database, a moved domain, an endpoint deleted from the Stripe Dashboard. On a site
        // that is already registered this reads the cached settings and returns; it never
        // throws, and is skipped outright on hosts Stripe cannot reach - see ensureRegistered().
        /** @var StripeWebhookRegistrationService $webhookRegistrationService */
        $webhookRegistrationService = $this->container->get('application.stripe.webhook.registration.service');
        $webhookRegistrationService->ensureRegistered();

        $customerData = $this->getStripeCustomerData($reservation, $appointmentData);

        $existingStripeCustomerId = $this->getExistingStripeCustomerId($reservation, $transfers);

        try {
            $intentData = $stripeService->createPaymentIntent(
                [
                    'amount'       => $currencyService->getAmountInFractionalUnit(new Price($paymentAmount)),
                    'currency'     => $this->container->get('domain.settings.service')->getCategorySettings('payments')['currency'],
                    'description'  => $additionalInformation['description'] ?:
                        $reservation->getBookable()->getName()->getValue(),
                    'metaData'     => $metaData,
                    'returnUrl'    => $returnUrl,
                    'receiptEmail' => !empty($customerData['email']) ? $customerData['email'] : '',
                    'customerId'   => $existingStripeCustomerId,
                    'customerData' => $customerData,
                ],
                $transfers
            );
        } catch (Exception $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: failed to create PaymentIntent',
                ['exception' => $e]
            );

            // The slot was reserved for a payment that can never arrive - give it back, along with the
            // customer this request created, which is what rollbackReservation() adds over deleting the
            // reservation on its own.
            $reservationService->rollbackReservation($reservation, true);

            $cacheRepository->delete($cache->getId()->getValue());

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'paymentSuccessful' => false,
                ]
            );

            return $result;
        }

        if (empty($intentData['clientSecret'])) {
            $reservationService->rollbackReservation($reservation, true);

            $cacheRepository->delete($cache->getId()->getValue());

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(['paymentSuccessful' => false]);

            return $result;
        }

        // Links the cache row to the pending payment. That link is what tells the completion and
        // cancellation handlers the booking already exists, so they mark it paid or delete it instead of
        // creating it - the same signal Mollie's notify URL relies on. It rewrites the row's data, so the
        // Stripe keys below have to be applied to what it wrote, not to what the row held before.
        try {
            $result = $paymentAS->updateCache($result, $command->getFields(), $cache, $reservation);
        } catch (Exception $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: failed to link cache to the pending payment',
                ['exception' => $e->getMessage()]
            );

            // reconcilePendingStripePayments() finds abandoned reservations through that link, so an
            // unlinked one would hold its slot with nothing left to release it.
            $stripeService->cancelPaymentIntent($intentData['paymentIntentId'], $transfers);

            $reservationService->rollbackReservation($reservation, true);

            $cacheRepository->delete($cache->getId()->getValue());

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(['paymentSuccessful' => false]);

            return $result;
        }

        $cacheData = json_decode($cache->getData()->getValue(), true);
        $cacheData['type'] = $type;
        $cacheData['stripePaymentIntentId'] = $intentData['paymentIntentId'];
        $cacheData['stripeTransfers'] = $transfers;
        $cacheData['paymentMethod'] = 'stripe';
        $cacheData['reservationKey'] = $reservationKey;

        $this->persistStripeCustomerId(
            $reservation,
            $transfers,
            $existingStripeCustomerId,
            !empty($intentData['stripeCustomerId']) ? $intentData['stripeCustomerId'] : null,
            $cacheData
        );

        $cache->setData(new Json(json_encode($cacheData)));

        $cacheRepository->update(
            $cache->getId()->getValue(),
            $cache
        );

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData(
            [
                'clientSecret'     => $intentData['clientSecret'],
                'paymentIntentId'  => $intentData['paymentIntentId'],
                'cacheName'        => $identifier,
                'returnUrl'        => $returnUrl,
                'connectAccountId' => $intentData['connectAccountId'],
            ]
        );

        return $result;
    }

    private function stageUploadedCustomFieldFiles(&$appointmentData): void
    {
        /** @var AbstractCustomFieldApplicationService $customFieldService */
        $customFieldService = $this->container->get('application.customField.service');

        if (
            isset($appointmentData['bookings'][0]['customFields']) &&
            is_array($appointmentData['bookings'][0]['customFields'])
        ) {
            $appointmentData['uploadedCustomFieldFilesInfo'] = $customFieldService->saveUploadedFiles(
                0,
                $customFieldService->processCustomFields($appointmentData['bookings'][0]['customFields']),
                '/tmp',
                false
            );
        }

        if (
            !empty($appointmentData['bookings'][0]['customer']['customFields']) &&
            is_array($appointmentData['bookings'][0]['customer']['customFields'])
        ) {
            $appointmentData['uploadedCustomerCustomFieldFilesInfo'] = $customFieldService->saveUploadedFiles(
                0,
                $customFieldService->processCustomFields(
                    $appointmentData['bookings'][0]['customer']['customFields']
                ),
                '/tmp',
                false
            );
        }
    }

    private function getStripeCustomerData($reservation, $appointmentData): array
    {
        $customer = $reservation->getCustomer();

        $requestCustomer = !empty($appointmentData['bookings'][0]['customer'])
            ? $appointmentData['bookings'][0]['customer']
            : [];

        if (!$customer) {
            return [
                'name'  => trim(
                    (!empty($requestCustomer['firstName']) ? $requestCustomer['firstName'] : '') . ' ' .
                    (!empty($requestCustomer['lastName']) ? $requestCustomer['lastName'] : '')
                ),
                'email' => !empty($requestCustomer['email']) ? $requestCustomer['email'] : '',
                'phone' => !empty($requestCustomer['phone']) ? $requestCustomer['phone'] : '',
            ];
        }

        $lastName = $customer->getLastName() ? $customer->getLastName()->getValue() : '';

        return [
            'name'  => trim(
                ($customer->getFirstName() ? $customer->getFirstName()->getValue() : '') . ' ' . $lastName
            ),
            'email' => $customer->getEmail() && $customer->getEmail()->getValue()
                ? $customer->getEmail()->getValue()
                : (!empty($requestCustomer['email']) ? $requestCustomer['email'] : ''),
            'phone' => $customer->getPhone() && $customer->getPhone()->getValue()
                ? $customer->getPhone()->getValue()
                : (!empty($requestCustomer['phone']) ? $requestCustomer['phone'] : ''),
        ];
    }

    private function getExistingStripeCustomerId($reservation, $transfers): ?string
    {
        if (!$reservation->getCustomer() || !$reservation->getCustomer()->getId()) {
            return null;
        }

        /** @var CustomerRepository $customerRepository */
        $customerRepository = $this->container->get('domain.users.customers.repository');

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        $customer = $customerRepository->getById($reservation->getCustomer()->getId()->getValue());

        return $customer ? $stripeService->getStripeCustomerId($customer, $transfers + ['accounts' => []]) : null;
    }

    private function persistStripeCustomerId(
        $reservation,
        $transfers,
        $existingStripeCustomerId,
        $stripeCustomerId,
        &$cacheData
    ): void {
        if (
            !$stripeCustomerId ||
            $stripeCustomerId === $existingStripeCustomerId ||
            !$reservation->getCustomer()
        ) {
            return;
        }

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        if (!$reservation->getCustomer()->getId()) {
            if (isset($cacheData['bookingRequest']['bookings'][0]['customer'])) {
                $cacheData['bookingRequest']['bookings'][0]['customer']['stripeConnect'] =
                    $stripeService->setNewStripeCustomerId(
                        $reservation->getCustomer(),
                        $stripeCustomerId,
                        $transfers + ['accounts' => []]
                    );
            }

            return;
        }

        /** @var CustomerRepository $customerRepository */
        $customerRepository = $this->container->get('domain.users.customers.repository');

        $customer = $customerRepository->getById($reservation->getCustomer()->getId()->getValue());

        if (!$customer) {
            return;
        }

        $customerRepository->updateFieldById(
            $customer->getId()->getValue(),
            json_encode(
                $stripeService->setNewStripeCustomerId(
                    $customer,
                    $stripeCustomerId,
                    $transfers + ['accounts' => []]
                )
            ),
            'stripeConnect'
        );
    }

    /**
     * Fingerprints what the reservation is for, so a later request can tell whether it is the same
     * checkout coming back or a changed one.
     *
     * Everything the request carries goes in except the few values that move on their own; a field nobody
     * thought of therefore changes the key and costs a re-reservation, rather than quietly reusing a hold
     * taken for a different slot, price or customer.
     *
     * @param array $appointmentData
     *
     * @return string
     */
    private function getReservationKey($appointmentData): string
    {
        $volatile = ['recaptcha', 'componentProps', 'returnUrl', 'cacheName'];

        foreach ($volatile as $field) {
            unset($appointmentData[$field]);
        }

        $this->sortRecursively($appointmentData);

        return sha1(json_encode($appointmentData));
    }

    /**
     * @param array $data
     *
     * @return void
     */
    private function sortRecursively(&$data): void
    {
        if (!is_array($data)) {
            return;
        }

        ksort($data);

        foreach ($data as &$value) {
            $this->sortRecursively($value);
        }
    }

    /**
     * The reservation a repeated payment step request supersedes, or null when there is nothing usable to
     * supersede. A name that does not resolve is not an error: the row may have been swept, cancelled or
     * already paid, and this request then simply reserves anew.
     *
     * @param string|null $cacheName
     * @param string      $type
     *
     * @return array|null
     */
    private function getSupersededCache($cacheName, $type): ?array
    {
        if (empty($cacheName)) {
            return null;
        }

        $identifier = explode('_', (string)$cacheName);

        if (count($identifier) < 3 || $identifier[2] !== $type) {
            return null;
        }

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        /** @var Cache|null $cache */
        $cache = $cacheRepository->getByIdAndName($identifier[0], $identifier[1]);

        if (!$cache || !$cache->getData()) {
            return null;
        }

        $cacheData = json_decode($cache->getData()->getValue(), true);

        if (!is_array($cacheData) || empty($cacheData['paymentMethod']) || $cacheData['paymentMethod'] !== 'stripe') {
            return null;
        }

        // A paid row is a finished booking, and one without a payment linked to it holds no slot.
        if (!empty($cacheData['status']) || !$cache->getPaymentId() || empty($cacheData['stripePaymentIntentId'])) {
            return null;
        }

        return [
            'cache'     => $cache,
            'cacheData' => $cacheData,
            'name'      => (string)$cacheName,
        ];
    }

    /**
     * Hands back the PaymentIntent the superseded reservation already holds, when this request is the same
     * checkout returning and Stripe still lets that intent be confirmed. Nothing is written and no slot
     * changes hands, which is the point: switching payment methods or reloading the page no longer churns
     * a booking row per visit.
     *
     * @param array  $previous
     * @param string $reservationKey
     * @param string $returnUrl
     *
     * @return CommandResult|null
     */
    private function reuseReservation($previous, $reservationKey, $returnUrl): ?CommandResult
    {
        if (
            empty($previous['cacheData']['reservationKey']) ||
            !hash_equals((string)$previous['cacheData']['reservationKey'], $reservationKey)
        ) {
            return null;
        }

        /** @var StripeService $stripeService */
        $stripeService = $this->container->get('infrastructure.payment.stripe.service');

        $intentId = (string)$previous['cacheData']['stripePaymentIntentId'];
        $transfers = !empty($previous['cacheData']['stripeTransfers']) ? $previous['cacheData']['stripeTransfers'] : [];

        $intent = $stripeService->getConfirmablePaymentIntent($intentId, $transfers);

        if (empty($intent['clientSecret'])) {
            return null;
        }

        $result = new CommandResult();

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData(
            [
                'clientSecret'     => $intent['clientSecret'],
                'paymentIntentId'  => $intentId,
                'cacheName'        => $previous['name'],
                'returnUrl'        => $this->getStripeCallbackUrl(
                    $previous['name'],
                    $this->getValidReturnUrl($returnUrl)
                ),
                'connectAccountId' => $intent['connectAccountId'],
                'reused'           => true,
            ]
        );

        return $result;
    }

    /**
     * Gives the superseded reservation's slot back before this request tries to take it, by the same path
     * the browser's cancel call uses - cancel the PaymentIntent, then delete the booking it was held for.
     *
     * @param array  $previous
     * @param string $type
     *
     * @return void
     */
    private function releaseReservation($previous, $type): void
    {
        try {
            $handler = new CancelStripePaymentIntentCommandHandler($this->container);
            $command = new CancelStripePaymentIntentCommand([]);
            $command->setField('name', $previous['name']);

            $handler->handle($command);
        } catch (Exception $e) {
            // Left to the sweeper: it holds a slot this request is about to be told is taken, which is a
            // clear error for the customer, not a silent double booking.
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: failed to release the superseded reservation',
                ['name' => $previous['name'], 'exception' => $e->getMessage()]
            );
        }
    }

    private function getStripeCallbackUrl($identifier, $returnUrl)
    {
        return HelperService::getPublicCallbackUrl(AMELIA_ACTION_URL) .
            '__payment__stripe__callback&name=' . $identifier . '&returnUrl=' . rawurlencode($returnUrl);
    }

    private function getValidReturnUrl($returnUrl)
    {
        $returnUrl = trim((string)$returnUrl);
        $siteUrl = rtrim(AMELIA_SITE_URL, '/');

        if ($returnUrl === '') {
            return $siteUrl;
        }

        if (strpos($returnUrl, '/') === 0 && strpos($returnUrl, '//') !== 0) {
            return $siteUrl . $returnUrl;
        }

        $returnParts = parse_url($returnUrl);
        $siteParts = parse_url(AMELIA_SITE_URL);

        if (
            !is_array($returnParts) ||
            !is_array($siteParts) ||
            empty($returnParts['scheme']) ||
            empty($returnParts['host']) ||
            empty($siteParts['scheme']) ||
            empty($siteParts['host']) ||
            !in_array(strtolower($returnParts['scheme']), ['http', 'https'], true) ||
            strtolower($returnParts['scheme']) !== strtolower($siteParts['scheme']) ||
            strtolower($returnParts['host']) !== strtolower($siteParts['host']) ||
            (isset($returnParts['port']) ? (int)$returnParts['port'] : null) !==
            (isset($siteParts['port']) ? (int)$siteParts['port'] : null)
        ) {
            return $siteUrl;
        }

        return $returnUrl;
    }
}
