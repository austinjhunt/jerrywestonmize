<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Payment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Services\Payment\SquareService;
use Exception;

/**
 * Class CalculatePaymentAmountCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Payment
 */
class CalculatePaymentAmountCommandHandler extends CommandHandler
{
    /**
     * @param CalculatePaymentAmountCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function handle(CalculatePaymentAmountCommand $command)
    {
        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        $requestData = $this->getAppointmentData($command->getFields());

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($command->getField('type'));

        $squareSettings = $settingsService->getCategorySettings('payments')['square'];

        $reservation = $reservationService->getNew(true, true, true);

        $reservationService->processBooking(
            $result,
            $requestData,
            $reservation,
            false
        );

        if ($result->getResult() === CommandResult::RESULT_ERROR) {
            return $result;
        }

        $paymentAmount = $reservationService->getReservationPaymentAmount($reservation);

        $countryCode = null;
        if ($squareSettings['enabled']) {
            /** @var SquareService $squareService */
            $squareService = $this->container->get('infrastructure.payment.square.service');

            $countryCode = $squareService->getCountryCodeByLocationId($squareSettings['locationId']);
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData(
            [
                'amount'      => $paymentAmount,
                'currency'    => $settingsService->getCategorySettings('payments')['currency'],
                'countryCode' => $countryCode,
            ]
        );

        return $result;
    }
}
