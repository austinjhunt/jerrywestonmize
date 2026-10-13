<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile\Devices;

use AmeliaBooking\Application\Commands\Mobile\AbstractMobileCommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Repository\Mobile\MobileDeviceRepositoryInterface;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class UnregisterMobileDeviceCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Mobile\Devices
 */
class UnregisterMobileDeviceCommandHandler extends AbstractMobileCommandHandler
{
    public $mandatoryFields = [
        'token',
    ];

    /**
     * @param UnregisterMobileDeviceCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(UnregisterMobileDeviceCommand $command)
    {
        $user = $this->getAuthenticatedProvider($command);

        if ($user === null || $user->getId() === null) {
            return $this->createReauthorizeResult();
        }

        $this->checkMandatoryFields($command);

        /** @var MobileDeviceRepositoryInterface $deviceRepository */
        $deviceRepository = $this->container->get('domain.mobile.device.repository');

        $token  = $command->getField('token');
        $device = $deviceRepository->getByExpoPushToken($token);

        if (
            $device !== null
            && $device->getUserId()->getValue() === $user->getId()->getValue()
        ) {
            $deviceRepository->deleteByExpoPushToken($token);
        }

        return new CommandResult();
    }
}
