<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Reservation\AbstractReservationService;
use AmeliaBooking\Domain\Entity\Bookable\Service\PackageCustomer;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\Payment\Payment;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Domain\ValueObjects\String\PaymentStatus;
use AmeliaBooking\Domain\ValueObjects\String\PaymentType;
use AmeliaBooking\Infrastructure\Common\Exceptions\NotFoundException;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use Slim\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class DeleteBookingRemotelyCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class DeleteBookingRemotelyCommandHandler extends CommandHandler
{
    /**
     * @param DeleteBookingRemotelyCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws ContainerValueNotFoundException
     * @throws AccessDeniedException
     * @throws QueryExecutionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function handle(DeleteBookingRemotelyCommand $command)
    {
        $result = new CommandResult();

        $type = $command->getField('type') ?: Entities::APPOINTMENT;

        $bookingId = $command->getArg('id');

        $token = $command->getField('token');

        $statusOnly = filter_var($command->getField('statusOnly'), FILTER_VALIDATE_BOOLEAN);

        if (!$token) {
            throw new AccessDeniedException('No token sent.');
        }

        /** @var AbstractReservationService $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var CustomerBooking|PackageCustomer|null $booking */
        $booking = $reservationService->getBooking($bookingId);

        if (!$booking || !$booking->getToken() || $booking->getToken()->getValue() !== $token) {
            throw new AccessDeniedException('Invalid token sent.');
        }

        $authorizationPayment = $this->getAuthorizationPayment($booking);

        $createdAtSource = null;

        if ($authorizationPayment && $authorizationPayment->getCreated()) {
            $createdAtSource = $authorizationPayment->getCreated()->getValue()->format('Y-m-d H:i:s');
        } elseif ($booking instanceof CustomerBooking && $booking->getCreated()) {
            $createdAtSource = $booking->getCreated()->getValue()->format('Y-m-d H:i:s');
        } elseif ($booking instanceof PackageCustomer && $booking->getPurchased()) {
            $createdAtSource = $booking->getPurchased()->getValue()->format('Y-m-d H:i:s');
        }

        if (!$createdAtSource) {
            throw new AccessDeniedException('Token expired.');
        }

        $now = new \DateTime();
        $createdAt = DateTimeService::getCustomDateTimeObjectInUtc($createdAtSource);
        $diffInSeconds = $now->getTimestamp() - $createdAt->getTimestamp();
        if ($diffInSeconds > 1800) {
            throw new AccessDeniedException('Token expired.');
        }

        $pending = $this->isStillPending($booking);

        if ($statusOnly) {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Booking remote status retrieved');
            $result->setData(
                [
                    'pending' => $pending,
                ]
            );

            return $result;
        }

        if (!$pending) {
            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Booking is not pending; skipped delete');
            $result->setData(
                [
                    'deleted' => false,
                    'pending' => false,
                ]
            );

            return $result;
        }

        try {
            $reservationService->deleteBooking($bookingId);
        } catch (\Exception $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage($e->getMessage());

            return $result;
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully deleted booking');
        $result->setData(
            [
                'deleted' => true,
                'pending' => true,
            ]
        );

        return $result;
    }

    /**
     * @param CustomerBooking|PackageCustomer $booking
     *
     * @return Payment[]
     */
    private function getAssociatedPayments($booking)
    {
        if (!$booking->getPayments() || $booking->getPayments()->length() === 0) {
            return [];
        }

        $payments = [];

        foreach ($booking->getPayments()->getItems() as $payment) {
            if ($payment instanceof Payment) {
                $payments[] = $payment;
            }
        }

        return $payments;
    }

    /**
     * Prefer a pending Razorpay payment for token expiration; otherwise use the newest payment.
     *
     * @param CustomerBooking|PackageCustomer $booking
     *
     * @return Payment|null
     */
    private function getAuthorizationPayment($booking)
    {
        $payments = $this->getAssociatedPayments($booking);

        if (!$payments) {
            return null;
        }

        $pendingRazorpay = null;
        $latestPayment = null;

        foreach ($payments as $payment) {
            if (
                $latestPayment === null ||
                (
                    $payment->getCreated() &&
                    $latestPayment->getCreated() &&
                    $payment->getCreated()->getValue() > $latestPayment->getCreated()->getValue()
                )
            ) {
                $latestPayment = $payment;
            }

            $gateway = $payment->getGateway() && $payment->getGateway()->getName()
                ? $payment->getGateway()->getName()->getValue()
                : null;

            if (
                $gateway === PaymentType::RAZORPAY &&
                $payment->getStatus() &&
                $payment->getStatus()->getValue() === PaymentStatus::PENDING
            ) {
                if (
                    $pendingRazorpay === null ||
                    (
                        $payment->getCreated() &&
                        $pendingRazorpay->getCreated() &&
                        $payment->getCreated()->getValue() > $pendingRazorpay->getCreated()->getValue()
                    )
                ) {
                    $pendingRazorpay = $payment;
                }
            }
        }

        return $pendingRazorpay ?: $latestPayment;
    }

    /**
     * @param CustomerBooking|PackageCustomer $booking
     *
     * @return bool
     */
    private function isStillPending($booking)
    {
        if (
            $booking->getStatus() &&
            in_array(
                $booking->getStatus()->getValue(),
                [BookingStatus::CANCELED, BookingStatus::REJECTED, BookingStatus::NO_SHOW],
                true
            )
        ) {
            return false;
        }

        $payments = $this->getAssociatedPayments($booking);

        if (!$payments) {
            return true;
        }

        foreach ($payments as $payment) {
            if (!$payment->getStatus()) {
                continue;
            }

            if ($payment->getStatus()->getValue() !== PaymentStatus::PENDING) {
                return false;
            }
        }

        return true;
    }
}
