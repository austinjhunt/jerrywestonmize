<?php

namespace AmeliaBooking\Application\Services\Payment;

use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Bookable\BookableApplicationService;
use AmeliaBooking\Application\Services\Bookable\PackageApplicationService;
use AmeliaBooking\Application\Services\Booking\AppointmentApplicationService;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\Booking\EventApplicationService;
use AmeliaBooking\Application\Services\Bookable\AbstractPackageApplicationService;
use AmeliaBooking\Application\Services\Placeholder\PlaceholderService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Common\Exceptions\PaymentValidationException;
use AmeliaBooking\Domain\Entity\Bookable\AbstractBookable;
use AmeliaBooking\Domain\Entity\Bookable\Service\PackageCustomer;
use AmeliaBooking\Domain\Entity\Bookable\Service\Package;
use AmeliaBooking\Domain\Entity\Bookable\Service\PackageCustomerService;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBookingExtra;
use AmeliaBooking\Domain\Entity\Booking\Event\CustomerBookingEventTicket;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Booking\Reservation;
use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Domain\Entity\Coupon\Coupon;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\Location\Location;
use AmeliaBooking\Domain\Entity\Payment\Payment;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\Bookable\Service\PackageCustomerFactory;
use AmeliaBooking\Domain\Factory\Bookable\Service\PackageFactory;
use AmeliaBooking\Domain\Factory\Bookable\Service\ServiceFactory;
use AmeliaBooking\Domain\Factory\Booking\Appointment\CustomerBookingFactory;
use AmeliaBooking\Domain\Factory\Booking\Event\EventFactory;
use AmeliaBooking\Domain\Factory\Coupon\CouponFactory;
use AmeliaBooking\Domain\Factory\Payment\PaymentFactory;
use AmeliaBooking\Domain\Factory\Stripe\StripeFactory;
use AmeliaBooking\Domain\Factory\User\UserFactory;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Payment\PaymentServiceInterface;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\BooleanValueObject;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Domain\ValueObjects\Number\Float\Price;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Domain\ValueObjects\String\BookingType;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Domain\ValueObjects\String\PaymentStatus;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Domain\ValueObjects\String\Token;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageCustomerRepository;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageCustomerServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\Repository\Coupon\CouponRepository;
use AmeliaBooking\Infrastructure\Repository\Location\LocationRepository;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Repository\User\CustomerRepository;
use AmeliaBooking\Infrastructure\Services\Mailchimp\AbstractMailchimpService;
use AmeliaBooking\Infrastructure\Services\Payment\CurrencyService;
use AmeliaBooking\Infrastructure\Services\Payment\RazorpayService;
use AmeliaBooking\Infrastructure\Services\Payment\SquareService;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
use AmeliaBooking\Infrastructure\WP\HelperService\HelperService;
use AmeliaBooking\Infrastructure\WP\Integrations\WooCommerce\WooCommerceService;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Exception;
use Throwable;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Parser\DecimalMoneyParser;

/**
 * Class PaymentApplicationService
 *
 * @package AmeliaBooking\Application\Services\Payment
 */
class PaymentApplicationService
{
    /** @var Container */
    private $container;

    /**
     * PaymentApplicationService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $params
     * @param int   $itemsPerPage
     *
     * @return array
     *
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function getPaymentsData($params, $itemsPerPage = null)
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var AbstractPackageApplicationService $packageApplicationService */
        $packageApplicationService = $this->container->get('application.bookable.package');

        /** @var EventApplicationService $eventAS */
        $eventAS = $this->container->get('application.booking.event.service');

        $isInvoicePage = !empty($params['invoices']) && filter_var($params['invoices'], FILTER_VALIDATE_BOOLEAN);

        $paymentsTypesIds = $paymentRepository->getFilteredIds($params, $itemsPerPage, $isInvoicePage);

        $groupedPaymentsTypesIds = [];

        foreach ($paymentsTypesIds as $id => $type) {
            $groupedPaymentsTypesIds[$type][] = $id;
        }

        if (
            empty($groupedPaymentsTypesIds['appointment']) &&
            empty($groupedPaymentsTypesIds['event']) &&
            empty($groupedPaymentsTypesIds['package'])
        ) {
            return [];
        }

        $paymentsData = [];

        $appointmentsPaymentsData = !empty($groupedPaymentsTypesIds['appointment'])
            ? $paymentRepository->getAppointmentsPaymentsByIds($groupedPaymentsTypesIds['appointment'])
            : [];

        $eventsPaymentsData = !empty($groupedPaymentsTypesIds['event'])
            ? $paymentRepository->getEventsPaymentsByIds($groupedPaymentsTypesIds['event'])
            : [];

        $packagesPaymentsData = !empty($groupedPaymentsTypesIds['package'])
            ? $paymentRepository->getPackagesPaymentsByIds($groupedPaymentsTypesIds['package'])
            : [];

        foreach ($paymentsTypesIds as $id => $type) {
            if (!empty($appointmentsPaymentsData[$id])) {
                $paymentsData[$id] = $appointmentsPaymentsData[$id];
            }

            if (!empty($eventsPaymentsData[$id])) {
                $paymentsData[$id] = $eventsPaymentsData[$id];
            }

            if (!empty($packagesPaymentsData[$id])) {
                $paymentsData[$id] = $packagesPaymentsData[$id];
            }
        }

        $eventBookingIds = [];

        $secondaryPaymentsData = [];

        $secondaryPayments = [];

        if (!isset($params['separateRows'])) {
            foreach ($paymentsData as $paymentData) {
                $secondaryPaymentsData[] = [
                    'paymentId'  => $paymentData['id'],
                    'parentId'   => $paymentData['parentId'],
                    'columnName' => !empty($paymentData['packageCustomerId']) ? 'packageCustomerId' : 'customerBookingId',
                    'columnId'   => $paymentData['packageCustomerId'] ?: $paymentData['customerBookingId'],
                ];
            }

            $secondaryPaymentsIds = $paymentRepository->getSecondaryPaymentIds($secondaryPaymentsData, $isInvoicePage);

            $groupedSecondaryPaymentsTypesIds = [];

            foreach ($secondaryPaymentsIds as $id => $type) {
                $groupedSecondaryPaymentsTypesIds[$type][] = $id;
            }

            if (
                !empty($groupedSecondaryPaymentsTypesIds['appointment']) ||
                !empty($groupedSecondaryPaymentsTypesIds['event']) ||
                !empty($groupedSecondaryPaymentsTypesIds['package'])
            ) {
                $appointmentsSecondaryPaymentsData = !empty($groupedSecondaryPaymentsTypesIds['appointment'])
                    ? $paymentRepository->getAppointmentsPaymentsByIds($groupedSecondaryPaymentsTypesIds['appointment'])
                    : [];

                $eventsSecondaryPaymentsData = !empty($groupedSecondaryPaymentsTypesIds['event'])
                    ? $paymentRepository->getEventsPaymentsByIds($groupedSecondaryPaymentsTypesIds['event'])
                    : [];

                $packagesSecondaryPaymentsData = !empty($groupedSecondaryPaymentsTypesIds['package'])
                    ? $paymentRepository->getPackagesPaymentsByIds($groupedSecondaryPaymentsTypesIds['package'])
                    : [];

                foreach ($secondaryPaymentsIds as $id => $type) {
                    if (!empty($appointmentsSecondaryPaymentsData[$id])) {
                        $secondaryPayments[$id] = $appointmentsSecondaryPaymentsData[$id];
                    }

                    if (!empty($eventsSecondaryPaymentsData[$id])) {
                        $secondaryPayments[$id] = $eventsSecondaryPaymentsData[$id];
                    }

                    if (!empty($packagesSecondaryPaymentsData[$id])) {
                        $secondaryPayments[$id] = $packagesSecondaryPaymentsData[$id];
                    }
                }
            }
        }

        foreach ($paymentsData as &$paymentData) {
            $paymentData['secondaryPayments'] = [];

            foreach ($secondaryPayments as $secondaryPayment) {
                if (
                    $paymentData['id'] !== $secondaryPayment['id'] &&
                    (
                        (
                            $paymentData['packageCustomerId'] &&
                            $secondaryPayment['packageCustomerId'] &&
                            (int)$paymentData['packageCustomerId'] === (int)$secondaryPayment['packageCustomerId']
                        ) ||
                        (
                            $paymentData['customerBookingId'] &&
                            $secondaryPayment['customerBookingId'] &&
                            (int)$paymentData['customerBookingId'] === (int)$secondaryPayment['customerBookingId']
                        ) ||
                        (
                            $isInvoicePage &&
                            ((int)$paymentData['id'] === (int)$secondaryPayment['parentId'] ||
                                (int)$paymentData['parentId'] === (int)$secondaryPayment['id'] ||
                                ($paymentData['parentId'] && (int)$paymentData['parentId'] === (int)$secondaryPayment['parentId']))
                        )
                    )
                ) {
                    $paymentData['secondaryPayments'][] = $secondaryPayment;
                }
            }

            if (empty($paymentData['serviceId']) && empty($paymentData['packageId'])) {
                $eventBookingIds[] = $paymentData['customerBookingId'];
            }
        }

        /** @var Collection $events */
        $events = !empty($groupedPaymentsTypesIds['event']) ? $eventAS->getEventsByCriteria(
            [
                'customerBookingsIds' => $eventBookingIds,
            ],
            [
                'fetchEventsPeriods'   => true,
                'fetchEventsTickets'   => true,
                'fetchEventsProviders' => true,
                'fetchBookings'        => true,
                'fetchBookingsTickets' => true,
            ],
            0
        ) : new Collection();

        $paymentDataValues = array_values($paymentsData);

        $bookingsIds = array_column($paymentDataValues, 'customerBookingId');

        /** @var Event $event */
        foreach ($events->getItems() as $event) {
            /** @var CustomerBooking $booking */
            foreach ($event->getBookings()->getItems() as $booking) {
                $keys = [];

                foreach ($bookingsIds as $key => $bookingId) {
                    if ((int)$bookingId === $booking->getId()->getValue()) {
                        $keys[] = $key;
                    }
                }

                foreach ($keys as $key) {
                    $paymentsData[$paymentDataValues[$key]['id']]['bookingStart'] =
                        $event->getPeriods()->getItem(0)->getPeriodStart()->getValue()->format('Y-m-d H:i:s');

                    /** @var Provider $provider */
                    foreach ($event->getProviders()->getItems() as $provider) {
                        $paymentsData[$paymentDataValues[$key]['id']]['providers'][] = [
                            'id' => $provider->getId()->getValue(),
                            'firstName' => $provider->getFirstName()->getValue(),
                            'lastName' => $provider->getLastName()->getValue(),
                            'picture' => $provider->getPicture() ? $provider->getPicture()->getThumbPath() : null,
                            'fullName' => $provider->getFullName(),
                            'email' => $provider->getEmail()->getValue(),
                        ];
                    }

                    $paymentsData[$paymentDataValues[$key]['id']]['eventId'] = $event->getId()->getValue();

                    $paymentsData[$paymentDataValues[$key]['id']]['name'] = $event->getName()->getValue();

                    if ($event->getCustomPricing() && $event->getCustomPricing()->getValue()) {
                        $price = 0;

                        /** @var CustomerBookingEventTicket $bookingToEventTicket */
                        foreach ($booking->getTicketsBooking()->getItems() as $bookingToEventTicket) {
                            $price += $bookingToEventTicket->getPersons()
                                ? (
                                    $booking->getAggregatedPrice()->getValue()
                                    ? $bookingToEventTicket->getPersons()->getValue()
                                    : 1
                                ) * $bookingToEventTicket->getPrice()->getValue()
                                : 0;
                        }

                        $paymentsData[$paymentDataValues[$key]['id']]['bookedPrice'] = $price;

                        $paymentsData[$paymentDataValues[$key]['id']]['aggregatedPrice'] = 0;
                    }
                }
            }
        }

        if (!empty($groupedPaymentsTypesIds['package'])) {
            $packageApplicationService->setPaymentData($paymentsData);
        }

        foreach ($paymentsData as $index => $value) {
            $paymentsData[$index]['providers'] = !empty($value['providers']) ? array_values($value['providers']) : [];
        }

        foreach ($paymentsData as &$item) {
            $this->addWcFields($item);

            foreach ($item['secondaryPayments'] as &$secondaryPayment) {
                $this->addWcFields($secondaryPayment);
            }
        }

