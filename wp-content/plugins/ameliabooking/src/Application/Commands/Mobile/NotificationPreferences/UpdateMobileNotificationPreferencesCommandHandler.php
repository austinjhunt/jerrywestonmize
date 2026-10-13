<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile\NotificationPreferences;

use AmeliaBooking\Application\Commands\Mobile\AbstractMobileCommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Factory\Mobile\MobileNotificationPrefsFactory;
use AmeliaBooking\Domain\Repository\Mobile\MobileNotificationPrefsRepositoryInterface;
use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class UpdateMobileNotificationPreferencesCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Mobile\NotificationPreferences
 */
class UpdateMobileNotificationPreferencesCommandHandler extends AbstractMobileCommandHandler
{
    /**
     * @param UpdateMobileNotificationPreferencesCommand $command
     *
     * @return CommandResult
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function handle(UpdateMobileNotificationPreferencesCommand $command): CommandResult
    {
        $user = $this->getAuthenticatedProvider($command);

        if ($user === null || $user->getId() === null) {
            return $this->createReauthorizeResult();
        }

        /** @var MobileNotificationPrefsRepositoryInterface $prefsRepository */
        $prefsRepository = $this->container->get('domain.mobile.notificationPrefs.repository');

        $input = $command->getField('preferences');

        if (!is_array($input)) {
            $input = [];

            $flatKeys = array_merge(
                array_keys(MobileNotificationPreferences::getDefaults()),
                [MobileNotificationPreferences::OPTION_REMINDER_TIME_BEFORE]
            );

            foreach ($flatKeys as $key) {
                $value = $command->getField($key);

                if ($value !== null) {
                    $input[$key] = $value;
                }
            }
        }

        $existing = $prefsRepository->getByUserId($user->getId()->getValue());
        $base = $existing ? $existing->getPreferencesArray() : [];
        $preferences = MobileNotificationPreferences::normalize(array_merge($base, $input));

        $entity = MobileNotificationPrefsFactory::create([
            'userId'      => $user->getId()->getValue(),
            'preferences' => json_encode($preferences),
        ]);

        $prefsRepository->upsertByUserId($entity);

        $result = new CommandResult();
        $result->setData(['preferences' => $preferences]);

        return $result;
    }
}
