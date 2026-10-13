<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\BookingCancellationException;
use AmeliaBooking\Domain\Common\Exceptions\BookingUnavailableException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Booking\Event\Event;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Reservation\ReservationServiceInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/**
 * Class UpdateBookingStatusCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class UpdateBookingStatusCommandHandler extends CommandHandler
{
    /**
     * @param UpdateBookingStatusCommand $command
     *
     * @return CommandResult
     *
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws QueryExecutionException
     */
    public function handle(UpdateBookingStatusCommand $command)
    {
        $result = new CommandResult();

        /** @var UserApplicationService $userAS */
        $userAS = $this->getContainer()->get('application.user.service');

        /** @var AbstractUser $user */
        $user = $this->container->get('logged.in.user');

        if (!$user) {
            throw new AccessDeniedException('You are not allowed to update booking status because you are not logged in');
        }

        $canWriteAppointments = $command->getPermissionService()->currentUserCanWrite(Entities::APPOINTMENTS);
        $canWriteEvents = $command->getPermissionService()->currentUserCanWrite(Entities::EVENTS);

        if (!$canWriteAppointments && !$canWriteEvents) {
            throw new AccessDeniedException('You are not allowed to update booking status');
        }

        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        /** @var CustomerBooking|null $booking */
        $booking = $bookingRepository->getById((int)$command->getArg('id'));

        if (!$booking) {
            if ($userAS->isProvider($user)) {
                throw new AccessDeniedException("You are not allowed to update another provider's booking status");
            }

            throw new NotFoundException('Booking not found');
        }

        $type = $booking->getAppointmentId() ? Entities::APPOINTMENT : Entities::EVENT;

        if (
            ($type === Entities::APPOINTMENT && !$canWriteAppointments) ||
            ($type === Entities::EVENT && !$canWriteEvents)
        ) {
            throw new AccessDeniedException('You are not allowed to update booking status');
        }

        /** @var ReservationServiceInterface $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        if ($type === Entities::APPOINTMENT && $userAS->isProvider($user)) {
            /** @var Appointment $appointment */
            $appointment = $reservationService->getReservationByBookingId($booking->getId()->getValue());

            if (
                !$appointment ||
                !$appointment->getProviderId() ||
                $appointment->getProviderId()->getValue() !== $user->getId()->getValue()
            ) {
                throw new AccessDeniedException("You are not allowed to update another provider's booking status");
            }
        }

        if ($type === Entities::EVENT && $userAS->isProvider($user)) {
            /** @var SettingsService $settingsDS */
            $settingsDS = $this->container->get('domain.settings.service');

            /** @var Event $event */
            $event = $reservationService->getReservationByBookingId($booking->getId()->getValue());

            if (
                !$event ||
                !$settingsDS->getSetting('roles', 'allowWriteEvents') ||
                (
                    !$event->getProviders()->keyExists($user->getId()->getValue()) &&
                    (!$event->getOrganizerId() || $event->getOrganizerId()->getValue() !== $user->getId()->getValue())
                )
            ) {
                throw new AccessDeniedException("You are not allowed to update another provider's booking status");
            }
        }

        do_action('amelia_before_booking_' . $command->getField('status'), $booking->toArray());

        $oldStatus = $booking->getStatus()->getValue();

        try {
            $bookingData = $reservationService->updateStatus($booking, $command->getField('status'), false);
        } catch (BookingCancellationException $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('You are not allowed to update booking status');
            $result->setData(
                [
                    'updateBookingUnavailable' => true
                ]
            );

            return $result;
        } catch (BookingUnavailableException $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Maximum capacity reached');
            $result->setData(
                [
                    'updateBookingUnavailable' => true,
                    'oldStatus' => $oldStatus,
                    'message' =>
                        BackendStrings::get('maximum_capacity_reached')
                ]
            );

            return $result;
        }

        // Ensure provider's zoomUserId is included for Zoom integration
        if ($type === Entities::APPOINTMENT && isset($bookingData[Entities::APPOINTMENT])) {
            $appointment = &$bookingData[Entities::APPOINTMENT];

            if (isset($appointment['providerId']) && empty($appointment['provider']['zoomUserId'])) {
                /** @var ProviderRepository $providerRepository */
                $providerRepository = $this->container->get('domain.users.providers.repository');

                $provider = $providerRepository->getById($appointment['providerId']);

                if ($provider && $provider->getZoomUserId()) {
                    $appointment['provider']['zoomUserId'] = $provider->getZoomUserId()->getValue();
                }
            }
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully updated booking status');
        $result->setData(
            array_merge(
                $bookingData,
                [
                    'type'    => $type,
                    'status'  => $command->getField('status'),
                    'message' =>
                        BackendStrings::get('booking_status_changed')
                ]
            )
        );

        do_action('amelia_after_booking_' . $command->getField('status'), $bookingData);

        return $result;
    }
}