        return $paymentsData;
    }

    /**
     * @param array $item
     *
     * @return void
     */
    public function addWcFields(&$item)
    {
        if (
            !empty($item['wcOrderId']) &&
            WooCommerceService::isEnabled()
        ) {
            $item['wcOrderUrl'] = HelperService::getWooCommerceOrderUrl($item['wcOrderId']);

            $wcOrderItemValues = HelperService::getWooCommerceOrderItemAmountValues($item['wcOrderId']);

            if ($wcOrderItemValues) {
                $key = !empty($item['wcOrderItemId']) && !empty($wcOrderItemValues[$item['wcOrderItemId']]) ?
                    $item['wcOrderItemId'] : array_keys($wcOrderItemValues)[0];

                $item['wcItemCouponValue'] = $wcOrderItemValues[$key]['coupon'];

                $item['wcItemTaxValue'] = $wcOrderItemValues[$key]['tax'];
            }
        }
    }

    /**
     * Payment gateways that can be processed in the "processPayment" function
     *
     * @return array
     */
    public function getAvailablePaymentGateways()
    {
        return [
            PaymentType::ON_SITE,
            PaymentType::PAY_PAL,
            PaymentType::STRIPE,
            PaymentType::SQUARE,
            PaymentType::RAZORPAY,
            PaymentType::WC,
            PaymentType::MOLLIE,
            PaymentType::BARION,
        ];
    }

    /**
     * Validate the payment data received from the front-end request.
     *
     * The request is only allowed to choose *which* gateway is used, and to pass the gateway's own
     * client-side handles (payment intent / method / transaction reference) through in "data".
     *
     * Everything that describes the *state* of the payment - payment status, WooCommerce order status
     * and order ids, deposit and invoice flags, back-end booking flags, amounts, currency - is derived
     * on the server and is therefore dropped here rather than validated key by key. Any new key that
     * the request might send is dropped by default, so this allow list is the boundary for the whole
     * payment object and not just for the keys that are known to be abusable today.
     *
     * @param mixed $paymentData
     * @param array $allowedGateways
     *
     * @return array|null   Null when the payment data from the request is not valid
     */
    public function getValidatedRequestPaymentData($paymentData, $allowedGateways)
    {
        if (!is_array($paymentData)) {
            return null;
        }

        $gateway = isset($paymentData['gateway']) ? $paymentData['gateway'] : null;

        // the gateway must be a known string before it is compared or used - an array, an object or an
        // integer would silently fail every === comparison downstream and then blow up on first use
        if (
            !is_string($gateway) ||
            !in_array($gateway, $allowedGateways, true)
        ) {
            return null;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $validatedPaymentData = [
            'gateway'  => $gateway,
            'currency' => $settingsService->getCategorySettings('payments')['currency'],
        ];

        if (isset($paymentData['data']) && is_array($paymentData['data'])) {
            $validatedPaymentData['data'] = $paymentData['data'];
        }

        return $validatedPaymentData;
    }

    /**
     * Replace the payment object of a booking request with its validated, server-derived equivalent.
     *
     * Every request-sourced booking flow must call this before the request reaches processBooking /
     * processRequest. Data that Amelia itself builds (the WooCommerce order flow, back-end handlers)
     * must not be passed through here - it legitimately carries the server-side payment state that
     * this method strips.
     *
     * @param array $data            Booking request data, payment object is replaced in place
     * @param array $allowedGateways Gateways this endpoint accepts; the request is refused unless it names
     *                               one of them, so callers must pass the allow list explicitly
     *
     * @return void
     * @throws PaymentValidationException When the payment data from the request is not valid
     */
    public function validateRequestPaymentData(&$data, $allowedGateways): void
    {
        $paymentData = $this->getValidatedRequestPaymentData($data['payment'], $allowedGateways);

        // A malformed payment object is rejected the same way a missing one is: callers that write into
        if ($paymentData === null) {
            throw new PaymentValidationException();
        }

        $data['payment'] = $paymentData;

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get(
            !empty($data['type']) ? $data['type'] : Entities::APPOINTMENT
        );

        // the entities in a cart can each allow different payment methods, so a cart is bound to the global
        // settings only - the same methods the booking form offers for it (see availablePayments in v3)
        /** @var AbstractBookable|null $bookable */
        $bookable = null;

        if (!$this->isCartRequest($data)) {
            try {
                $bookable = $reservationService->getBookableEntity(
                    [
                        'serviceId' => !empty($data['serviceId']) ? $data['serviceId'] : null,
                        'packageId' => !empty($data['packageId']) ? $data['packageId'] : null,
                        'eventId'   => !empty($data['eventId']) ? $data['eventId'] : null,
                    ]
                );
            } catch (Throwable $e) {
                // every entity type throws a different exception when its id is missing or unknown, and none
                // of them says which payment methods the request may use, so the request is refused here
                // instead of being let through - the booking that follows fails on the very same data
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                    'Amelia: booked entity could not be looked up while validating payment data',
                    ['exception' => $e->getMessage()]
                );

                throw new PaymentValidationException();
            }
        }

        $isAllowedPaymentMethod = in_array($paymentData['gateway'], $this->getAvailablePayments($bookable), true);

        // if the payment method is not onSite, and this method is disabled, throw an exception
        if (
            $paymentData['gateway'] !== PaymentType::ON_SITE &&
            !$isAllowedPaymentMethod
        ) {
            throw new PaymentValidationException();
        }

        // if the payment method is onSite, and this method is disabled, inspect amount of the reservation
        if (
            $paymentData['gateway'] === PaymentType::ON_SITE &&
            !$isAllowedPaymentMethod
        ) {
            /** @var AbstractUser|null $user */
            $user = $this->container->get('logged.in.user');

            // back-end bookings are not bound to the payment methods that are offered to customers,
            // everyone else - customers and visitors that are not logged in at all - is
            if (!($user instanceof AbstractUser) || $user->getType() === Entities::CUSTOMER) {
                try {
                    /** @var Reservation $reservation */
                    $reservation = $reservationService->getNew(true, false, false);

                    $reservationService->processBooking(new CommandResult(), $data, $reservation, false);

                    $paymentAmount = (float)$reservationService->getReservationPaymentAmount($reservation);
                } catch (Throwable $e) {
                    // a reservation that cannot be built or priced from this request cannot be shown to have
                    // nothing to pay either, so it is refused instead of being let through unpriced - the
                    // booking that follows would have failed on the very same data
                    throw new PaymentValidationException();
                }

                // on site is disabled, so only a reservation that has nothing to pay may be recorded with it
                if ($paymentAmount > 0) {
                    throw new PaymentValidationException();
                }
            }
        }
    }

    /**
     * @param array $data
     *
     * @return bool True when the request books a cart
     */
    private function isCartRequest($data)
    {
        /* SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        if (
            (!empty($data['type']) ? $data['type'] : Entities::APPOINTMENT) !== Entities::APPOINTMENT ||
            !$settingsService->isFeatureEnabled('cart') ||
            empty($data['recurring'])
        ) {
            return false;
        }

        return isset($data['isCart']) && is_string($data['isCart'])
            ? filter_var($data['isCart'], FILTER_VALIDATE_BOOLEAN)
            : !empty($data['isCart']);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param CommandResult $result
     * @param array         $paymentData
     * @param Reservation   $reservation
     * @param BookingType   $bookingType
     * @param string        $paymentTransactionId
     * @param array         $transfers
     *
     * @return boolean
     *
     * @throws Exception
     */
    public function processPayment($result, $paymentData, $reservation, $bookingType, &$paymentTransactionId, &$transfers)
    {
        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($bookingType->getValue());

        $paymentAmount = $reservationService->getReservationPaymentAmount($reservation);

        $paymentData = apply_filters('amelia_before_payment_processed_filter', $paymentData, $reservation->getReservation()->toArray());

        do_action('amelia_before_payment_processed', $paymentData, $reservation->getReservation()->toArray());

        if (
            !$paymentAmount &&
            (
                $paymentData['gateway'] === 'stripe' ||
                $paymentData['gateway'] === 'payPal' ||
                $paymentData['gateway'] === 'mollie' ||
                $paymentData['gateway'] === 'razorpay' ||
                $paymentData['gateway'] === 'square' ||
                $paymentData['gateway'] === 'barion'
            )
        ) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
            $result->setData(
                [
                    'paymentSuccessful' => false,
                    'onSitePayment'     => true
                ]
            );

            return false;
        }

        switch ($paymentData['gateway']) {
            case ('payPal'):
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.payPal.service');

                $response = $paymentService->complete(
                    [
                        'transactionReference' => $paymentData['data']['transactionReference'],
                        'PayerID'              => $paymentData['data']['PayerId'],
                        'amount'               => $paymentAmount,
                    ]
                );

                if ($response->isSuccessful()) {
                    $paymentTransactionId = $response->getData()['id'];
                } else {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                    $result->setData(
                        [
                            'paymentSuccessful' => false,
                            'message'           => $response->getMessage(),
                        ]
                    );

                    return false;
                }

                return true;

            case ('stripe'):
                /** @var StripeService $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.stripe.service');

                $this->setTransfers(
                    $paymentData,
                    $reservation,
                    $bookingType,
                    $transfers,
                    true
                );

                if (!empty($paymentData['data']['createPaymentIntent'])) {
                    return true;
                }

                /** @var CurrencyService $currencyService */
                $currencyService = $this->container->get('infrastructure.payment.currency.service');

                $additionalInformation = $this->getBookingInformationForPaymentSettings(
                    $reservation,
                    PaymentType::STRIPE
                );

                /** @var CustomerRepository $customerRepository */
                $customerRepository = $this->container->get('domain.users.customers.repository');

                $customer = null;

                $stripeCustomerId = null;

                if ($reservation->getCustomer() && $reservation->getCustomer()->getId()) {
                    /** @var \AmeliaBooking\Domain\Entity\User\Customer $customer */
                    $customer = $customerRepository->getById($reservation->getCustomer()->getId()->getValue());

                    $stripeCustomerId = $paymentService->getStripeCustomerId($customer, $transfers);
                }


                try {
                    $response = $paymentService->execute(
                        [
                            'paymentMethodId' => !empty($paymentData['data']['paymentMethodId']) ?
                                $paymentData['data']['paymentMethodId'] : null,
                            'paymentIntentId' => !empty($paymentData['data']['paymentIntentId']) ?
                                $paymentData['data']['paymentIntentId'] : null,
                            'amount'          => $currencyService->getAmountInFractionalUnit(new Price($paymentAmount)),
                            'metaData'        => $additionalInformation['metaData'],
                            'description'     => $additionalInformation['description'],
                            'address' => !empty($paymentData['data']['address']) ?
                                $paymentData['data']['address'] : null,
                            'customerId' => $stripeCustomerId,
                            'customerData' => $customer ? [
                                'name' =>
                                    $customer->getFirstName()->getValue() . ($customer->getLastName() ? (' ' . $customer->getLastName()->getValue()) : ''),
                                'email' => $customer->getEmail() ? $customer->getEmail()->getValue() : '',
                                'phone' => $customer->getPhone() ? $customer->getPhone()->getValue() : ''
                            ] : null
                        ],
                        $transfers
                    );
                } catch (Exception $e) {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                    $result->setData(
                        [
                            'paymentSuccessful' => false,
                            'message'           => $e->getMessage(),
                        ]
                    );

                    return false;
                }

                if (isset($response['requiresAction'])) {
                    $result->setResult(CommandResult::RESULT_SUCCESS);
                    $result->setData(
                        [
                            'paymentIntentClientSecret' => $response['paymentIntentClientSecret'],
                            'requiresAction'            => $response['requiresAction']
                        ]
                    );

                    return false;
                }

                if (empty($response['paymentSuccessful'])) {
                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                    $result->setData(
                        [
                            'paymentSuccessful' => false
                        ]
                    );

                    return false;
                }

                // Record the captured intent before any post-capture side effects so a later throwable
                // does not roll the booking back after Stripe has already taken payment.
                $paymentTransactionId = $response['paymentIntentId'];

                if (!empty($response['customerId']) && ($stripeCustomerId === null || $response['customerId'] !== $stripeCustomerId)) {
                    $newStripeConnectArray = $paymentService->setNewStripeCustomerId($customer, $response['customerId'], $transfers);

                    $customerRepository->updateFieldById(
                        $reservation->getCustomer()->getId()->getValue(),
                        json_encode($newStripeConnectArray),
                        'stripeConnect'
                    );
                }

                return true;

            case ('square'):
                /** @var SettingsService $settingsService */
                $settingsService = $this->container->get('domain.settings.service');

                $squareSettings = $settingsService->getSetting('payments', 'square');

                /** @var SquareService $squareService */
                $squareService = $this->container->get('infrastructure.payment.square.service');

                if ($squareSettings['enabled']) {
                    try {
                        $paymentResponse = $this->createSquarePaymentIntent($paymentData, $paymentAmount, $reservation);
                        if ($paymentResponse && $paymentResponse->isError()) {
                            $errorMessage = $squareService->getErrorMessage($paymentResponse);
                            $result->setResult(CommandResult::RESULT_ERROR);
                            $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                            $result->setData(
                                [
                                    'paymentSuccessful' => false,
                                    'emailRequired' => $errorMessage === 'buyer_email_address  is not valid',
                                ]
                            );

                            return false;
                        }
                        if ($paymentResponse && $paymentResponse->getResult()->getPayment() !== null) {
                            $paymentTransactionId = $paymentResponse->getResult()->getPayment()->getId();

                            return true;
                        }
                    } catch (Exception $e) {
                        $result->setResult(CommandResult::RESULT_ERROR);
                        $result->setMessage(FrontendStrings::getCommonStrings()['payment_error']);
                        $result->setData(
                            [
                                'paymentSuccessful' => false
                            ]
                        );
                    }
                }
                return false;

            case ('onSite'):
                // whether this reservation may be paid on site is decided by validateRequestPaymentData,
                // which every request-sourced booking flow passes through before it reaches this point
                return true;

            case ('wc'):
            case ('mollie'):
            case ('barion'):
            case ('razorpay'):
                return true;
        }

        return false;
    }

    /**
     * The payment methods a reservation may be paid with, as a list of gateway names.
     *
     * A method is available for an entity only when it is enabled globally *and* for that entity, so
     * an entity can never widen what the global settings offer, and an entity that disables every
     * globally enabled method is left with none - it is not handed the global ones back.
     *
     * An entity records only the gateways it was asked about, so a gateway that is absent from its
     * settings is not a disabled one - it inherits the global setting. An entity without payment
     * settings of its own therefore ends up with exactly the globally enabled methods. This is the
     * same reading the booking form applies when it decides which methods to offer (see
     * availablePayments in v3), and the two have to agree: a method the form offers and this method
     * rejects fails the booking with a payment error.
     *
     * @param AbstractBookable|null $bookable Null when the reservation books more than one entity at once (cart)
     *
     * @return array   Gateway names, numerically indexed
     */
    public function getAvailablePayments($bookable = null)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $generalPayments = $settingsService->getCategorySettings('payments');

        // on site is configured as a plain boolean, every other gateway as ['enabled' => bool]
        $gatewayPayments = array_diff($this->getAvailablePaymentGateways(), [PaymentType::ON_SITE]);

        $availableGeneralMethods = [];

        if (!empty($generalPayments[PaymentType::ON_SITE])) {
            $availableGeneralMethods[] = PaymentType::ON_SITE;
        }

        foreach ($gatewayPayments as $payment) {
            if (!empty($generalPayments[$payment]['enabled'])) {
                $availableGeneralMethods[] = $payment;
            }
        }

        if ($bookable !== null && $bookable->getSettings()) {
            $bookableSettings = json_decode($bookable->getSettings()->getValue(), true);

            $bookablePayments = !empty($bookableSettings['payments']) && is_array($bookableSettings['payments'])
                ? $bookableSettings['payments']
                : [];

            $availableBookableMethods = [];

            if (
                in_array(PaymentType::ON_SITE, $availableGeneralMethods, true) &&
                (
                    !array_key_exists(PaymentType::ON_SITE, $bookablePayments) ||
                    !empty($bookablePayments[PaymentType::ON_SITE])
                )
            ) {
                $availableBookableMethods[] = PaymentType::ON_SITE;
            }

            foreach ($gatewayPayments as $payment) {
                if (!in_array($payment, $availableGeneralMethods, true)) {
                    continue;
                }

                if (!array_key_exists($payment, $bookablePayments)) {
                    $availableBookableMethods[] = $payment;

                    continue;
                }

                $gatewaySettings = is_array($bookablePayments[$payment]) ? $bookablePayments[$payment] : [];

                // WooCommerce keeps its product id next to the flag, and an entity that carries the id
                // without the flag is read as enabled - again the reading the booking form applies
                $isEnabled = $payment === PaymentType::WC && !array_key_exists('enabled', $gatewaySettings)
                    ? true
                    : !empty($gatewaySettings['enabled']);

                if ($isEnabled) {
                    $availableBookableMethods[] = $payment;
                }
            }

            // an entity that carries no payment settings has kept every globally enabled method above,
            // so an empty list here means the entity really did disable them all - as the booking form
            // reads it too, which then offers no payment method at all
            return $availableBookableMethods;
        }

        return $availableGeneralMethods;
    }

    /**
     * @param Reservation|array $reservation
     * @param string $paymentType
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getBookingInformationForPaymentSettings($reservation, $paymentType, $bookingIndex = null)
    {
        $reservationType = $reservation instanceof Reservation ? $reservation->getReservation()->getType()->getValue() : $reservation['type'];

        /** @var PlaceholderService $placeholderService */
        $placeholderService = $this->container->get("application.placeholder.{$reservationType}.service");

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $paymentsSettings = $settingsService->getSetting('payments', $paymentType);

        $setDescription = !empty($paymentsSettings['description']);

        $setName = !empty($paymentsSettings['name']);

        $setMetaData = !empty($paymentsSettings['metaData']);

        $placeholderData = [];

        if ($setDescription || $setMetaData || $setName) {
            $reservationData = $reservation;

            $customer = null;

            if ($reservation instanceof Reservation) {
                $reservationData = $reservation->getReservation()->toArray();

                $reservationData['bookings'] = $reservation->getBooking() ? [
                    $reservation->getBooking()->getId() ?
                        $reservation->getBooking()->getId()->getValue() : 0 => $reservation->getBooking()->toArray()
                ] : [];

                $reservationData['customer'] = $reservation->getCustomer()->toArray();
                $customer  = $reservation->getCustomer();
                $bookingId = $reservation->getBooking() && $reservation->getBooking()->getId() ? $reservation->getBooking()->getId()->getValue() : 0;
            } else {
                if (!empty($reservation['bookings'][$bookingIndex]['customer'])) {
                    $customerArray = $reservation['bookings'][$bookingIndex]['customer'];
                    if (!empty($customerArray['birthday']) && is_array($customerArray['birthday'])) {
                        $customerArray['birthday'] = DateTimeService::getCustomDateTimeObject($customerArray['birthday']['date']);
                    }
                    $customer = UserFactory::create($customerArray);
                } elseif (!empty($reservation['bookings'][$bookingIndex]['info'])) {
                    $customerInfo = json_decode($reservation['bookings'][$bookingIndex]['info'], true);

                    if ($customerInfo !== null) {
                        $customer = UserFactory::create(array_merge($customerInfo, ['email' => null]));
                    }
                }

                $bookingId = $bookingIndex;
            }

            try {
                $placeholderData = $placeholderService->getPlaceholdersData(
                    $reservationData,
                    $bookingId,
                    null,
                    $customer
                );
            } catch (Exception $e) {
            }
        }

        $metaData = [];

        $description = '';
        $name        = '';

        if ($placeholderData && $setDescription) {
            $description = $placeholderService->applyPlaceholders(
                $paymentsSettings['description'][$reservationType],
                $placeholderData
            );
        }

        if ($placeholderData && $setName) {
            $name = $placeholderService->applyPlaceholders(
                $paymentsSettings['name'][$reservationType],
                $placeholderData
            );
        }

        if ($placeholderData && $setMetaData) {
            foreach ((array)$paymentsSettings['metaData'][$reservationType] as $metaDataKay => $metaDataValue) {
                $metaData[$metaDataKay] = $placeholderService->applyPlaceholders(
                    $metaDataValue,
                    $placeholderData
                );
            }
        }

        return [
            'description' => $description,
            'metaData'    => $metaData,
            'name'        => $name
        ];
    }

    /**
     * @param Payment $payment
     *
     * @return boolean
     *
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function delete($payment)
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        /** @var Collection $followingPayments */
        $followingPayments = $paymentRepository->getByEntityId(
            $payment->getId()->getValue(),
            'parentId'
        );

        /** @var Collection $caches */
        $caches = $cacheRepository->getByEntityId(
            $payment->getId()->getValue(),
            'paymentId'
        );

        $followingPaymentId = $followingPayments->length() ?
            min(array_map('intval', array_column($followingPayments->toArray(), 'id'))) : null;

        /** @var Cache $cache */
        foreach ($caches->getItems() as $cache) {
            if ($followingPaymentId) {
                $cacheRepository->updateByEntityId(
                    $payment->getId()->getValue(),
                    $followingPaymentId,
                    'paymentId'
                );
            } else {
                $cacheRepository->updateFieldById(
                    $cache->getId()->getValue(),
                    null,
                    'paymentId'
                );
            }
        }

        $paymentRepository->updateByEntityId(
            $payment->getId()->getValue(),
            $followingPaymentId,
            'parentId'
        );

        $paymentRepository->updateFieldById(
            $followingPaymentId,
            null,
            'parentId'
        );

        if (!$paymentRepository->delete($payment->getId()->getValue())) {
            return false;
        }

        return true;
    }

    /**
     * @param CustomerBooking $booking
     *
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     */
    public function updateBookingPaymentDate($booking, $date)
    {
        foreach ($booking->getPayments()->getItems() as $payment) {
            if (
                $payment->getGateway()->getName()->getValue() === PaymentType::ON_SITE &&
                (!$payment->getPackageCustomerId() || !$payment->getPackageCustomerId()->getValue())
            ) {
                /** @var PaymentRepository $paymentRepository */
                $paymentRepository = $this->container->get('domain.payment.repository');

                $paymentRepository->updateFieldById(
                    $payment->getId()->getValue(),
                    $date,
                    'dateTime'
                );
            }
        }
    }

    /**
     * @param array $originalPayment
     * @param int $amount
     * @param string $type
     *
     * @return Payment
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function insertPaymentFromLink($originalPayment, $amount, $type)
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        $linkPayment = PaymentFactory::create($originalPayment);
        $linkPayment->setAmount(new Price($amount));
        $linkPayment->setId(null);
        $linkPayment->setDateTime(null);
        $linkPayment->setEntity(new Name($type));
        $linkPayment->setActionsCompleted(new BooleanValueObject(true));
        if ($type === Entities::PACKAGE) {
            $linkPayment->setCustomerBookingId(null);
            $linkPayment->setPackageCustomerId(new Id($originalPayment['packageCustomerId']));
        }
        $linkPaymentId = $paymentRepository->add($linkPayment);
        $linkPayment->setId(new Id($linkPaymentId));
        return $linkPayment;
    }

    /**
     * @param array $data
     * @param int $index
     * @param string|null $paymentMethod
     * @param string $customRedirectUrl
     * @param bool $directLink
     *
     * @return array|null
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function createPaymentLink(
        $data,
        $index = null,
        $recurringKey = null,
        $paymentMethod = null,
        $customRedirectUrl = null,
        $directLink = false
    ) {
        try {
            /** @var PaymentApplicationService $paymentAS */
            $paymentAS = $this->container->get('application.payment.service');
            /** @var SettingsService $settingsService */
            $settingsService = $this->container->get('domain.settings.service');
            /** @var PaymentRepository $paymentRepository */
            $paymentRepository = $this->container->get('domain.payment.repository');
            /** @var CurrencyService $currencyService */
            $currencyService = $this->container->get('infrastructure.payment.currency.service');

            $paymentLinks = [];

            $type        = $data['type'];
            $reservation = $data[$type];
            $booking     = $recurringKey !== null ? $data['recurring'][$recurringKey]['bookings'][$index] : $data['booking'];

            $reservation['bookings'][$index]['customer'] = $data['customer'];
            $customer = $data['customer'] ?: ($data['booking'] ? $data['booking']['customer'] : null);
            $reservation['packageCustomerId'] = !empty($data['packageCustomerId']) ? $data['packageCustomerId'] : null;

            $entitySettings =
                !empty($data['bookable']) &&
                !empty($data['bookable']['settings']) &&
                json_decode($data['bookable']['settings'], true) ?
                    json_decode($data['bookable']['settings'], true) :
                    null;

            $paymentLinksSettings =
                !empty($entitySettings) &&
                !empty($entitySettings['payments']['paymentLinks']['enabled'])
                    ? $entitySettings['payments']['paymentLinks']
                    : null;
            $paymentLinksEnabled  =
                $paymentLinksSettings ?
                    !empty($paymentLinksSettings['enabled']) :
                    $settingsService->getSetting('payments', 'paymentLinks')['enabled'];
            if (
                !$paymentLinksEnabled ||
                ($booking && (in_array($booking['status'], [BookingStatus::CANCELED, BookingStatus::REJECTED, BookingStatus::NO_SHOW])))
            ) {
                return $paymentLinks;
            }

            $redirectUrl = $paymentLinksSettings && !empty($paymentLinksSettings['redirectUrl']) ? $paymentLinksSettings['redirectUrl'] :
                $settingsService->getSetting('payments', 'paymentLinks')['redirectUrl'];

            $redirectUrl = empty($redirectUrl) ? AMELIA_SITE_URL : $redirectUrl;

            $customerPanelUrl = $settingsService->getSetting('roles', 'customerCabinet')['pageUrl'];
            $redirectUrl      = $paymentMethod && !empty($data['fromPanel']) ? $customerPanelUrl : $redirectUrl;
            $redirectUrl      = $customRedirectUrl ?: $redirectUrl;

            $totalPrice = $this->calculateAppointmentPrice($booking, $type, $reservation);

            $oldPaymentId = $recurringKey !== null ? $data['recurring'][$recurringKey]['bookings'][$index]['payments'][0]['id'] : $data['paymentId'];

            if (!empty($data['packageCustomerId'])) {
                $payments = $paymentRepository->getByEntityId($data['packageCustomerId'], 'packageCustomerId');
            } else {
                $payments = $paymentRepository->getByEntityId($booking['id'], 'customerBookingId');
            }

            if (!$payments  || $payments->length() === 0 || empty($oldPaymentId)) {
                return null;
            }

            $payments   = $payments->toArray();
            $allAmounts = 0;
            foreach ($payments as $payment) {
                if ($payment['status'] !== 'refunded' && $payment['status'] !== 'pending') {
                    $allAmounts += $payment['amount'];
                }
            }
            $allWCTaxes = array_sum(array_filter(array_column($payments, 'wcItemTaxValue')));

            $allWCCoupons = array_sum(array_filter(array_column($payments, 'wcItemCouponValue')));

            $amountWithoutTax = round($allAmounts + $allWCCoupons - $allWCTaxes, 2);

            if ($amountWithoutTax >= $totalPrice || $totalPrice === 0.0) {
                return null;
            }

            $oldPaymentKey = array_search($oldPaymentId, array_column($payments, 'id'));
            if ($oldPaymentKey === false) {
                return null;
            }
            $oldPayment = $payments[$oldPaymentKey];

            $amount = $totalPrice - $amountWithoutTax;

            $callbackLink =
                AMELIA_ACTION_URL . '/payments/callback&fromLink=true&paymentAmeliaId=' .
                $oldPaymentId . '&chargedAmount=' . $amount . '&fromPanel=' . (!empty($data['fromPanel']));

            $paymentSettings = $settingsService->getCategorySettings('payments');

            $methods = $paymentMethod ?: [
                'payPal'   =>
                    !empty($entitySettings) && !empty($entitySettings['payments']['payPal']) ?
                        ($entitySettings['payments']['payPal']['enabled'] && $paymentSettings['payPal']['enabled']) :
                        $paymentSettings['payPal']['enabled'],
                'stripe'   =>
                    !empty($entitySettings) && !empty($entitySettings['payments']['stripe']) ?
                        ($entitySettings['payments']['stripe']['enabled'] && $paymentSettings['stripe']['enabled']) :
                        $paymentSettings['stripe']['enabled'],
                'razorpay' =>
                    !empty($entitySettings) && !empty($entitySettings['payments']['razorpay']) ?
                        ($entitySettings['payments']['razorpay']['enabled'] && $paymentSettings['razorpay']['enabled']) :
                        $paymentSettings['razorpay']['enabled'],
                'mollie'   =>
                    !empty($entitySettings) && !empty($entitySettings['payments']['mollie']) ?
                        ($entitySettings['payments']['mollie']['enabled'] && $paymentSettings['mollie']['enabled']) :
                        $paymentSettings['mollie']['enabled'],
                'wc'       =>
                    !empty($entitySettings) && !empty($entitySettings['payments']['wc']) && array_key_exists('enabled', $entitySettings['payments']['wc']) ?
                        ($entitySettings['payments']['wc']['enabled'] && $paymentSettings['wc']['enabled']) :
                        $paymentSettings['wc']['enabled'],
                'square'   =>
                    !empty($entitySettings) && !empty($entitySettings['payments']['square']) ?
                        ($entitySettings['payments']['square']['enabled'] && $paymentSettings['square']['enabled']) :
                        $paymentSettings['square']['enabled'],
                'barion'   =>
                        !empty($entitySettings) && !empty($entitySettings['payments']['barion']) ?
                            ($entitySettings['payments']['barion']['enabled'] && $paymentSettings['barion']['enabled']) :
                            $paymentSettings['barion']['enabled'],
            ];

            if (!$directLink) {
                /** @var CustomerBookingRepository $bookingRepository */
                $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

                /** @var PackageCustomerRepository $packageCustomerRepository */
                $packageCustomerRepository = $this->container->get('domain.bookable.packageCustomer.repository');

                $token = $type === Entities::PACKAGE
                    ? $packageCustomerRepository->getToken($data['packageCustomerId'])['token']
                    : $bookingRepository->getToken($booking['id'])['token'];

                $basePaymentLink = AMELIA_ACTION_URL . "/payments/link/$oldPaymentId&token=" . $token;

                if (!empty($methods['wc'])  && WooCommerceService::isEnabled()) {
                    $paymentLinks['payment_link_woocommerce'] = $basePaymentLink . '&paymentMethod=wc';
                }

                if (!empty($methods['stripe'])) {
                    $paymentLinks['payment_link_stripe'] = $basePaymentLink . '&paymentMethod=stripe';
                }

                if (!empty($methods['payPal'])) {
                    $paymentLinks['payment_link_paypal'] = $basePaymentLink . '&paymentMethod=payPal';
                }

                if (!empty($methods['razorpay'])) {
                    $paymentLinks['payment_link_razorpay'] = $basePaymentLink . '&paymentMethod=razorpay';
                }

                if (!empty($methods['mollie'])) {
                    $paymentLinks['payment_link_mollie'] = $basePaymentLink . '&paymentMethod=mollie';
                }

                if (!empty($methods['square'])) {
                    $paymentLinks['payment_link_square'] = $basePaymentLink . '&paymentMethod=square';
                }

                if (!empty($methods['barion'])) {
                    $paymentLinks['payment_link_barion'] = $basePaymentLink . '&paymentMethod=barion';
                }

                return $paymentLinks;
            }

            $methods = apply_filters('amelia_payment_link_methods', $methods, $data);

            $amount = apply_filters('amelia_payment_link_amount', $amount, $data);

            do_action('amelia_before_payment_links_created', $methods, $data, $amount);

            if (
                !empty($methods['wc']) &&
                WooCommerceService::isEnabled()
            ) {
                /** @var ReservationServiceInterface $reservationService */
                $reservationService = $this->container->get('application.reservation.service')->get($type);

                $appointmentData = $reservationService->getWooCommerceDataFromArray($data, $index);
                $appointmentData['redirectUrl'] = $redirectUrl;

                $bookableSettings = $data['bookable']['settings'] ?
                    json_decode($data['bookable']['settings'], true) : null;

                $appointmentData['wcProductId'] = $bookableSettings && isset($bookableSettings['payments']['wc']['productId']) ?
                    $bookableSettings['payments']['wc']['productId'] : null;

                $linkPayment = PaymentFactory::create([
                    'status' => PaymentStatus::PENDING,
                    'amount' => $amount,
                    'gateway' => 'wc',
                    'entity' => $type,
                    'dateTime' => null,
                ]);

                if ($oldPayment['gateway'] === 'onSite') {
                    $linkPayment->setId(new Id($oldPayment['id']));
                    $linkPayment->setWcOrderItemId(new Id($oldPayment['wcOrderItemId']));
                    $linkPayment->setWcOrderId(new Id($oldPayment['wcOrderId']));
                }

                $linkPayment->setActionsCompleted(new BooleanValueObject(true));
                if (!empty($oldPayment['customerBookingId'])) {
                    $linkPayment->setCustomerBookingId(new Id($oldPayment['customerBookingId']));
                }
                if (!empty($oldPayment['invoiceNumber'])) {
                    $linkPayment->setInvoiceNumber(new Id($oldPayment['invoiceNumber']));
                }
                if ($type === Entities::PACKAGE) {
                    $linkPayment->setCustomerBookingId(null);
                    $linkPayment->setPackageCustomerId(new Id($data['packageCustomerId']));
                }

                $appointmentData['payment'] = $linkPayment->toArray();
                $appointmentData['payment']['fromLink']   = true;
                $appointmentData['payment']['fromPanel']  = !empty($data['fromPanel']);
                $appointmentData['payment']['newPayment'] = $oldPayment['gateway'] !== 'onSite';

                $paymentLink = WooCommerceService::getPaymentLink($appointmentData, $amount, $oldPayment['wcOrderId'], $amount === $totalPrice);
                if (!empty($paymentLink['link'])) {
                    $paymentLinks['payment_link_woocommerce'] = $paymentLink['link'];
                } else {
                    $paymentLinks['payment_link_error_message'] = 'There has been an error creating the payment link';
                }

                $paymentLinks = apply_filters('amelia_wc_payment_link', $paymentLinks, $amount, $data);
            }

            if (!empty($methods['barion'])) {
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.barion.service');

                $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings(
                    $reservation,
                    PaymentType::BARION
                );

                $name = '';
                if ($type === Entities::APPOINTMENT) {
                    /** @var ServiceRepository $serviceRepository */
                    $serviceRepository = $this->container->get('domain.bookable.service.repository');
                    $service = $serviceRepository->getById($reservation['serviceId']);
                    $name = $service->getName()->getValue();
                } else {
                    $name = $reservation['name'];
                }

                $paymentData = [
                    'amount'      => $amount,
                    'reservation' => $reservation,
                    'returnUrl'   => $callbackLink . '&paymentMethod=barion&barionStatus=success',
                    'cancelUrl'   => $callbackLink . '&paymentMethod=barion&barionStatus=canceled',
                    'name'        => $name,
                    'info'        => $additionalInformation,
                    'paymentId'   => $data['paymentId'],
                ];

                $paymentLink = $paymentService->getPaymentLink($paymentData);

                if (empty($paymentLink['Errors'])) {
                    $paymentLinks['payment_link_barion'] = $paymentLink['GatewayUrl'];
                } else {
                    $paymentLinks['payment_link_error_code']    = $paymentLink['Status'];
                    $paymentLinks['payment_link_error_message'] = $paymentLink['Message'];
                }
            }

            if (!empty($methods['payPal'])) {
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.payPal.service');

                $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings($reservation, PaymentType::PAY_PAL, $index);

                $paymentData = [
                    'amount'      => $amount,
                    'description' => $additionalInformation['description'],
                    'returnUrl'   => $callbackLink . '&paymentMethod=payPal&payPalStatus=success',
                    'cancelUrl'   => $callbackLink . '&paymentMethod=payPal&payPalStatus=canceled'
                ];

                $paymentLink = $paymentService->getPaymentLink($paymentData);
                if ($paymentLink['status'] === 200 && !empty($paymentLink['link'])) {
                    $paymentLinks['payment_link_paypal'] = $paymentLink['link'] . '&useraction=commit';
                } else {
                    $paymentLinks['payment_link_error_code']    = $paymentLink['status'];
                    $paymentLinks['payment_link_error_message'] = $paymentLink['message'];
                }
            }

            if (!empty($methods['stripe'])) {
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.stripe.service');

                /** @var CurrencyService $currencyService */
                $currencyService = $this->container->get('infrastructure.payment.currency.service');

                $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings($reservation, PaymentType::STRIPE, $index);


                $paymentData = [
                    'amount'      => $currencyService->getAmountInFractionalUnit(new Price($amount)),
                    'description' => $additionalInformation['description'] ?: $data['bookable']['name'],
                    'returnUrl'   => $callbackLink . '&paymentMethod=stripe',
                    'metaData'    => $additionalInformation['metaData'] ?: [],
                    'currency'    => $settingsService->getCategorySettings('payments')['currency'],
                    'fromPanel'   => !empty($data['fromPanel']),
                    'customerId'  => !empty($customer['stripeConnect']['id']) ? $customer['stripeConnect']['id'] : null,
                    'customerData' => [
                        'name' => $customer['firstName'] . (!empty($customer['lastName']) ? (' ' . $customer['lastName']) : ''),
                        'email' => !empty($customer['email']) ? $customer['email'] : '',
                        'phone' => !empty($customer['phone']) ? $customer['phone'] : ''
                    ]
                ];

                $stripeSettings = $settingsService->getSetting('payments', 'stripe');

                if ($stripeSettings['connect']['enabled']) {
                    /** @var ProviderRepository $providerRepository */
                    $providerRepository = $this->container->get('domain.users.providers.repository');

                    $stripeConnectAccountIds = [];

                    switch ($reservation['type']) {
                        case ('appointment'):
                            if (!empty($reservation['providerId'])) {
                                /** @var Provider $provider */
                                $provider = $providerRepository->getById($reservation['providerId']);

                                if ($provider->getStripeConnect() && $provider->getStripeConnect()->getId()) {
                                    $stripeConnectAmount =
                                        $provider->getStripeConnect()->getAmount() &&
                                        $provider->getStripeConnect()->getAmount()->getValue()
                                        ? $provider->getStripeConnect()->getAmount()->getValue()
                                        : $stripeSettings['connect']['amount'];

                                    $stripeConnectAccountIds[$provider->getStripeConnect()->getId()->getValue()] =
                                        $stripeConnectAmount;
                                }
                            }

                            break;

                        case ('event'):
                            foreach ($reservation['providers'] as $providerArray) {
                                /** @var Provider $provider */
                                $provider = $providerRepository->getById($providerArray['id']);

                                if ($provider->getStripeConnect() && $provider->getStripeConnect()->getId()) {
                                    $stripeConnectAmount = $provider->getStripeConnect()->getAmount()
                                        ? $provider->getStripeConnect()->getAmount()->getValue()
                                        : $stripeSettings['connect']['amount'];

                                    $stripeConnectAccountIds[$provider->getStripeConnect()->getId()->getValue()] =
                                        $stripeConnectAmount;
                                }
                            }

                            break;

                        case ('package'):
                            foreach ($reservation['bookable'] as $bookable) {
                                foreach ($bookable['providers'] as $providerArray) {
                                    /** @var Provider $provider */
                                    $provider = $providerRepository->getById($providerArray['id']);

                                    if ($provider->getStripeConnect() && $provider->getStripeConnect()->getId()) {
                                        $stripeConnectAmount = $provider->getStripeConnect()->getAmount()
                                            ? $provider->getStripeConnect()->getAmount()->getValue()
                                            : $stripeSettings['connect']['amount'];

                                        $stripeConnectAccountIds[$provider->getStripeConnect()->getId()->getValue()] =
                                            $stripeConnectAmount;
                                    }
                                }
                            }

                            break;
                    }

                    if (sizeof($stripeConnectAccountIds) === 1) {
                        $transferAmount = $stripeSettings['connect']['type'] === 'fixed'
                            ? array_values($stripeConnectAccountIds)[0]
                            : round(($amount / 100) * array_values($stripeConnectAccountIds)[0], 2);

                        $paymentData['transfer'] = [
                            'accountId' => array_keys($stripeConnectAccountIds)[0],
                            'amount'    => $currencyService->getAmountInFractionalUnit(
                                new Price($transferAmount)
                            )
                        ];
                    }
                }

                $paymentLink = $paymentService->getPaymentLink($paymentData);
                if ($paymentLink['status'] === 200 && !empty($paymentLink['link'])) {
                    $paymentLinks['payment_link_stripe'] = $paymentLink['link'] . (empty($data['fromPanel']) ? '?prefilled_email=' . $customer['email'] : '');
                } else {
                    $paymentLinks['payment_link_error_code']    = $paymentLink['status'];
                    $paymentLinks['payment_link_error_message'] = $paymentLink['message'];
                }

                if (!empty($paymentLink['customerId'])) {
                    /** @var CustomerRepository $customerRepository */
                    $customerRepository = $this->container->get('domain.users.customers.repository');

                    $stripeConnect = StripeFactory::create(['id' => $paymentLink['customerId']]);
                    if (!empty($data['customer']['id']) && empty($data['customer']['stripeConnect'])) {
                        $customerRepository->updateFieldById($data['customer']['id'], json_encode($stripeConnect->toArray()), 'stripeConnect');
                    }
                }
            }

            if (!empty($methods['mollie'])) {
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.mollie.service');

                $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings($reservation, PaymentType::MOLLIE, $index);

                $paymentData =
                    [
                        'amount'      => [
                            'currency' =>  $settingsService->getCategorySettings('payments')['currency'],
                            'value' => number_format((float)$amount, 2, '.', '') //strval($amount)
                        ],
                        'description' => $additionalInformation['description'] ?: $data['bookable']['name'],
                        'redirectUrl' => $redirectUrl,
                        'webhookUrl'  => (AMELIA_DEV ? str_replace('localhost', AMELIA_NGROK_URL, $callbackLink) : $callbackLink) . '&paymentMethod=mollie',
                    ];

                $paymentLink = $paymentService->getPaymentLink($paymentData);
                if ($paymentLink['status'] === 200 && !empty($paymentLink['link'])) {
                    $paymentLinks['payment_link_mollie'] = $paymentLink['link'];
                } else {
                    $paymentLinks['payment_link_error_code']    = $paymentLink['status'];
                    $paymentLinks['payment_link_error_message'] = $paymentLink['message'];
                }
            }

            if (!empty($methods['square'])) {
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.square.service');

                $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings($reservation, PaymentType::SQUARE, $index);

                $pendingPaymentKey = array_search(
                    PaymentStatus::PENDING,
                    array_column($payments, 'status')
                );

                if ($pendingPaymentKey !== false) {
                    $ameliaPaymentId = $payments[$pendingPaymentKey]['id'];
                } else {
                    $oldPayment['status'] = PaymentStatus::PENDING;

                    $linkPayment = $paymentAS->insertPaymentFromLink($oldPayment, $amount, $oldPayment['entity']);

                    $ameliaPaymentId = $linkPayment->getId()->getValue();
                }

                $returnUrl =
                    AMELIA_ACTION_URL . '__payments__callback&fromLink=true&paymentAmeliaId=' .
                    $ameliaPaymentId . '&chargedAmount=' . $amount . '&fromPanel=' . (!empty($data['fromPanel']));

                $paymentData =
                    [
                        'redirectUrl' => $returnUrl . '&paymentMethod=square',
                        'amount'      => $currencyService->getAmountInFractionalUnit(new Price($amount)),
                        'description' => $additionalInformation['description'] ?: $data['bookable']['name'],
                        'metaData'    => $additionalInformation['metaData'] ?: [],
                        'customer'    => $customer,
                        'paymentId'   => $ameliaPaymentId
                    ];

                $paymentLink = $paymentService->getPaymentLink($paymentData);

                if ($paymentLink['status'] === 200 && !empty($paymentLink['link'])) {
                    $paymentLinks['payment_link_square'] = $paymentLink['link'];
                } else {
                    $paymentLinks['payment_link_error_code']    = $paymentLink['status'];
                    $paymentLinks['payment_link_error_message'] = $paymentLink['message'];
                }
            }


            if (!empty($methods['razorpay'])) {
                /** @var PaymentServiceInterface $paymentService */
                $paymentService = $this->container->get('infrastructure.payment.razorpay.service');

                $additionalInformation = $paymentAS->getBookingInformationForPaymentSettings($reservation, PaymentType::RAZORPAY, $index);

                $paymentData =
                    [
                        'amount'      => intval($amount * 100),
                        'description' => $additionalInformation['description'],
                        'notes'    => $additionalInformation['metaData'] ?: [],
                        'currency' => $settingsService->getCategorySettings('payments')['currency'],
                        'customer' => [
                            'name'    => $customer['firstName'] . ' ' . $customer['lastName'],
                            'email'   => $customer['email'],
                            'contact' => $customer['phone']
                        ],
                        //'notify' => ['sms' => false, 'email' => true],
                        'callback_url'    =>
                            AMELIA_ACTION_URL . '__payments__callback&fromLink=true&paymentAmeliaId=' . $oldPaymentId .
                            '&chargedAmount=' . $amount . '&paymentMethod=razorpay' . '&fromPanel=' . (!empty($paymentMethod)),
                        'callback_method' => 'get'
                    ];

                $paymentLink = $paymentService->getPaymentLink($paymentData);
                if ($paymentLink['status'] === 200 && !empty($paymentLink['link'])) {
                    $paymentLinks['payment_link_razorpay'] = $paymentLink['link'];
                } else {
                    $paymentLinks['payment_link_error_code']    = $paymentLink['status'];
                    $paymentLinks['payment_link_error_message'] = $paymentLink['message'];
                }
            }

            $paymentLinks = apply_filters('amelia_payment_links', $paymentLinks, $amount, $data);

            do_action('amelia_after_payment_links_created', $paymentLinks, $data, $amount);

            return $paymentLinks;
        } catch (Exception $e) {
            return ['payment_link_error_message' => 'There has been an error creating the payment link'];
        }
    }

    /**
     * @param array  $booking
     * @param string $type
     * @return string
     */
    public function getFullStatus($booking, $type, $reservationEntity = null)
    {
        $bookingPrice = $this->calculateAppointmentPrice($booking, $type, $reservationEntity);

        $paidAmount     = 0;
        $refundedAmount = 0;
        foreach ($booking['payments'] as $payment) {
            if ($payment['status'] === 'paid' || $payment['status'] === 'partiallyPaid') {
                $paidAmount += $payment['amount'];
            } elseif ($payment['status'] === 'refunded') {
                $refundedAmount += $payment['amount'];
            }
        }

        if ($paidAmount >= $bookingPrice) {
            return 'paid';
        }
        if ($refundedAmount >= $bookingPrice) {
            return 'refunded';
        }

        return $paidAmount > 0 ? 'partiallyPaid' : 'pending';
    }

    /**
     * Create a bookable entity with extras populated from booking data
     * This method extracts the logic from calculateAppointmentPrice to make it reusable
     *
     * @param array $booking
     * @param string $type
     * @return AbstractBookable
     * @throws InvalidArgumentException
     */
    public function createBookableWithExtras($booking, $type)
    {
        /** @var AbstractBookable|null $bookable */
        $bookable = null;

        switch ($type) {
            case (Entities::APPOINTMENT):
                $serviceExtras = [];

                foreach ($booking['extras'] as $extra) {
                    $serviceExtras[$extra['extraId']] = [
                        'price'           => $extra['price'],
                        'aggregatedPrice' => !empty($extra['aggregatedPrice']),
                    ];
                }

                /** @var Service $bookable */
                $bookable = ServiceFactory::create(
                    [
                        'price'           => $booking['price'],
                        'aggregatedPrice' => !empty($booking['aggregatedPrice']),
                        'extras'          => $serviceExtras,
                    ]
                );
                break;

            case (Entities::EVENT):
                $customTickets = !empty($booking['ticketsData']) ? $booking['ticketsData'] : [];

                $eventCustomPricing = [];

                foreach ($customTickets as $customTicket) {
                    $eventCustomPricing[$customTicket['eventTicketId']] = [
                        'dateRanges'     => '[]',
                        'price'          => $customTicket['price'],
                        'dateRangePrice' => 0,
                    ];
                }

                /** @var Event $bookable */
                $bookable = EventFactory::create(
                    [
                        'price'           => $booking['price'],
                        'aggregatedPrice' => $booking['aggregatedPrice'],
                        'customPricing'   => !empty($eventCustomPricing),
                        'customTickets'   => !empty($eventCustomPricing) ? $eventCustomPricing : null,
                    ]
                );
                break;
        }

        return $bookable;
    }

    /**
     * @param array  $booking
     * @param string $type
     * @param array  $reservationEntity
     *
     * @return float
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function calculateAppointmentPrice($booking, $type, $reservationEntity = null)
    {
        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var Reservation $reservation */
        $reservation = new Reservation();

        /** @var AbstractBookable|null $bookable */
        $bookable = null;

        switch ($type) {
            case (Entities::APPOINTMENT):
                /** @var Coupon $coupon */
                $coupon = !empty($booking['coupon']) ? CouponFactory::create($booking['coupon']) : null;

                $bookable = $this->createBookableWithExtras($booking, $type);

                $reservation->setBooking(
                    CustomerBookingFactory::create(
                        [
                            'persons' => $booking['persons'],
                            'coupon'  => $coupon ? $coupon->toArray() : null,
                            'extras'  => $booking['extras'],
                            'tax'     => !empty($booking['tax']) ? json_encode($booking['tax']) : null,
                        ]
                    )
                );

                $reservation->setRecurring(new Collection());

                break;

            case (Entities::EVENT):
                /** @var Coupon $coupon */
                $coupon = !empty($booking['coupon']) ? CouponFactory::create($booking['coupon']) : null;

                $bookable = $this->createBookableWithExtras($booking, $type);

                $reservation->setBooking(
                    CustomerBookingFactory::create(
                        [
                            'persons'         => $booking['persons'],
                            'coupon'          => $coupon ? $coupon->toArray() : null,
                            'tax'             => !empty($booking['tax']) ? (is_array($booking['tax']) ? json_encode($booking['tax']) : $booking['tax']) : null,
                            'aggregatedPrice' => $booking['aggregatedPrice'],
                            'ticketsData'     => !empty($booking['ticketsData']) ? $booking['ticketsData'] : null,
                        ]
                    )
                );

                break;

            case (Entities::PACKAGE):
                /** @var PackageCustomerRepository $packageCustomerRepository */
                $packageCustomerRepository = $this->container->get('domain.bookable.packageCustomer.repository');

                if (!empty($reservationEntity['packageCustomer'])) {
                    $packageCustomer = PackageCustomerFactory::create($reservationEntity['packageCustomer']);
                } else {
                    /** @var PackageCustomer $packageCustomer */
                    $packageCustomer = $packageCustomerRepository->getById($reservationEntity['packageCustomerId']);
                }

                if ($packageCustomer->getCouponId() && $packageCustomer->getCoupon() === null) {
                    /** @var CouponRepository $couponRepository */
                    $couponRepository = $this->container->get('domain.coupon.repository');

                    /** @var Coupon $coupon */
                    $coupon = $couponRepository->getById($packageCustomer->getCouponId()->getValue());

                    $packageCustomer->setCoupon($coupon);
                }

                /** @var Package $bookable */
                $bookable = PackageFactory::create(
                    [
                        'price'           => $reservationEntity['price'],
                        'calculatedPrice' => $reservationEntity['calculatedPrice'],
                        'discount'        => $reservationEntity['discount'],
                    ]
                );

                $reservation->setPackageCustomer($packageCustomer);

                break;
        }

        $reservation->setBookable($bookable);

        $reservation->setApplyDeposit(new BooleanValueObject(false));

        return $reservationService->getReservationPaymentAmount($reservation);
    }

    /**
     * @param int    $paymentId
     * @param string $transactionId
     *
     * @throws QueryExecutionException
     */
    public function setPaymentTransactionId($paymentId, $transactionId)
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        if ($transactionId && $paymentId) {
            $paymentRepository->updateTransactionId(
                $paymentId,
                $transactionId
            );
        }
    }

    /**
     * @param array $transfers
     *
     * @throws QueryExecutionException
     */
    public function setPaymentsTransfers($transfers)
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        $payments = [];

        foreach ($transfers['accounts'] as $accountId => $transfer) {
            foreach ($transfer as $paymentId => $payment) {
                if (!empty($payment['transferId'])) {
                    $payments[$paymentId][$accountId][$payment['transferId']] = $payment['amount'];
                } else {
                    $payments[$paymentId][$accountId] = [];
                }
            }
        }

        foreach ($payments as $paymentId => $accounts) {
            $paymentRepository->updateFieldById(
                $paymentId,
                json_encode(['method' => $transfers['method'], 'accounts' => $accounts]),
                'transfers'
            );
        }
    }

    /**
     * Inspect if there is related payment (multiple appointments were booked and paid at once) that can be refunded
     *
     * @param Payment $payment
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws NotFoundException
     */
    public function hasRelatedRefundablePayment($payment)
    {
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var Collection $followingPayments */
        $followingPayments = $paymentRepository->getByEntityId(
            $payment->getParentId() ? $payment->getParentId()->getValue() : $payment->getId()->getValue(),
            'parentId'
        );

        if ($payment->getParentId()) {
            /** @var Payment $parentPayment */
            $parentPayment = $paymentRepository->getById($payment->getParentId()->getValue());

            $followingPayments->addItem($parentPayment);
        }

        /** @var Payment $followingPayment */
        foreach ($followingPayments->getItems() as $followingPayment) {
            if (
                $followingPayment->getId()->getValue() !== $payment->getId()->getValue() &&
                (
                    $followingPayment->getStatus()->getValue() === PaymentStatus::REFUNDED ||
                    $followingPayment->getStatus()->getValue() === PaymentStatus::PAID ||
                    $followingPayment->getStatus()->getValue() === PaymentStatus::PARTIALLY_PAID
                )
            ) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param CommandResult $result
     * @param array $appointmentData
     * @param Cache $cache
     * @param Reservation $reservation
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function updateCache($result, $appointmentData, $cache, $reservation, $squareData = null, $razorpayOrderId = null)
    {
        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        if ($result->getResult() !== CommandResult::RESULT_ERROR) {
            /** @var Payment|null $payment */
            $payment = null;

            switch ($reservation->getReservation()->getType()->getValue()) {
                case (Entities::APPOINTMENT):
                case (Entities::EVENT):
                    /** @var Payment $payment */
                    $payment = $reservation->getBooking()->getPayments()->getItem(0);

                    break;

                case (Entities::PACKAGE):
                    /** @var PackageCustomerService $packageCustomerService */
                    foreach ($reservation->getPackageCustomerServices()->getItems() as $packageCustomerService) {
                        /** @var Payment $payment */
                        $payment =
                            $packageCustomerService->getPackageCustomer()->getPayments()
                                ->getItem($packageCustomerService->getPackageCustomer()->getPayments()->keys()[0]);

                        break;
                    }

                    break;
            }

            $cache->setPaymentId(new Id($payment->getId()->getValue()));

            $cache->setData(
                new Json(
                    json_encode(
                        [
                            'status'   => null,
                            'request'  => $appointmentData['componentProps'],
                            'response' => $result->getData(),
                            'squareOrderId' => $squareData ? $squareData['orderId'] : null,
                            'razorpayOrderId' => $razorpayOrderId,
                            'subscribeToMailchimp' => filter_var(
                                isset($appointmentData['bookings'][0]['customer']['subscribeToMailchimp'])
                                    ? $appointmentData['bookings'][0]['customer']['subscribeToMailchimp']
                                    : false,
                                FILTER_VALIDATE_BOOLEAN,
                                FILTER_NULL_ON_FAILURE
                            ) === true
                        ]
                    )
                )
            );

            $cacheRepository->update(
                $cache->getId()->getValue(),
                $cache
            );
        }

        return $result;
    }

    /**
     * Reconciles pending Razorpay bookings whose payment was captured on Razorpay's side but
     * never finalized in Amelia (e.g. the customer's browser closed before the checkout
     * callback or webhook reached this site). Intended to run on a schedule (WP-Cron).
     *
     * @return void
     */
    public function reconcilePendingRazorpayPayments()
    {
        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        /** @var RazorpayService $paymentService */
        $paymentService = $this->container->get('infrastructure.payment.razorpay.service');

        $cutoff = DateTimeService::getNowDateTimeObjectInUtc()->modify('-15 minutes')->format('Y-m-d H:i:s');

        $caches = $cacheRepository->getPendingByGatewayOlderThan(PaymentType::RAZORPAY, $cutoff);

        foreach ($caches as $cache) {
            try {
                $this->reconcilePendingRazorpayCache($cache, $paymentRepository, $paymentService);
            } catch (Exception $e) {
                $this->container->getLoggerService()->error(
                    'Razorpay reconciliation failed for cache #' . $cache->getId()->getValue() . ': ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * @param Cache             $cache
     * @param PaymentRepository $paymentRepository
     * @param RazorpayService   $paymentService
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    private function reconcilePendingRazorpayCache($cache, $paymentRepository, $paymentService)
    {
        $cacheData = json_decode($cache->getData()->getValue(), true);

        if (!empty($cacheData['status']) && $cacheData['status'] === PaymentStatus::PAID) {
            return;
        }

        $razorpayOrderId = !empty($cacheData['razorpayOrderId']) ? $cacheData['razorpayOrderId'] : null;

        if (!$razorpayOrderId) {
            return;
        }

        if (!$cache->getPaymentId()) {
            return;
        }

        /** @var Payment|null $payment */
        $payment = $paymentRepository->getById($cache->getPaymentId()->getValue());

        if (!$payment || $payment->getStatus()->getValue() !== PaymentStatus::PENDING) {
            return;
        }

        $orderPayments = $paymentService->fetchOrderPayments($razorpayOrderId);

        $captured = null;
        $authorized = null;

        foreach ($orderPayments as $orderPayment) {
            if (empty($orderPayment['status'])) {
                continue;
            }

            if ($orderPayment['status'] === 'captured') {
                $captured = $orderPayment;

                break;
            }

            if ($orderPayment['status'] === 'authorized' && $authorized === null) {
                $authorized = $orderPayment;
            }
        }

        $paymentAmount = (float) $payment->getAmount()->getValue();

        if (!$captured && $authorized) {
            if (!$paymentService->amountMatches($authorized['amount'] ?? 0, $paymentAmount)) {
                $this->container->getLoggerService()->error(
                    'Razorpay reconciliation amount mismatch for authorized payment on cache #' .
                    $cache->getId()->getValue() . ', order ' . $razorpayOrderId
                );

                return;
            }

            try {
                $response = $paymentService->capture($authorized['id'], $paymentAmount);
            } catch (Exception $e) {
                $this->container->getLoggerService()->error(
                    'Razorpay reconciliation capture failed for authorized payment on cache #' .
                    $cache->getId()->getValue() . ', order ' . $razorpayOrderId . ': ' . $e->getMessage()
                );

                return;
            }

            if (is_object($response) && method_exists($response, 'toArray')) {
                $response = $response->toArray();
            }

            $captureSucceeded = is_array($response) && (
                (!empty($response['status']) && $response['status'] === 'captured') ||
                (isset($response['error_code']) && (int) $response['error_code'] === 0)
            );

            if (!$captureSucceeded) {
                $this->container->getLoggerService()->error(
                    'Razorpay reconciliation capture rejected for cache #' .
                    $cache->getId()->getValue() . ', order ' . $razorpayOrderId . ' - marking payment as failed'
                );

                $this->updateAppointmentAndCache($payment->getEntity()->getValue(), 'failed', $cache, null);

                return;
            }

            $captured = [
                'id'     => $authorized['id'],
                'amount' => $authorized['amount'] ?? 0,
                'status' => 'captured',
            ];
        }

        if (!$captured) {
            $this->container->getLoggerService()->info(
                'Razorpay reconciliation found no successful payment for cache #' .
                $cache->getId()->getValue() . ', order ' . $razorpayOrderId . ' - marking payment as failed'
            );

            $this->updateAppointmentAndCache($payment->getEntity()->getValue(), 'failed', $cache, null);

            return;
        }

        if (!$paymentService->amountMatches($captured['amount'] ?? 0, $paymentAmount)) {
            $this->container->getLoggerService()->error(
                'Razorpay reconciliation amount mismatch for cache #' . $cache->getId()->getValue() .
                ', order ' . $razorpayOrderId
            );

            return;
        }

        $type = $payment->getEntity()->getValue();

        $result = $this->updateAppointmentAndCache(
            $type,
            PaymentStatus::PAID,
            $cache,
            $captured['id']
        );

        if ($result->getResult() === CommandResult::RESULT_SUCCESS) {
            /** @var ReservationServiceInterface $reservationService */
            $reservationService = $this->container->get('application.reservation.service')->get($type);

            $reservationService->runPostBookingActions($result);
        }
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * @param array         $paymentData
     * @param Reservation   $reservation
     * @param BookingType   $bookingType
     * @param array         $transfers
     * @param bool          $usePayment
     *
     * @return void
     *
     * @throws Exception
     */
    public function setTransfers($paymentData, $reservation, $bookingType, &$transfers, $usePayment)
    {
        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($bookingType->getValue());

        switch ($paymentData['gateway']) {
            case ('stripe'):
                /** @var CurrencyService $currencyService */
                $currencyService = $this->container->get('infrastructure.payment.currency.service');

                /** @var ProviderRepository $providerRepository */
                $providerRepository = $this->container->get('domain.users.providers.repository');

                /** @var SettingsService $settingsService */
                $settingsService = $this->container->get('domain.settings.service');

                $stripeSettings = $settingsService->getSetting('payments', 'stripe');

                if ($stripeSettings['connect']['enabled']) {
                    $transfers['method'] = $stripeSettings['connect']['method'];

                    $transfers['accounts'] = [];

                    $providersAmountData = $reservationService->getProvidersPaymentAmount($reservation, $usePayment);

                    foreach ($providersAmountData as $providerId => $items) {
                        /** @var Provider $provider */
                        $provider = $providerRepository->getById($providerId);

                        $stripeConnectAccountId = $provider->getStripeConnect() && $provider->getStripeConnect()->getId()
                            ? $provider->getStripeConnect()->getId()->getValue()
                            : null;

                        $stripeConnectAmount =
                            $provider->getStripeConnect() &&
                            $provider->getStripeConnect()->getAmount()
                                ? $provider->getStripeConnect()->getAmount()->getValue()
                                : $stripeSettings['connect']['amount'];

                        if ($stripeConnectAccountId) {
                            foreach ($items as $item) {
                                $amount = $stripeSettings['connect']['type'] === 'fixed'
                                    ? $stripeConnectAmount
                                    : round(($item['amount'] / 100) * $stripeConnectAmount, 2);

                                $transfers['accounts'][$stripeConnectAccountId][$item['paymentId'] ?: 0] = [
                                    'amount' => $currencyService->getAmountInFractionalUnit(new Price($amount)),
                                ];
                            }
                        }
                    }
                }

                break;

            default:
                break;
        }
    }

    /**
     * Subscribes a customer that opted in while booking through a gateway which only confirms the payment
     * after the reservation has already been stored.
     *
     * @param array $customerData
     *
     * @return void
     */
    private function addMailchimpSubscriber($customerData)
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        if (!$settingsService->isFeatureEnabled('mailchimp') || empty($customerData['email'])) {
            return;
        }

        /** @var AbstractMailchimpService $mailchimpService */
        $mailchimpService = $this->container->get('infrastructure.mailchimp.service');

        $mailchimpService->addOrUpdateSubscriber($customerData['email'], $customerData);
    }

    /**
     * @param string $status
     * @param Cache  $cache
     * @param string $transactionId
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function updateAppointmentAndCache($type, $status, $cache, $transactionId)
    {
        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');
        /** @var AppointmentRepository $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');
        /** @var CustomerRepository $customerRepository */
        $customerRepository = $this->container->get('domain.users.customers.repository');
        /** @var LocationRepository $locationRepository */
        $locationRepository = $this->container->get('domain.locations.repository');
        /** @var PackageRepository $packageRepository */
        $packageRepository = $this->container->get('domain.bookable.package.repository');
        /** @var PackageCustomerServiceRepository $packageCustomerServiceRepository */
        $packageCustomerServiceRepository = $this->container->get('domain.bookable.packageCustomerService.repository');
        /** @var AppointmentApplicationService $appointmentAS */
        $appointmentAS = $this->container->get('application.booking.appointment.service');
        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');
        /** @var BookableApplicationService $bookableAS */
        $bookableAS = $this->container->get('application.bookable.service');
        /** @var EventApplicationService $eventApplicationService */
        $eventApplicationService = $this->container->get('application.booking.event.service');

        $result = new CommandResult();

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully finalized payment');
        $result->setData([]);
        $result->setDataInResponse(false);

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        $mailchimpCustomerData = null;

        $transactionOpen = false;

        try {
            $cacheRepository->beginTransaction();
            $transactionOpen = true;

            $lockedCache = $cacheRepository->getByIdForUpdate($cache->getId()->getValue());

            if (!$lockedCache) {
                $cacheRepository->commit();
                $transactionOpen = false;

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Cache object not found');
                $result->setData(
                    [
                        'paymentSuccessful' => false,
                    ]
                );

                return $result;
            }

            $cache = $lockedCache;

            $cacheData = json_decode($cache->getData()->getValue(), true);

            /** @var Payment|null $payment */
            $payment = null;

            if (!$cache->getPaymentId()) {
                if (in_array($status, ['canceled', 'failed', 'expired'], true)) {
                    if (
                        is_array($cacheData) &&
                        isset($cacheData['status']) &&
                        $cacheData['status'] !== 'pending'
                    ) {
                        $cacheRepository->commit();
                        $transactionOpen = false;

                        $result->setMessage('Payment already finalized');
                        $result->setData(
                            [
                                'ignored' => true,
                                'status'  => $cacheData['status'],
                            ]
                        );

                        return $result;
                    }

                    if ($status === 'expired') {
                        $cacheRepository->delete($cache->getId()->getValue());
                    } else {
                        $cache->setData(
                            new Json(
                                json_encode(
                                    array_merge(
                                        is_array($cacheData) ? $cacheData : [],
                                        [
                                            'status' => $status,
                                        ]
                                    )
                                )
                            )
                        );

                        $cache->setPaymentId(null);

                        $cacheRepository->update($cache->getId()->getValue(), $cache);
                    }

                    $cacheRepository->commit();
                    $transactionOpen = false;

                    return $result;
                }

                $cacheRepository->commit();
                $transactionOpen = false;

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Cache payment not found');
                $result->setData(
                    [
                        'paymentSuccessful' => false,
                    ]
                );

                return $result;
            }

            $payment = $paymentRepository->getById($cache->getPaymentId()->getValue());

            $shouldFinalizePaid = false;

            if ($status === 'paid') {
                if (!empty($cacheData['status']) && $cacheData['status'] === PaymentStatus::PAID) {
                    $cacheRepository->commit();
                    $transactionOpen = false;

                    $result->setMessage('Payment already finalized');
                    $result->setData(
                        array_merge(
                            !empty($cacheData['response']) && is_array($cacheData['response'])
                                ? $cacheData['response']
                                : [],
                            ['paymentSuccessful' => true]
                        )
                    );

                    return $result;
                }

                if (
                    $payment &&
                    in_array(
                        $payment->getStatus()->getValue(),
                        [PaymentStatus::PAID, PaymentStatus::PARTIALLY_PAID],
                        true
                    )
                ) {
                    $cacheRepository->commit();
                    $transactionOpen = false;

                    $result->setMessage('Payment already finalized');
                    $result->setData(
                        array_merge(
                            !empty($cacheData['response']) && is_array($cacheData['response'])
                                ? $cacheData['response']
                                : [],
                            ['paymentSuccessful' => true]
                        )
                    );

                    return $result;
                }

                if (
                    !$payment ||
                    $payment->getStatus()->getValue() !== PaymentStatus::PENDING
                ) {
                    $cacheRepository->commit();
                    $transactionOpen = false;

                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Payment cannot be finalized');
                    $result->setData(
                        [
                            'paymentSuccessful' => false,
                            'status'            => $payment && $payment->getStatus()
                                ? $payment->getStatus()->getValue()
                                : null,
                        ]
                    );

                    return $result;
                }

                if (!$cacheRepository->claimPendingAsPaid($cache->getId()->getValue())) {
                    $freshCache = $cacheRepository->getByIdForUpdate($cache->getId()->getValue());
                    $freshData = $freshCache
                        ? json_decode($freshCache->getData()->getValue(), true)
                        : $cacheData;
                    $freshStatus = !empty($freshData['status']) ? $freshData['status'] : null;

                    $cacheRepository->commit();
                    $transactionOpen = false;

                    if ($freshStatus === PaymentStatus::PAID) {
                        $result->setMessage('Payment already finalized');
                        $result->setData(
                            array_merge(
                                !empty($freshData['response']) && is_array($freshData['response'])
                                    ? $freshData['response']
                                    : [],
                                ['paymentSuccessful' => true]
                            )
                        );

                        return $result;
                    }

                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setMessage('Payment finalization claim failed');
                    $result->setData(
                        [
                            'paymentSuccessful' => false,
                            'status'            => $freshStatus,
                        ]
                    );

                    return $result;
                }

                $shouldFinalizePaid = true;
            }

            if ($shouldFinalizePaid) {
                $paymentRepository->updateFieldById(
                    $payment->getId()->getValue(),
                    $transactionId,
                    'transactionId'
                );

                $paymentRepository->updateFieldByColumn(
                    'transactionId',
                    $transactionId,
                    'parentId',
                    $payment->getId()->getValue()
                );

                switch ($type) {
                    case (Entities::APPOINTMENT):
                        $recurringData = [];

                        /** @var Appointment $appointment */
                        $appointment = $appointmentRepository->getByPaymentId($payment->getId()->getValue());

                        if ($appointment->getLocationId()) {
                            /** @var Location $location */
                            $location = $locationRepository->getById($appointment->getLocationId()->getValue());

                            $appointment->setLocation($location);
                        }

                        /** @var CustomerBooking $booking */
                        $booking = $appointment->getBookings()->getItem($payment->getCustomerBookingId()->getValue());

                        $token = $bookingRepository->getToken($booking->getId()->getValue());

                        if (!empty($token['token'])) {
                            $booking->setToken(new Token($token['token']));
                        }

                        /** @var AbstractUser $customer */
                        $customer = $customerRepository->getById($booking->getCustomerId()->getValue());

                        /** @var Collection $nextPayments */
                        $nextPayments = $paymentRepository->getByEntityId($payment->getId()->getValue(), 'parentId');

                        /** @var Payment $nextPayment */
                        foreach ($nextPayments->getItems() as $nextPayment) {
                            /** @var Appointment $nextAppointment */
                            $nextAppointment = $appointmentRepository->getByPaymentId($nextPayment->getId()->getValue());

                            if ($nextAppointment->getLocationId()) {
                                /** @var Location $location */
                                $location = $locationRepository->getById($nextAppointment->getLocationId()->getValue());

                                $nextAppointment->setLocation($location);
                            }

                            /** @var CustomerBooking $nextBooking */
                            $nextBooking = $nextAppointment->getBookings()->getItem(
                                $nextPayment->getCustomerBookingId()->getValue()
                            );

                            $nextToken = $bookingRepository->getToken($nextBooking->getId()->getValue());

                            if (!empty($nextToken['token'])) {
                                $nextBooking->setToken(new Token($nextToken['token']));
                            }

                            /** @var Service $nextService */
                            $nextService = $bookableAS->getAppointmentService(
                                $nextAppointment->getServiceId()->getValue(),
                                $nextAppointment->getProviderId()->getValue()
                            );

                            $nextAppointmentStatusChanged = $appointmentAS->isAppointmentStatusChangedWithBooking(
                                $nextService,
                                $nextAppointment,
                                $nextPayment,
                                $nextBooking
                            );

                            $recurringData[] = [
                                'type'                     => Entities::APPOINTMENT,
                                Entities::APPOINTMENT      => $nextAppointment->toArray(),
                                Entities::BOOKING          => $nextBooking->toArray(),
                                'appointmentStatusChanged' => $nextAppointmentStatusChanged,
                                'utcTime'                  => $reservationService->getBookingPeriods(
                                    $nextAppointment,
                                    $nextBooking,
                                    $nextService
                                ),
                            ];
                        }

                        /** @var Service $service */
                        $service = $bookableAS->getAppointmentService(
                            $appointment->getServiceId()->getValue(),
                            $appointment->getProviderId()->getValue()
                        );

                        $appointmentStatusChanged = $appointmentAS->isAppointmentStatusChangedWithBooking(
                            $service,
                            $appointment,
                            $payment,
                            $booking
                        );

                        $customerCabinetUrl = '';

                        if (
                            $customer &&
                            $customer->getEmail() &&
                            $customer->getEmail()->getValue() &&
                            $booking->getInfo() &&
                            $booking->getInfo()->getValue()
                        ) {
                            $infoJson = json_decode($booking->getInfo()->getValue(), true);
                            $locale = is_array($infoJson) && !empty($infoJson['locale']) ? $infoJson['locale'] : '';

                            /** @var \AmeliaBooking\Application\Services\Helper\HelperService $helperService */
                            $helperService = $this->container->get('application.helper.service');

                            $customerCabinetUrl = $helperService->getCustomerCabinetUrl(
                                $customer->getEmail()->getValue(),
                                'email',
                                $appointment->getBookingStart()->getValue()->format('Y-m-d'),
                                $appointment->getBookingEnd()->getValue()->format('Y-m-d'),
                                $locale
                            );
                        }

                        $result->setData(
                            [
                            'type'                     => Entities::APPOINTMENT,
                            Entities::APPOINTMENT      => $appointment->toArray(),
                            Entities::BOOKING          => $booking->toArray(),
                            'customer'                 => $customer->toArray(),
                            'packageId'                => 0,
                            'recurring'                => $recurringData,
                            'appointmentStatusChanged' => $appointmentStatusChanged,
                            'bookable'                 => $service->toArray(),
                            'utcTime'                  => $reservationService->getBookingPeriods(
                                $appointment,
                                $booking,
                                $service
                            ),
                            'paymentId'                => $payment->getId()->getValue(),
                            'packageCustomerId'        => 0,
                            'payment'                  => $payment ? $payment->toArray() : null,
                            'customerCabinetUrl'       => $customerCabinetUrl,
                            ]
                        );

                        break;

                    case (Entities::EVENT):
                        /** @var Event $event */
                        $event = $reservationService->getReservationByBookingId(
                            $payment->getCustomerBookingId()->getValue()
                        );

                        if ($event->getLocationId()) {
                            /** @var Location $location */
                            $location = $locationRepository->getById($event->getLocationId()->getValue());

                            $event->setLocation($location);
                        }

                        /** @var CustomerBooking $booking */
                        $booking = $event->getBookings()->getItem($payment->getCustomerBookingId()->getValue());

                        $token = $bookingRepository->getToken($booking->getId()->getValue());

                        if (!empty($token['token'])) {
                            $booking->setToken(new Token($token['token']));
                        }

                        if ($booking->getStatus()->getValue() === BookingStatus::PENDING) {
                            $booking->setChangedStatus(new BooleanValueObject(true));
                            $booking->setStatus(new BookingStatus(BookingStatus::APPROVED));

                            $bookingRepository->updateFieldById(
                                $booking->getId()->getValue(),
                                BookingStatus::APPROVED,
                                'status'
                            );
                        }

                        /** @var AbstractUser $customer */
                        $customer = $customerRepository->getById($booking->getCustomerId()->getValue());

                        $paymentStatus = $reservationService->getPaymentAmount($booking, $event)['price'] >
                            $payment->getAmount()->getValue() ?
                            PaymentStatus::PARTIALLY_PAID : PaymentStatus::PAID;

                        $paymentRepository->updateFieldById(
                            $payment->getId()->getValue(),
                            $paymentStatus,
                            'status'
                        );
                        $payment->setStatus(new PaymentStatus($paymentStatus));

                        $event->setBookings(new Collection());

                        $event->getBookings()->addItem($booking);


                        $result->setData(
                            [
                            'type'                     => Entities::EVENT,
                            Entities::EVENT            => $event->toArray(),
                            Entities::BOOKING          => $booking->toArray(),
                            'appointmentStatusChanged' => false,
                            'customer'                 => $customer->toArray(),
                            'packageId'                => 0,
                            'recurring'                => [],
                            'utcTime'                  => $reservationService->getBookingPeriods(
                                $event,
                                $booking,
                                $event
                            ),
                            'paymentId'                => $payment->getId()->getValue(),
                            'packageCustomerId'        => 0,
                            'payment'                  => $payment ? $payment->toArray() : null,
                            ]
                        );

                        break;

                    case (Entities::PACKAGE):
                        /** @var Collection $packageCustomerServices */
                        $packageCustomerServices = $packageCustomerServiceRepository->getByCriteria(
                            ['packagesCustomers' => [$payment->getPackageCustomerId()->getValue()]]
                        );

                        $packageId = null;

                        $customerId = null;

                        /** @var PackageCustomerService $packageCustomerService */
                        foreach ($packageCustomerServices->getItems() as $packageCustomerService) {
                            $paymentStatus = $packageCustomerService->getPackageCustomer()->getPrice()->getValue() >
                                $payment->getAmount()->getValue() ?
                                PaymentStatus::PARTIALLY_PAID : PaymentStatus::PAID;

                            $paymentRepository->updateFieldById(
                                $payment->getId()->getValue(),
                                $paymentStatus,
                                'status'
                            );
                            $payment->setStatus(new PaymentStatus($paymentStatus));

                            $packageId = $packageCustomerService->getPackageCustomer()->getPackageId()->getValue();

                            $customerId = $packageCustomerService->getPackageCustomer()->getCustomerId()->getValue();

                            break;
                        }

                        /** @var Package $package */
                        $package = $packageId ? $packageRepository->getById($packageId) : null;

                        $packageData = [];

                        /** @var Collection $appointments */
                        $appointments = $appointmentRepository->getFiltered(
                            ['packageCustomerServices' => $packageCustomerServices->keys()]
                        );

                        $firstBooking = null;

                        /** @var Appointment $packageAppointment */
                        foreach ($appointments->getItems() as $packageAppointment) {
                            if ($packageAppointment->getLocationId()) {
                                /** @var Location $location */
                                $location = $locationRepository->getById($packageAppointment->getLocationId()->getValue());

                                $packageAppointment->setLocation($location);
                            }

                            /** @var CustomerBooking $packageBooking */
                            foreach ($packageAppointment->getBookings()->getItems() as $packageBooking) {
                                if (
                                    $packageBooking->getPackageCustomerService() &&
                                    in_array(
                                        $packageBooking->getPackageCustomerService()->getId()->getValue(),
                                        $packageCustomerServices->keys()
                                    )
                                ) {
                                    /** @var Service $packageService */
                                    $packageService = $bookableAS->getAppointmentService(
                                        $packageAppointment->getServiceId()->getValue(),
                                        $packageAppointment->getProviderId()->getValue()
                                    );

                                    $appointmentStatusChanged = $appointmentAS->isAppointmentStatusChangedWithBooking(
                                        $packageService,
                                        $packageAppointment,
                                        null,
                                        $packageBooking
                                    );

                                    if ($firstBooking === null) {
                                        $firstBooking = $packageBooking;
                                    }

                                    $packageData[] = [
                                    'type'                     => Entities::APPOINTMENT,
                                    Entities::APPOINTMENT      => $packageAppointment->toArray(),
                                    Entities::BOOKING          => $packageBooking->toArray(),
                                    'appointmentStatusChanged' => $appointmentStatusChanged,
                                    'utcTime'                  => $reservationService->getBookingPeriods(
                                        $packageAppointment,
                                        $packageBooking,
                                        $packageService
                                    ),
                                    ];
                                }
                            }
                        }

                        /** @var AbstractUser $customer */
                        $customer = $customerRepository->getById($customerId);

                        $customerCabinetUrl = '';

                        if ($customer->getEmail() && $customer->getEmail()->getValue()) {
                            /** @var \AmeliaBooking\Application\Services\Helper\HelperService $helperService */
                            $helperService = $this->container->get('application.helper.service');

                            $locale = '';

                            if ($firstBooking && $firstBooking->getInfo() && $firstBooking->getInfo()->getValue()) {
                                $info = json_decode($firstBooking->getInfo()->getValue(), true);

                                $locale = !empty($info['locale']) ? $info['locale'] : '';
                            }

                            $customerCabinetUrl = $helperService->getCustomerCabinetUrl(
                                $customer->getEmail()->getValue(),
                                'email',
                                null,
                                null,
                                $locale
                            );
                        }

                        /** @var PackageCustomerRepository $packageCustomerRepository */
                        $packageCustomerRepository = $this->container->get('domain.bookable.packageCustomer.repository');

                        $packageCustomerToken = null;

                        if ($payment->getPackageCustomerId()) {
                            $packageToken = $packageCustomerRepository->getToken(
                                $payment->getPackageCustomerId()->getValue()
                            );

                            $packageCustomerToken = !empty($packageToken['token']) ? $packageToken['token'] : null;
                        }

                        $result->setData(
                            [
                            'type'                     => Entities::PACKAGE,
                            'customer'                 => $customer->toArray(),
                            'packageId'                => $packageId,
                            'recurring'                => [],
                            'package'                  => $packageData,
                            'appointmentStatusChanged' => false,
                            'utcTime'                  => [],
                            'bookable'                 => $package ? $package->toArray() : null,
                            'paymentId'                => $payment->getId()->getValue(),
                            'packageCustomerId'        => $payment->getPackageCustomerId() ?
                                $payment->getPackageCustomerId()->getValue() : null,
                            'packageCustomerToken'     => $packageCustomerToken,
                            'payment'                  => $payment ? $payment->toArray() : null,
                            'customerCabinetUrl'       => $customerCabinetUrl,
                            ]
                        );

                        break;
                }

                if (!empty($cacheData['subscribeToMailchimp'])) {
                    $resultData = $result->getData();

                    $mailchimpCustomerData = !empty($resultData['customer']) ? $resultData['customer'] : [];
                }

                $cacheDataArray = json_decode($cache->getData()->getValue(), true);

                $responseData = $result->getData();

                $cachedResponse = !empty($cacheDataArray['response']) && is_array($cacheDataArray['response'])
                    ? $cacheDataArray['response']
                    : [];

                foreach (['isCart', 'isPackageAppointment'] as $preservedField) {
                    if (
                        empty($responseData[$preservedField]) &&
                        !empty($cachedResponse[$preservedField])
                    ) {
                        $responseData[$preservedField] = $cachedResponse[$preservedField];
                    }
                }

                if (empty($responseData['packageCustomerToken']) && !empty($cachedResponse['packageCustomerToken'])) {
                    $responseData['packageCustomerToken'] = $cachedResponse['packageCustomerToken'];
                }

                $result->setData($responseData);

                $trigger = $cacheDataArray && isset($cacheDataArray['request']['trigger'])
                ? $cacheDataArray['request']['trigger']
                : (
                    $cacheDataArray && isset($cacheDataArray['request']['form']['shortcode']['trigger'])
                    ? $cacheDataArray['request']['form']['shortcode']['trigger']
                    : ''
                );

                $cache->setData(
                    new Json(
                        json_encode(
                            array_merge(
                                json_decode($cache->getData()->getValue(), true),
                                [
                                'response' => $result->getData(),
                                'status'   => $status,
                                ]
                            )
                        )
                    )
                );

                $cacheRepository->update($cache->getId()->getValue(), $cache);

            /** @var SettingsService $settingsService */
                $settingsService = $this->container->get('domain.settings.service');

                $shouldRunPostBookingActions =
                $settingsService->getSetting('general', 'runInstantPostBookingActions') || $trigger;

                $cacheRepository->commit();
                $transactionOpen = false;

                if ($shouldRunPostBookingActions) {
                    try {
                        $reservationService->runPostBookingActions($result);
                    } catch (Exception $postBookingException) {
                        $this->container->getLoggerService()->error(
                            'Post-booking actions failed after payment finalization for cache #' .
                            $cache->getId()->getValue() . ': ' . $postBookingException->getMessage()
                        );
                    }
                }
            } elseif (
                (!is_array($cacheData) || !isset($cacheData['status']) || $cacheData['status'] === 'pending') &&
                (in_array($status, ['canceled', 'failed', 'expired'], true))
            ) {
                if (!$payment) {
                    if ($status === 'expired') {
                        $cacheRepository->delete($cache->getId()->getValue());
                    } else {
                        $cache->setData(
                            new Json(
                                json_encode(
                                    array_merge(
                                        is_array($cacheData) ? $cacheData : [],
                                        [
                                            'status' => $status,
                                        ]
                                    )
                                )
                            )
                        );

                        $cache->setPaymentId(null);

                        $cacheRepository->update($cache->getId()->getValue(), $cache);
                    }

                    $cacheRepository->commit();
                    $transactionOpen = false;

                    $result->setMessage('Payment not found');
                    $result->setData(
                        [
                            'ignored'           => true,
                            'paymentSuccessful' => false,
                        ]
                    );

                    return $result;
                }

                switch ($type) {
                    case (Entities::APPOINTMENT):
                        /** @var Appointment $appointment */
                        $appointment = $appointmentRepository->getByPaymentId($payment->getId()->getValue());

                        /** @var Collection $nextPayments */
                        $nextPayments = $paymentRepository->getByEntityId($payment->getId()->getValue(), 'parentId');

                        /** @var Payment $nextPayment */
                        foreach ($nextPayments->getItems() as $nextPayment) {
                            /** @var Appointment $nextAppointment */
                            $nextAppointment = $appointmentRepository->getByPaymentId($nextPayment->getId()->getValue());

                            /** @var CustomerBooking $nextBooking */
                            $nextBooking = $nextAppointment->getBookings()->getItem(
                                $nextPayment->getCustomerBookingId()->getValue()
                            );

                            switch ($status) {
                                case ('expired'):
                                    $nextBooking->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                    $bookingRepository->updateFieldById(
                                        $nextBooking->getId()->getValue(),
                                        BookingStatus::CANCELED,
                                        'status'
                                    );

                                    if ($nextAppointment->getBookings()->length() === 1) {
                                            $nextAppointment->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                            $appointmentRepository->updateFieldById(
                                                $nextAppointment->getId()->getValue(),
                                                BookingStatus::CANCELED,
                                                'status'
                                            );
                                    }

                                    break;

                                case ('failed'):
                                case ('canceled'):
                                    if ($nextAppointment->getBookings()->length() === 1) {
                                        $appointmentAS->delete($nextAppointment);
                                    } else {
                                        $bookingAS->delete($nextBooking);
                                    }

                                    break;
                            }
                        }

                        /** @var CustomerBooking $booking */
                        $booking = $appointment->getBookings()->getItem($payment->getCustomerBookingId()->getValue());

                        switch ($status) {
                            case ('expired'):
                                $booking->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                $bookingRepository->updateFieldById(
                                    $booking->getId()->getValue(),
                                    BookingStatus::CANCELED,
                                    'status'
                                );

                                if ($appointment->getBookings()->length() === 1) {
                                    $appointment->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                    $appointmentRepository->updateFieldById(
                                        $appointment->getId()->getValue(),
                                        BookingStatus::CANCELED,
                                        'status'
                                    );
                                }

                                break;

                            case ('failed'):
                            case ('canceled'):
                                if ($appointment->getBookings()->length() === 1) {
                                    $appointmentAS->delete($appointment);
                                } else {
                                    $bookingAS->delete($booking);
                                }

                                break;
                        }

                        break;

                    case (Entities::EVENT):
                        /** @var Event $event */
                        $event = $reservationService->getReservationByBookingId(
                            $payment->getCustomerBookingId()->getValue()
                        );

                        /** @var CustomerBooking $booking */
                        $booking = $event->getBookings()->getItem($payment->getCustomerBookingId()->getValue());

                        switch ($status) {
                            case ('expired'):
                                $booking->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                $bookingRepository->updateFieldById(
                                    $booking->getId()->getValue(),
                                    BookingStatus::CANCELED,
                                    'status'
                                );

                                break;

                            case ('failed'):
                            case ('canceled'):
                                $eventApplicationService->deleteEventBooking($booking);

                                break;
                        }



                        break;

                    case (Entities::PACKAGE):
                        /** @var Collection $packageCustomerServices */
                        $packageCustomerServices = $packageCustomerServiceRepository->getByCriteria(
                            ['packagesCustomers' => [$payment->getPackageCustomerId()->getValue()]]
                        );

                        /** @var Collection $appointments */
                        $appointments = $appointmentRepository->getFiltered(
                            ['packageCustomerServices' => $packageCustomerServices->keys()]
                        );

                        /** @var PackageApplicationService $packageApplicationService */
                        $packageApplicationService = $this->container->get('application.bookable.package');

                        /** @var Appointment $appointment */
                        foreach ($appointments->getItems() as $appointment) {
                            /** @var Appointment $packageAppointment */
                            $packageAppointment = $appointmentRepository->getById($appointment->getId()->getValue());

                            /** @var CustomerBooking|null $packageBooking */
                            $packageBooking = null;

                            /** @var CustomerBooking $appointmentBooking */
                            foreach ($packageAppointment->getBookings()->getItems() as $appointmentBooking) {
                                if (
                                    $packageBooking === null &&
                                    $appointmentBooking->getPackageCustomerService() &&
                                    in_array(
                                        $appointmentBooking->getPackageCustomerService()->getId()->getValue(),
                                        $packageCustomerServices->keys(),
                                        true
                                    )
                                ) {
                                    $packageBooking = $appointmentBooking;
                                }
                            }

                            switch ($status) {
                                case ('expired'):
                                    if (!$packageBooking) {
                                        break;
                                    }

                                    $packageBooking->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                    $bookingRepository->updateFieldById(
                                        $packageBooking->getId()->getValue(),
                                        BookingStatus::CANCELED,
                                        'status'
                                    );

                                    if ($packageAppointment->getBookings()->length() === 1) {
                                        $packageAppointment->setStatus(new BookingStatus(BookingStatus::CANCELED));

                                        $appointmentRepository->updateFieldById(
                                            $packageAppointment->getId()->getValue(),
                                            BookingStatus::CANCELED,
                                            'status'
                                        );
                                    }

                                    break;

                                case ('failed'):
                                case ('canceled'):
                                    if ($packageAppointment->getBookings()->length() === 1) {
                                        $appointmentAS->delete($packageAppointment);
                                    } elseif ($packageBooking) {
                                        $bookingAS->delete($packageBooking);
                                    }

                                    break;
                            }
                        }

                        switch ($status) {
                            case ('expired'):
                                break;

                            case ('failed'):
                            case ('canceled'):
                                $packageApplicationService->deletePackageCustomer($packageCustomerServices);

                                break;
                        }

                        break;
                }

                switch ($status) {
                    case ('expired'):
                        $cacheRepository->delete($cache->getId()->getValue());

                        break;

                    case ('failed'):
                    case ('canceled'):
                        $cache->setData(
                            new Json(
                                json_encode(
                                    array_merge(
                                        json_decode($cache->getData()->getValue(), true),
                                        [
                                        'status' => $status,
                                        ]
                                    )
                                )
                            )
                        );

                        $cache->setPaymentId(null);

                        $cacheRepository->update($cache->getId()->getValue(), $cache);

                        break;
                }
            } elseif (in_array($status, ['canceled', 'failed', 'expired'], true)) {
                $result->setMessage('Payment already finalized');
                $result->setData(
                    [
                        'ignored' => true,
                        'status'  => is_array($cacheData) && isset($cacheData['status'])
                            ? $cacheData['status']
                            : null,
                    ]
                );
            }

            if ($transactionOpen) {
                $cacheRepository->commit();
                $transactionOpen = false;
            }

            // Subscribing after the commit keeps the external call out of the transaction and off a rolled back booking
            if ($mailchimpCustomerData !== null) {
                try {
                    $this->addMailchimpSubscriber($mailchimpCustomerData);
                } catch (Exception $e) {
                    $this->container->getLoggerService()->error(
                        'Failed to subscribe customer to Mailchimp after payment',
                        ['error' => $e->getMessage()]
                    );
                }
            }

            return $result;
        } catch (Exception $e) {
            if ($transactionOpen) {
                $cacheRepository->rollback();
            }

            throw $e;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function createSquarePaymentIntent($paymentData, $paymentAmount, $reservation)
    {
        $additionalInformation = $this->getBookingInformationForPaymentSettings(
            $reservation,
            PaymentType::SQUARE
        );

        /** @var SquareService $squareService */
        $squareService = $this->container->get('infrastructure.payment.square.service');

        $amount = $this->prepareSquareAmount($paymentData, $paymentAmount);

        return $squareService->preparePaymentRequest($paymentData['data'], $amount, $reservation, $additionalInformation);
    }

    private function prepareSquareAmount($paymentData, $amount)
    {
        $currencies = new ISOCurrencies();
        $moneyParser = new DecimalMoneyParser($currencies);

        return $moneyParser->parse(
            (string)$amount,
            new Currency($paymentData['currency'])
        )->getAmount();
    }
}
