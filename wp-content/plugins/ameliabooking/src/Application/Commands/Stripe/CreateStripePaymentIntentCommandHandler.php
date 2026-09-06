<?php

namespace AmeliaBooking\Application\Commands\Stripe;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
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
use AmeliaBooking\Infrastructure\Services\Payment\CurrencyService;
use AmeliaBooking\Infrastructure\Services\Payment\StripeService;
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
    public function handle(CreateStripePaymentIntentCommand $command)
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

        $recaptchaResult = $reservationService->validateFrontEndRecaptcha($appointmentData);

        if ($recaptchaResult !== null) {
            return $recaptchaResult;
        }

        $reservation = $reservationService->getNew(true, true, false);

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

        $token = new Token();

        /** @var Cache $cache */
        $cache = CacheFactory::create(
            [
                'name' => $token->getValue(),
                'data' => json_encode(
                    [
                        'status'         => null,
                        'request'        => $command->getField('componentProps'),
                        'bookingRequest' => $this->buildBookingRequestForCache($command->getFields(), $appointmentData, $type),
                    ]
                ),
            ]
        );

        $cacheId = $cacheRepository->add($cache);

        $cache->setId(new Id($cacheId));

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

        try {
            $intentData = $stripeService->createPaymentIntent(
                [
                    'amount'       => $currencyService->getAmountInFractionalUnit(new Price($paymentAmount)),
                    'currency'     => $this->container->get('domain.settings.service')->getCategorySettings('payments')['currency'],
                    'description'  => $additionalInformation['description'] ?:
                        $reservation->getBookable()->getName()->getValue(),
                    'metaData'     => $additionalInformation['metaData'] ?: [],
                    'returnUrl'    => $returnUrl,
                    'receiptEmail' => !empty($appointmentData['bookings'][0]['customer']['email']) ?
                        $appointmentData['bookings'][0]['customer']['email'] : '',
                ],
                $transfers
            );
        } catch (Exception $e) {
            $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_PAYMENT)->error(
                'Amelia Stripe: failed to create PaymentIntent',
                ['exception' => $e->getMessage()]
            );

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

    /**
     * @param array $fields
     * @param array $appointmentData
     * @param string $type
     *
     * @return array
     */
    private function buildBookingRequestForCache($fields, $appointmentData, $type)
    {
        $bookingRequest = $fields;

        unset($bookingRequest['componentProps']);

        // the cached request is replayed by CompleteStripePaymentIntentCommandHandler, so it has to carry
        // the validated payment object instead of the raw one that came in with the request
        $bookingRequest['payment'] = $appointmentData['payment'];

        if (isset($bookingRequest['payment']['data']['createPaymentIntent'])) {
            unset($bookingRequest['payment']['data']['createPaymentIntent']);
        }

        if (!empty($appointmentData['bookings'])) {
            $bookingRequest['bookings'] = $appointmentData['bookings'];
        }

        $bookingRequest['type'] = $type;

        return $bookingRequest;
    }

    private function getStripeCallbackUrl($identifier, $returnUrl)
    {
        return (AMELIA_DEV ? str_replace('localhost', AMELIA_NGROK_URL, AMELIA_ACTION_URL) : AMELIA_ACTION_URL) .
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
