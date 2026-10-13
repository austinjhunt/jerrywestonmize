<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile\Devices;

use AmeliaBooking\Application\Commands\Command;
use AmeliaBooking\Application\Commands\Mobile\AbstractMobileCommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Factory\Mobile\MobileDeviceFactory;
use AmeliaBooking\Domain\Repository\Mobile\MobileDeviceRepositoryInterface;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class RegisterMobileDeviceCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Mobile\Devices
 */
class RegisterMobileDeviceCommandHandler extends AbstractMobileCommandHandler
{
    public $mandatoryFields = [
        'token',
        'platform',
    ];

    /**
     * @param RegisterMobileDeviceCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(RegisterMobileDeviceCommand $command)
    {
        $user = $this->getAuthenticatedProvider($command);

        if ($user === null || $user->getId() === null) {
            return $this->createReauthorizeResult();
        }

        $this->checkMandatoryFields($command);

        /** @var MobileDeviceRepositoryInterface $deviceRepository */
        $deviceRepository = $this->container->get('domain.mobile.device.repository');

        $now = DateTimeService::getNowDateTimeObject();

        $deviceData = [
            'userId'        => $user->getId()->getValue(),
            'expoPushToken' => $command->getField('token'),
            'platform'      => $command->getField('platform'),
        ];

        $appVersion = $command->getField('appVersion');

        if (!empty($appVersion)) {
            $deviceData['appVersion'] = $appVersion;
        }

        $device = MobileDeviceFactory::create($deviceData);

        $device->setLastSeen(new DateTimeValue($now));
        $device->setCreatedAt(new DateTimeValue($now));

        $deviceId = $deviceRepository->upsertByToken($device);

        $result = new CommandResult();
        $result->setData(['deviceId' => (int) $deviceId]);

        return $result;
    }
}
