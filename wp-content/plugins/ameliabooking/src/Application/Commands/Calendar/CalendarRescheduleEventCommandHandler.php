<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Calendar;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Bookable\BookableApplicationService;
use AmeliaBooking\Application\Services\Booking\AppointmentApplicationService;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Application\Services\Payment\PaymentApplicationService;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\BooleanValueObject;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use AmeliaBooking\Infrastructure\WP\Translations\FrontendStrings;
use Interop\Container\Exception\ContainerException;
use Psr\Container\ContainerExceptionInterface;

class CalendarRescheduleEventCommandHandler extends CommandHandler
{
    /**
     * @var array
     */
    public $mandatoryFields = [
        'bookingStart',
        'appointmentId'
    ];

    /**
     * @throws ContainerException
     * @throws InvalidArgumentException
     * @throws ContainerExceptionInterface
     * @throws QueryExecutionException
     */
    public function handle(CalendarRescheduleEventCommand $command): CommandResult
    {
        $this->checkMandatoryFields($command);

        $result = new CommandResult();

        /** @var UserApplicationService $userAS */
        $userAS = $this->container->get('application.user.service');
        /** @var AppointmentRepository $appointmentRepo */
        $appointmentRepo = $this->container->get('domain.booking.appointment.repository');
        /** @var AppointmentApplicationService $appointmentAS */
        $appointmentAS = $this->container->get('application.booking.appointment.service');
        /** @var BookableApplicationService $bookableAS */
        $bookableAS = $this->container->get('application.bookable.service');
        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');
        /** @var PaymentApplicationService $paymentAS */
        $paymentAS = $this->container->get('application.payment.service');

        try {
            /** @var AbstractUser $user */
            $user = $command->getUserApplicationService()->authorization(
                $command->getPage() === 'cabinet' ? $command->getToken() : null,
                $command->getCabinetType()
            );
        } catch (AuthorizationException $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setData(
                [
                    'reauthorize' => true
                ]
            );

            return $result;
        }

        /** @var Appointment $appointment */
        $appointment = $appointmentRepo->getById($command->getField('appointmentId'));

        $initialBookingStart = $appointment->getBookingStart()->getValue();
        $initialBookingEnd   = $appointment->getBookingEnd()->getValue();

        /** @var Service $service */
        $service = $bookableAS->getAppointmentService(
            $appointment->getServiceId()->getValue(),
            $appointment->getProviderId()->getValue()
        );

        $bookingStart = $command->getField('bookingStart');

        $bookingStartInUtc = DateTimeService::getCustomDateTimeObject(
            $bookingStart
        )->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i');

        $appointment->setBookingStart(
            new DateTimeValue(
                DateTimeService::getCustomDateTimeObject(
                    $bookingStart
                )
            )
        );

        $appointment->setBookingEnd(
            new DateTimeValue(
                DateTimeService::getCustomDateTimeObject($bookingStart)
                    ->modify('+' . $appointmentAS->getAppointmentLengthTime($appointment, $service) . ' second')
            )
        );

        if (!$appointmentAS->canBeBooked($appointment, $userAS->isCustomer($user), null, null)) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage(FrontendStrings::getCommonStrings()['time_slot_unavailable']);
            $result->setData(
                [
                    'timeSlotUnavailable' => true
                ]
            );

            return $result;
        }

        do_action('amelia_before_booking_rescheduled', $appointment->toArray());

        $appointmentRepo->update($command->getField('appointmentId'), $appointment);

        foreach ($appointment->getBookings()->getItems() as $booking) {
            $paymentAS->updateBookingPaymentDate($booking, $bookingStartInUtc);
        }

        $appointment->setRescheduled(new BooleanValueObject(true));

        $bookingAS->bookingRescheduled(
            $appointment->getId()->getValue(),
            Entities::APPOINTMENT,
            null,
            Entities::CUSTOMER
        );

        $bookingAS->bookingRescheduled(
            $appointment->getId()->getValue(),
            Entities::APPOINTMENT,
            $appointment->getProviderId()->getValue(),
            Entities::PROVIDER
        );


        do_action('amelia_after_booking_rescheduled', $appointment->toArray());

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully updated appointment time');
        $result->setData(
            [
                Entities::APPOINTMENT        => $appointment->toArray(),
                'initialAppointmentDateTime' => [
                    'bookingStart' => $initialBookingStart->format('Y-m-d H:i:s'),
                    'bookingEnd'   => $initialBookingEnd->format('Y-m-d H:i:s'),
                ],
            ]
        );

        return $result;
    }
}
