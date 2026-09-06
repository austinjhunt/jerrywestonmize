<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Services\Helper\HelperService;
use AmeliaBooking\Application\Services\Reservation\AbstractReservationService;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Reservation;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use Exception;

/**
 * Class AddBookingCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class AddBookingCommandHandler extends CommandHandler
{
    /**
     * @var array
     */
    public $mandatoryFields = [
        'bookings',
    ];

    /**
     * @param AddBookingCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function handle(AddBookingCommand $command): CommandResult
    {
        $this->checkMandatoryFields($command);

        $appointmentData = $this->getAppointmentData(
            $command->getFields(),
            empty($command->getFields()['bookings'][0]['packageCustomerService']['id'])
                ? [
                    PaymentType::ON_SITE,
                    PaymentType::PAY_PAL,
                    PaymentType::STRIPE,
                    PaymentType::SQUARE,
                    PaymentType::RAZORPAY,
                ]
                : null
        );

        /** @var AbstractReservationService $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get(
            $command->getField('type') ?: Entities::APPOINTMENT
        );

        $appointmentData = apply_filters('amelia_before_booking_added_filter', $appointmentData);

        do_action('amelia_before_booking_added', $appointmentData);

        $validateCoupon = true;

        if (
            $command->getField('validateCoupon') === false &&
            $this->getContainer()->getPermissionsService()->currentUserCanWrite(Entities::COUPONS)
        ) {
            $validateCoupon = false;
        }

        /** @var Reservation $reservation */
        $reservation = $reservationService->getNew(
            $validateCoupon,
            true,
            true
        );

        // package appointment booking is available only for logged in users (WP dashboard or panel)
        if (!empty($appointmentData['bookings'][0]['packageCustomerService']['id'])) {
            if ($command->getToken()) {
                /** @var UserApplicationService $userAS */
                $userAS = $this->container->get('application.user.service');

                try {
                    /** @var AbstractUser $user */
                    $user = $userAS->authorization(
                        $command->getToken(),
                        Entities::CUSTOMER
                    );
                } catch (AuthorizationException $e) {
                    $result = new CommandResult();

                    $result->setResult(CommandResult::RESULT_ERROR);
                    $result->setData(
                        [
                            'reauthorize' => true
                        ]
                    );

                    return $result;
                }
            } else {
                /** @var AbstractUser $user */
                $user = $this->container->get('logged.in.user');
            }

            // user must be admin, manager or customer (same as the customer in the booking)
            if (
                !$user ||
                (
                    $user->getType() !== AbstractUser::USER_ROLE_ADMIN &&
                    $user->getType() !== AbstractUser::USER_ROLE_MANAGER &&
                    $user->getId()->getValue() !== (int)$appointmentData['bookings'][0]['customer']['id']
                )
            ) {
                $result = new CommandResult();

                $result->setResult(CommandResult::RESULT_ERROR);

                return $result;
            }
            $appointmentData['payment'] = null;
        }

        $result = $reservationService->processRequest($appointmentData, $reservation, true);

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        if ($settingsService->getSetting('general', 'runInstantPostBookingActions') || !empty($command->getField('runInstantPostBookingActions'))) {
            $reservationService->runPostBookingActions($result);
        }

        if ($result->getResult() === CommandResult::RESULT_SUCCESS && $reservation) {
            $data = $result->getData();
            $data['wpAmeliaNonce'] = wp_create_nonce('ajax-nonce');

            /** @var HelperService $helperService */
            $helperService = $this->container->get('application.helper.service');

            /** @var AbstractUser $customer */
            $customer = $reservation->getCustomer();

            if ($customer && $customer->getEmail() && $customer->getEmail()->getValue()) {
                $data['customerCabinetUrl'] = $helperService->getCustomerCabinetUrl(
                    $customer->getEmail()->getValue(),
                    $reservation->isNewUser()->getValue() ? 'email' : null,
                    null,
                    null,
                    $reservation->getLocale() ? $reservation->getLocale()->getValue() : '',
                    $reservation->isNewUser()->getValue()
                );

                if (!empty($appointmentData['packageBookingFromBackend'])) {
                    $data['packageBookingFromBackend'] = $appointmentData['packageBookingFromBackend'];
                }
            }

            $result->setData($data);

            do_action('amelia_after_booking_added', $result ? $result->getData() : null);
        }

        return $result;
    }
}
