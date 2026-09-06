<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Reservation\AbstractReservationService;
use AmeliaBooking\Application\Services\User\APIUserApplicationService;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\Payment\Payment;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageCustomerRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;
use AmeliaBooking\Infrastructure\Repository\Payment\PaymentRepository;
use Slim\Exception\ContainerValueNotFoundException;
use Exception;

/**
 * Class SuccessfulBookingCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class SuccessfulBookingCommandHandler extends CommandHandler
{
    /**
     * @var array
     */
    public $mandatoryFields = [
        'appointmentStatusChanged',
    ];

    /**
     * @param SuccessfulBookingCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws ContainerValueNotFoundException
     * @throws AccessDeniedException
     * @throws Exception
     */
    public function handle(SuccessfulBookingCommand $command)
    {
        $this->checkMandatoryFields($command);

        $bookingId = (int)$command->getArg('id');
        $requestType = $command->getField('type') ?: Entities::APPOINTMENT;
        $type = $requestType === Entities::CART ? Entities::APPOINTMENT : $requestType;
        $recurring = !empty($command->getFields()['recurring']) ? $command->getFields()['recurring'] : [];
        $packageCustomerId = $command->getField('packageCustomerId');
        $token = $command->getField('token');

        /** @var AbstractUser|null $user */
        $user = $this->container->get('logged.in.user');

        if (
            !($command->getUserApplicationService() instanceof APIUserApplicationService) &&
            !(
                $user &&
                in_array(
                    $user->getType(),
                    [AbstractUser::USER_ROLE_ADMIN, AbstractUser::USER_ROLE_MANAGER],
                    true
                )
            )
        ) {
            $this->authorizePostBookingRequest($token, $packageCustomerId, $bookingId, $requestType, $recurring);
        }

        /** @var AbstractReservationService $reservationService */
        $reservationService = $this->container->get('application.reservation.service')->get($type);

        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->container->get('domain.payment.repository');

        $paymentId = $command->getField('paymentId');

        if ($paymentId) {
            /** @var Payment $payment */
            $payment = $paymentRepository->getById($paymentId);

            if (
                ($payment && $payment->getActionsCompleted() && $payment->getActionsCompleted()->getValue()) ||
                ($payment && $payment->getTriggeredActions() && $payment->getTriggeredActions()->getValue())
            ) {
                $result = new CommandResult();

                $result->setResult(CommandResult::RESULT_SUCCESS);
                $result->setMessage('Successfully get booking');
                $result->setDataInResponse(false);

                return $result;
            } elseif ($payment && !$payment->getTriggeredActions()) {
                $paymentRepository->updateFieldById($paymentId, 1, 'triggeredActions');
            }
        } else {
            $result = new CommandResult();

            $result->setResult(CommandResult::RESULT_SUCCESS);
            $result->setMessage('Successfully get booking');
            $result->setDataInResponse(false);

            return $result;
        }

        $resultData = [
            'bookingId' => (int)$command->getArg('id'),
            'type' => $command->getField('type') ?: Entities::APPOINTMENT,
            'recurring' => !empty($command->getFields()['recurring']) ? $command->getFields()['recurring'] : [],
            'isCart' => $command->getField('type') === Entities::CART,
            'appointmentStatusChanged' => $command->getFields()['appointmentStatusChanged'],
            'packageId' => $command->getField('packageId'),
            'customer' => $command->getField('customer'),
            'paymentId' => $command->getField('paymentId'),
            'packageCustomerId' => $command->getField('packageCustomerId'),
            'isPackageAppointment' => !empty($command->getFields()['isPackageAppointment']),
            'packageBookingFromBackend' => !empty($command->getFields()['packageBookingFromBackend'])
        ];

        $resultData = apply_filters('amelia_before_post_booking_actions_filter', $resultData);

        do_action('amelia_before_post_booking_actions', $resultData);


        return $reservationService->getSuccessBookingResponse(
            $resultData['bookingId'],
            $resultData['type'],
            $resultData['recurring'],
            $resultData['isCart'],
            $resultData['appointmentStatusChanged'],
            $resultData['packageId'],
            $resultData['customer'],
            $resultData['paymentId'],
            $resultData['packageCustomerId'],
            $resultData['isPackageAppointment'],
            $resultData['packageBookingFromBackend']
        );
    }

    /**
     * @param string            $token
     * @param int               $packageCustomerId
     * @param int               $bookingId
     * @param string            $requestType
     * @param array             $recurring
     *
     * @return void
     * @throws AccessDeniedException
     */
    private function authorizePostBookingRequest(
        $token,
        $packageCustomerId,
        $bookingId,
        $requestType,
        array $recurring
    ) {
        if ($token === null || $token === '') {
            throw new AccessDeniedException('You are not allowed to complete booking actions');
        }

        if ($requestType === Entities::PACKAGE) {
            if (!$packageCustomerId) {
                throw new AccessDeniedException('You are not allowed to complete booking actions');
            }

            /** @var PackageCustomerRepository $packageCustomerRepository */
            $packageCustomerRepository = $this->container->get('domain.bookable.packageCustomer.repository');

            $packageToken = $packageCustomerRepository->getToken((int)$packageCustomerId);

            if (
                empty($packageToken['token']) ||
                !hash_equals((string)$packageToken['token'], (string)$token)
            ) {
                throw new AccessDeniedException('You are not allowed to complete booking actions');
            }
        } else {
            if ($bookingId <= 0) {
                throw new AccessDeniedException('You are not allowed to complete booking actions');
            }

            $this->assertBookingTokenAuthorized($bookingId, $token);

            foreach ($recurring as $recurringData) {
                if (empty($recurringData['id'])) {
                    continue;
                }

                if (empty($recurringData['token'])) {
                    throw new AccessDeniedException('You are not allowed to complete booking actions');
                }

                $this->assertBookingTokenAuthorized(
                    (int)$recurringData['id'],
                    $recurringData['token']
                );
            }
        }
    }

    /**
     * @param int    $bookingId
     * @param string $token
     *
     * @return void
     * @throws AccessDeniedException
     */
    private function assertBookingTokenAuthorized($bookingId, $token)
    {
        /** @var CustomerBookingRepository $bookingRepository */
        $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

        $storedToken = $bookingRepository->getToken($bookingId);

        if (
            empty($storedToken['token']) ||
            !hash_equals((string)$storedToken['token'], (string)$token)
        ) {
            throw new AccessDeniedException('You are not allowed to complete booking actions');
        }
    }
}
