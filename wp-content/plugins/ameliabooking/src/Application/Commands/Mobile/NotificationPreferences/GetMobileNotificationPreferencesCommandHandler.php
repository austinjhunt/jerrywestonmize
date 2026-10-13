<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Commands\Mobile\NotificationPreferences;

use AmeliaBooking\Application\Commands\Mobile\AbstractMobileCommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Domain\Repository\Mobile\MobileNotificationPrefsRepositoryInterface;
use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;

/**
 * Class GetMobileNotificationPreferencesCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Mobile\NotificationPreferences
 */
class GetMobileNotificationPreferencesCommandHandler extends AbstractMobileCommandHandler
{
    /**
     * @param GetMobileNotificationPreferencesCommand $command
     *
     * @return CommandResult
     * @throws QueryExecutionException
     */
    public function handle(GetMobileNotificationPreferencesCommand $command): CommandResult
    {
        $user = $this->getAuthenticatedProvider($command);

        if ($user === null || $user->getId() === null) {
            return $this->createReauthorizeResult();
        }

        /** @var MobileNotificationPrefsRepositoryInterface $prefsRepository */
        $prefsRepository = $this->container->get('domain.mobile.notificationPrefs.repository');

        $prefs = $prefsRepository->getByUserId($user->getId()->getValue());

        $preferences = $prefs
            ? $prefs->getPreferencesArray()
            : MobileNotificationPreferences::normalize([]);

        $result = new CommandResult();
        $result->setData(['preferences' => $preferences]);

        return $result;
    }
}
