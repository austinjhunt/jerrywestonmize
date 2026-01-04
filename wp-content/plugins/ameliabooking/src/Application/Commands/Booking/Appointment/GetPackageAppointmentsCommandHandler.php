<?php

namespace AmeliaBooking\Application\Commands\Booking\Appointment;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Bookable\AbstractPackageApplicationService;
use AmeliaBooking\Application\Services\Booking\AppointmentApplicationService;
use AmeliaBooking\Application\Services\Booking\BookingApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\AuthorizationException;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Bookable\Service\Service;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Booking\Appointment\CustomerBooking;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageCustomerRepository;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\ServiceRepository;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\CustomerBookingRepository;

/**
 * Class GetPackageAppointmentsCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Appointment
 */
class GetPackageAppointmentsCommandHandler extends CommandHandler
{
    /**
     * @param GetPackageAppointmentsCommand $command
     *
     * @return CommandResult
     *
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws AccessDeniedException
     */
    public function handle(GetPackageAppointmentsCommand $command)
    {
        $result = new CommandResult();

        /** @var ServiceRepository $serviceRepository */
        $serviceRepository = $this->container->get('domain.bookable.service.repository');

        /** @var PackageCustomerRepository $packageCustomerRepository */
        $packageCustomerRepository = $this->container->get('domain.bookable.packageCustomer.repository');

        /** @var AbstractPackageApplicationService $packageAS */
        $packageAS = $this->container->get('application.bookable.package');

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');

        /** @var BookingApplicationService $bookingAS */
        $bookingAS = $this->container->get('application.booking.booking.service');

        /** @var AppointmentApplicationService $appointmentAS */
        $appointmentAS = $this->container->get('application.booking.appointment.service');

        try {
            /** @var AbstractUser $user */
            $user = $command->getUserApplicationService()->authorization(null, $command->getCabinetType());
        } catch (AuthorizationException $e) {
            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setData(
                [
                    'reauthorize' => true
                ]
            );

            return $result;
        }

        $params = $command->getField('params');

        if ($user && $user->getType() === Entities::PROVIDER) {
            $params['providers'] = [$user->getId()->getValue()];
        }

        if ($user && $user->getType() === Entities::CUSTOMER) {
            $params['customers'] = [$user->getId()->getValue()];
        }

        if (!empty($params['dates'])) {
            !empty($params['dates'][0]) ? $params['dates'][0] .= ' 00:00:00' : null;
            !empty($params['dates'][1]) ? $params['dates'][1] .= ' 23:59:59' : null;
        }

        $availablePackageBookings = [];

        $packageCustomerIds = [];

        $noResultsManagePackagesFilters = false;

        $totalPackagePurchases = 0;

        $customers = isset($params['customerId']) ? [$params['customerId']] : [];

        if (!empty($params['packageStatus']) || !empty($params['page']) || !empty($params['bookingsCount'])) {
            $packageCustomerIds = $packageCustomerRepository->getFilteredIds(
                [
                    'dates'     => !empty($params['dates']) ? $params['dates'] : [],
                    'packages'  => !empty($params['packageId']) ? [$params['packageId']] : [],
                    'page'      => !empty($params['page']) ? $params['page'] : null,
                    'status'    => !empty($params['packageStatus']) ? $params['packageStatus'] : null,
                    'customers' => $customers,
                ],
                $settingsDS->getSetting('general', 'itemsPerPage')
            );

            $noResultsManagePackagesFilters = !$packageCustomerIds;

            $totalPackagePurchases = sizeof($packageCustomerRepository->getFilteredIds(
                [
                    'packageCustomerIds' => $packageCustomerIds ?: [],
                    'purchased'          => !empty($params['dates']) ? $params['dates'] : [],
                    'packages'           => !empty($params['packageId']) ? [$params['packageId']] : [],
                    'packageStatus'      => !empty($params['packageStatus']) ? $params['packageStatus'] : null,
                    'customers'          => $customers
                ]
            ));
        }

        /**
         * @var Collection $appointments
         */
        $appointments = new Collection();

        if (isset($params['customerId'])) {
            unset($params['customerId']);
        }

        $customersNoShowCountIds = [];

        $noShowTagEnabled = $settingsDS->isFeatureEnabled('noShowTag');

        if (!$noResultsManagePackagesFilters) {
            $availablePackageBookings = $packageAS->getPackageAvailability(
                $appointments,
                [
                    'packageCustomerIds' => !empty($packageCustomerIds) ? $packageCustomerIds : [],
                    'purchased'          => !empty($params['dates']) ? $params['dates'] : [],
                    'customers'          => $customers,
                    'packageId'          => !empty($params['packageId']) ? (int)$params['packageId'] : null,
                    'managePackagePage'  => true
                ]
            );

            if ($noShowTagEnabled && !!$availablePackageBookings) {
                $customersNoShowCountIds[] = $availablePackageBookings[0]['customerId'];
            }
        }

        /** @var Collection $services */
        $services = $serviceRepository->getAllArrayIndexedById();

        $packageAS->setPackageBookingsForAppointments($appointments);

        $occupiedTimes = [];

        $currentDateTime = DateTimeService::getNowDateTimeObject();

        $groupedAppointments = [];

        /** @var Appointment $appointment */
        foreach ($appointments->getItems() as $appointment) {
            /** @var Service $service */
            $service = $services->getItem($appointment->getServiceId()->getValue());

            $bookingsCount = 0;

            /** @var CustomerBooking $booking */
            foreach ($appointment->getBookings()->getItems() as $booking) {
                // fix for wrongly saved JSON
                if (
                    $booking->getCustomFields() &&
                    json_decode($booking->getCustomFields()->getValue(), true) === null
                ) {
                    $booking->setCustomFields(null);
                }

                if ($bookingAS->isBookingApprovedOrPending($booking->getStatus()->getValue())) {
                    $bookingsCount++;
                }

                if ($noShowTagEnabled && !in_array($booking->getCustomerId()->getValue(), $customersNoShowCountIds)) {
                    $customersNoShowCountIds[] = $booking->getCustomerId()->getValue();
                }
            }

            $appointmentAS->calculateAndSetAppointmentEnd($appointment, $service);

            $minimumCancelTimeInSeconds = $settingsDS
                ->getEntitySettings($service->getSettings())
                ->getGeneralSettings()
                ->getMinimumTimeRequirementPriorToCanceling();

            $minimumCancelTime = DateTimeService::getCustomDateTimeObject(
                $appointment->getBookingStart()->getValue()->format('Y-m-d H:i:s')
            )->modify("-{$minimumCancelTimeInSeconds} seconds");

            $date = $appointment->getBookingStart()->getValue()->format('Y-m-d');

            $cancelable = $currentDateTime <= $minimumCancelTime;

            $minimumRescheduleTimeInSeconds = $settingsDS
                ->getEntitySettings($service->getSettings())
                ->getGeneralSettings()
                ->getMinimumTimeRequirementPriorToRescheduling();

            $minimumRescheduleTime = DateTimeService::getCustomDateTimeObject(
                $appointment->getBookingStart()->getValue()->format('Y-m-d H:i:s')
            )->modify("-{$minimumRescheduleTimeInSeconds} seconds");

            $reschedulable = $currentDateTime <= $minimumRescheduleTime;

            $groupedAppointments[$date]['date'] = $date;

            $groupedAppointments[$date]['appointments'][] = array_merge(
                $appointment->toArray(),
                [
                    'cancelable'    => $cancelable,
                    'reschedulable' => $reschedulable,
                    'past'          => $currentDateTime >= $appointment->getBookingStart()->getValue()
                ]
            );
        }

        $emptyBookedPackages = null;

        if (
            !empty($params['packageId']) &&
            empty($params['services']) &&
            empty($params['providers']) &&
            empty($params['locations']) &&
            !$noResultsManagePackagesFilters
        ) {
            /** @var AbstractPackageApplicationService $packageApplicationService */
            $packageApplicationService = $this->container->get('application.bookable.package');

            /** @var Collection $emptyBookedPackages */
            $emptyBookedPackages = $packageApplicationService->getEmptyPackages(
                [
                    'packageCustomerIds' => !empty($packageCustomerIds) ? $packageCustomerIds : [],
                    'packages'           => [$params['packageId']],
                    'purchased'          => !empty($params['dates']) ? $params['dates'] : [],
                    'customers'          => $customers
                ]
            );
        }

        $customersNoShowCount = [];

        if ($noShowTagEnabled && $customersNoShowCountIds) {
            /** @var CustomerBookingRepository $bookingRepository */
            $bookingRepository = $this->container->get('domain.booking.customerBooking.repository');

            $customersNoShowCount = $bookingRepository->countByNoShowStatus($customersNoShowCountIds);
        }


        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully retrieved appointments');
        $result->setData(
            [
                Entities::APPOINTMENTS     =>
                !empty($params['asArray']) && filter_var($params['asArray'], FILTER_VALIDATE_BOOLEAN) ? $appointments->toArray() : $groupedAppointments,
                'availablePackageBookings' => $availablePackageBookings,
                'emptyPackageBookings'     => !empty($emptyBookedPackages) ? $emptyBookedPackages->toArray() : [],
                'occupied'                 => $occupiedTimes,
                'totalPackagePurchases'    => $totalPackagePurchases,
                'customersNoShowCount'     => $customersNoShowCount
            ]
        );

        return $result;
    }
}
