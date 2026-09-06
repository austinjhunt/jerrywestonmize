<?php

namespace AmeliaBooking\Application\Commands;

use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Common\Exceptions\PaymentValidationException;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Infrastructure\Common\Container;
use Exception;

/**
 * Class CommandHandler
 *
 * @package AmeliaBooking\Application\Commands
 */
abstract class CommandHandler
{
    /**
     * @var Container
     */
    protected $container;

    protected $mandatoryFields = [];

    /**
     * @param Command $command
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function checkMandatoryFields($command): void
    {
        $missingFields = [];

        foreach ($this->mandatoryFields as $field) {
            if ($command->getField($field) === null) {
                $missingFields[] = $field;
            }
        }
        if (!empty($missingFields)) {
            throw new InvalidArgumentException(
                'Mandatory fields not passed! Missing: ' . implode(', ', $missingFields)
            );
        }
    }

    /**
     * Normalize a booking request payload and replace its payment object with the
     * server-derived equivalent processBooking / processRequest expect.
     *
     * Call this after checkMandatoryFields on handlers that receive a booking request.
     * Pass the payload to normalize - usually $command->getFields(), or a cached/mutated copy.
     *
     * @param array      $data
     * @param array|null $allowedGateways Gateways this endpoint accepts, defaults to null (skip payment data validation)
     *
     * @return array
     * @throws PaymentValidationException
     * @throws Exception
     */
    protected function getAppointmentData(array $data, $allowedGateways = null): array
    {
        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');

        $data = $bookingAS->getAppointmentData($data);

        // if no payment data is provided, use the default on-site payment
        // if call is ment to check payment data, it will be validated later
        if (!isset($data['payment'])) {
            $data['payment'] = ['gateway' => PaymentType::ON_SITE];
        }

        // skip validation if no allowed gateways are provided
        if ($allowedGateways === null) {
            return $data;
        }

        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        $paymentAS->validateRequestPaymentData($data, $allowedGateways);

        return $data;
    }

    /**
     * CommandHandler constructor.
     *
     * @param Container $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}
