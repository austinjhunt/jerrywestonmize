<?php

namespace AmeliaBooking\Application\Commands\Booking\Package;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Infrastructure\Repository\Bookable\Service\PackageCustomerServiceRepository;

/**
 * Class GetPackageBookingServicesCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Booking\Package
 */
class GetPackageBookingServicesCommandHandler extends CommandHandler
{
    /**
     * @param GetPackageBookingServicesCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     */
    public function handle(GetPackageBookingServicesCommand $command)
    {
        /** @var AbstractUser $user */
        $user = $command->authorize();

        if ($user->getType() === Entities::CUSTOMER || $user->getType() === Entities::PROVIDER) {
            throw new AccessDeniedException('You are not allowed to read package booking services');
        }

        $result = new CommandResult();

        $packageCustomerId = $command->getArg('id');

        /** @var PackageCustomerServiceRepository $packageCustomerServiceRepository */
        $packageCustomerServiceRepository = $this->container->get('domain.bookable.packageCustomerService.repository');

        $availableServices = $packageCustomerServiceRepository->getAvailableServiceIds($packageCustomerId);

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setData([
            'services' => $availableServices
        ]);

        return $result;
    }
}
