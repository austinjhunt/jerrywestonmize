<?php

namespace AmeliaBooking\Application\Commands\User\Provider;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Application\Services\Entity\EntityApplicationService;
use AmeliaBooking\Application\Services\User\ProviderApplicationService;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Collection\Collection;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Entity\User\Provider;
use AmeliaBooking\Domain\Factory\User\UserFactory;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\Password;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Repository\User\ProviderRepository;
use AmeliaBooking\Infrastructure\Services\Apple\AbstractAppleCalendarService;
use AmeliaBooking\Domain\Entity\Google\GoogleCalendar;
use AmeliaBooking\Infrastructure\Repository\Google\GoogleCalendarRepository;
use AmeliaBooking\Infrastructure\Services\Google\AbstractGoogleCalendarService;
use AmeliaBooking\Infrastructure\Services\Outlook\AbstractOutlookCalendarService;
use AmeliaBooking\Infrastructure\WP\UserRoles\SuperAdminRoleService;
use Exception;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Domain\ValueObjects\String\Phone;

/**
 * Class UpdateProviderCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\User\Provider
 */
class UpdateProviderCommandHandler extends CommandHandler
{
    /**
     * @param UpdateProviderCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     * @throws Exception
     */
    public function handle(UpdateProviderCommand $command): CommandResult
    {
        /** @var AbstractUser $currentUser */
        $currentUser = $command->authorizeProviderWritePermission((int)$command->getArg('id'));

        $result = new CommandResult();

        $this->checkMandatoryFields($command);

        /** @var ProviderRepository $providerRepository */
        $providerRepository = $this->container->get('domain.users.providers.repository');

        /** @var ProviderApplicationService $providerAS */
        $providerAS = $this->container->get('application.user.provider.service');

        /** @var SettingsService $settingsDS */
        $settingsDS = $this->container->get('domain.settings.service');

        $userId = (int)$command->getArg('id');

        /** @var UserApplicationService $userAS */
        $userAS = $this->getContainer()->get('application.user.service');

        /** @var Provider $oldUser */
        $oldUser = $providerAS->getProviderWithServicesAndSchedule($userId);

        // If the current user is a provider and does not have write others permission, and the externalId is changed, throw an access denied exception
        if (
            $userAS->isProvider($currentUser) &&
            !$command->getPermissionService()->currentUserCanWriteOthers(Entities::EMPLOYEES) &&
            (int)$command->getField('externalId') &&
            (!$oldUser->getExternalId() || $oldUser->getExternalId()->getValue() !== $command->getField('externalId'))
        ) {
            throw new AccessDeniedException('You are not allowed');
        }

        $command->setField('id', $userId);

        $providerData = $command->getFields();

        $providerData['type'] = Entities::PROVIDER;

        if (!isset($providerData['stripeConnect'])) {
            $providerData['stripeConnect'] = null;
        }

        if (!isset($providerData['zoomUserId'])) {
            $providerData['zoomUserId'] = null;
        }

        if (!isset($providerData['appleCalendarId'])) {
            $providerData['appleCalendarId'] = null;
        }

        if (!isset($providerData['employeeAppleCalendar'])) {
            $providerData['employeeAppleCalendar'] = null;
        } else {
            /** @var AbstractAppleCalendarService $appleCalendarService */
            $appleCalendarService = $this->container->get('infrastructure.apple.calendar.service');

            $appleId       = $providerData['employeeAppleCalendar']['iCloudId'];
            $applePassword = $providerData['employeeAppleCalendar']['appSpecificPassword'];

            $credentials = $appleCalendarService->handleAppleCredentials($appleId, $applePassword);

            if (!$credentials) {
                $providerData['employeeAppleCalendar'] = null;
            }
        }

        /** @var EntityApplicationService $entityService */
        $entityService = $this->container->get('application.entity.service');

        $entityService->removeMissingEntitiesForProvider($providerData);

        if (!!$oldUser->getBadgeId() && !isset($providerData['badgeId'])) {
            $providerData['badgeId'] = null;
        }

        if ($oldUser->getTimeZone() && $settingsDS->isFeatureEnabled('timezones') === false) {
            $providerData['timeZone'] = $oldUser->getTimeZone()->getValue();
        }

        $newUserData = array_merge($oldUser->toArray(), $providerData);

        $newUserData = apply_filters('amelia_before_provider_updated_filter', $newUserData, $oldUser->toArray());

        /** @var Provider $newUser */
        $newUser = UserFactory::create($newUserData);

        $oldExternalId = $oldUser->getExternalId() ? $oldUser->getExternalId()->getValue() : null;
        $newExternalId = $newUser->getExternalId() ? $newUser->getExternalId()->getValue() : null;

        $isAdminOrManager = $currentUser && (
            $currentUser->getType() === AbstractUser::USER_ROLE_ADMIN ||
            $currentUser->getType() === AbstractUser::USER_ROLE_MANAGER
        );

        // externalId === 0 is the front-end asking for a new WP user to be created for this employee.
        // UserFactory drops empty values, so it never reaches $newExternalId and the comparison below
        // cannot see it - it has to be detected on the raw field.
        $createsWpUser = $command->getField('externalId') === 0;

        if (($oldExternalId !== $newExternalId || $createsWpUser) && !$isAdminOrManager) {
            // Non-admin/manager cannot change externalId at all, nor have a WP user created

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Could not update user.');

            return $result;
        }

        if (
            $newExternalId &&
            !$userAS->isRoleForExternalIdAllowed($newExternalId, Entities::PROVIDER)
        ) {
            // Linking to existing WP user must pass role check

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('Could not update user.');

            return $result;
        }

        if ($newExternalId && SuperAdminRoleService::userHasRole((int)$newExternalId)) {
            $result->setResult(CommandResult::RESULT_CONFLICT);
            $result->setMessage('Superadmin users cannot be assigned Amelia roles.');
            $result->setData([]);

            return $result;
        }

        // If the phone is not set and the old phone is set, set the phone and country phone iso to null
        if (empty($providerData['phone']) && $oldUser->getPhone() && $oldUser->getPhone()->getValue()) {
            $newUser->setPhone(new Phone(null));
            $newUser->setCountryPhoneIso(new Name(null));
        }

        $newUser->setDayOffList(
            $providerAS->getModifiedDayList(
                $newUser->getDayOffList(),
                $oldUser->getDayOffList(),
                !empty($newUserData['removedDayOffList'])
                    ? UserFactory::createDayOffList($newUserData['removedDayOffList'])
                    : new Collection()
            )
        );

        $newUser->setSpecialDayList(
            $providerAS->getModifiedDayList(
                $newUser->getSpecialDayList(),
                $oldUser->getSpecialDayList(),
                !empty($newUserData['removedSpecialDayList'])
                    ? UserFactory::createSpecialDayList($newUserData['removedSpecialDayList'])
                    : new Collection()
            )
        );

        if ($command->getUserApplicationService()->checkProviderPermissions($currentUser, $command->getToken())) {
            $rolesSettings = $settingsDS->getCategorySettings('roles');

            if (!$rolesSettings['allowConfigureServices']) {
                $newUser->setServiceList($oldUser->getServiceList());
            }

            if (!$rolesSettings['allowConfigureSchedule']) {
                $newUser->setWeekDayList($oldUser->getWeekDayList());
            }

            if (!$rolesSettings['allowConfigureDaysOff']) {
                $newUser->setDayOffList($oldUser->getDayOffList());
            }

            if (!$rolesSettings['allowConfigureSpecialDays']) {
                $newUser->setSpecialDayList($oldUser->getSpecialDayList());
            }
        }

        $providerRepository->beginTransaction();

        if (
            $providerRepository->getByEmail($newUser->getEmail()->getValue()) &&
            $oldUser->getEmail()->getValue() !== $newUser->getEmail()->getValue()
        ) {
            $providerRepository->rollback();

            $result->setResult(CommandResult::RESULT_CONFLICT);
            $result->setMessage('Email already exist.');
            $result->setData('This email is already in use.');

            return $result;
        }

        $canWriteWpCredentials = $userAS->canWriteWpCredentials($currentUser, $oldUser);
        $emailChanged          = $oldUser->getEmail()->getValue() !== $newUser->getEmail()->getValue();
        $linkedWpId            = $newUser->getExternalId() ? $newUser->getExternalId()->getValue() : null;

        if ($linkedWpId && $emailChanged && !$canWriteWpCredentials) {
            $providerRepository->rollback();

            $result->setResult(CommandResult::RESULT_ERROR);
            $result->setMessage('You are not allowed to change the linked WordPress account email.');

            return $result;
        }

        if ($command->getField('password')) {
            $newPassword = new Password($command->getField('password'));

            $providerRepository->updateFieldById($command->getArg('id'), $newPassword->getValue(), 'password');

            // Propagate to the linked WP user only when the caller is an admin or the provider
            // updating their own profile. Blocks a Manager from resetting another provider's WP password.
            if ($linkedWpId && $canWriteWpCredentials) {
                add_filter('amelia_user_profile_updated', '__return_true');
                wp_set_password($command->getField('password'), $linkedWpId);
                remove_filter('amelia_user_profile_updated', '__return_true');
            }
        }

        do_action('amelia_before_provider_updated', $newUser ? $newUser->toArray() : null, $oldUser ? $oldUser->toArray() : null);

        try {
            if (!$providerAS->update($oldUser, $newUser, $providerData)) {
                $providerRepository->rollback();
                return $result;
            }

            if (isset($providerData['googleCalendar']['blockedCalendars'])) {
                $providerAS->updateProviderGoogleCalendarBlockedCalendars(
                    $userId,
                    $providerData['googleCalendar']['blockedCalendars']
                );
            }

            $providerData = $this->getGoogleCalendarProviderData($providerData, $providerAS, $userId);

            if (isset($providerData['outlookCalendar']['blockedCalendars'])) {
                $providerAS->updateProviderOutlookCalendarBlockedCalendars(
                    $userId,
                    $providerData['outlookCalendar']['blockedCalendars']
                );
            }

            $providerData = $this->getOutlookCalendarProviderData($providerData, $providerAS, $userId);

            if ($createsWpUser && $isAdminOrManager) {
                /** @var UserApplicationService $userAS */
                $userAS = $this->getContainer()->get('application.user.service');

                $userAS->setWpUserIdForNewUser($userId, $newUser, Entities::PROVIDER, $command->getField('password'));
            } elseif ($linkedWpId) {
                $wpUserData = [
                    'ID'         => $linkedWpId,
                    'first_name' => $newUser->getFirstName() ? $newUser->getFirstName()->getValue() : '',
                    'last_name'  => $newUser->getLastName() ? $newUser->getLastName()->getValue() : '',
                ];

                if ($emailChanged && $canWriteWpCredentials) {
                    $wpUserData['user_email'] = $newUser->getEmail() ? $newUser->getEmail()->getValue() : '';
                }

                add_filter('amelia_user_profile_updated', '__return_true');
                wp_update_user($wpUserData);

                if ($uid = get_current_user_id()) {
                    clean_user_cache($uid);
                }

                remove_filter('amelia_user_profile_updated', '__return_true');
            }
        } catch (QueryExecutionException $e) {
            $providerRepository->rollback();
            throw $e;
        }

        $result = $userAS->getAuthenticatedUserResponse(
            $newUser,
            $oldUser->getEmail()->getValue() !== $newUser->getEmail()->getValue(),
            true,
            $oldUser->getLoginType(),
            'provider'
        );

        $result->setData(
            array_merge(
                $result->getData(),
                [
                    'sendEmployeePanelAccessEmail' =>
                    $command->getField('password') && $command->getField('sendEmployeePanelAccessEmail'),
                    'password'                     => $command->getField('password')
                ]
            )
        );

        $providerRepository->commit();

        // Deliberately after the commit: reaching Google takes a round trip, and holding row locks across
        // one is what let concurrent employee writes deadlock. Running it here also means a rolled back
        // save leaves no watch behind, and lets the connections be read back in their persisted state.
        //
        // They are read back rather than taken from the request precisely because syncId is watch state:
        // it lives on the calendar_sync row, the payload never carries it, and the shallow merge that
        // builds $newUser drops it whenever the request sends a nested googleCalendar object.
        if ($newUser->getGoogleCalendar() && $newUser->getGoogleCalendar()->getId()) {
            /** @var AbstractGoogleCalendarService $googleCalendarService */
            $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');

            /** @var GoogleCalendarRepository $googleCalendarRepository */
            $googleCalendarRepository = $this->container->get('domain.google.calendar.repository');

            try {
                /** @var Collection $googleConnections */
                $googleConnections = $googleCalendarRepository->getByEntityId($userId, 'userId');

                /** @var GoogleCalendar $googleConnection */
                foreach ($googleConnections->getItems() as $googleConnection) {
                    $connectionCalendarId = $googleConnection->getCalendarId()
                        ? $googleConnection->getCalendarId()->getValue()
                        : null;

                    if ($connectionCalendarId) {
                        // manageChannel() decides for itself whether a channel is needed: it consults the
                        // watch registry, so it does not need the caller to compare old and new state.
                        $googleCalendarService->manageChannel($googleConnection);

                        continue;
                    }

                    // The selection has been cleared, on this connection or on one the save switched away
                    // from. Nothing else releases the watch: manageChannel() returns at its first guard on
                    // an empty calendar id, ensureTwoWaySyncForGlobalProvider() below leaves an employee
                    // who owns a connection alone, and the renewal cron still sees this row pointing at the
                    // registry entry, so it keeps renewing. Left attached, isProviderAttachedToSync()
                    // matches on syncId alone and changes on the old calendar would go on moving this
                    // employee's appointments.
                    $googleCalendarService->disconnect($googleConnection);
                }
            } catch (\Throwable $e) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                    'GoogleCalendar: managing watches after provider update failed',
                    [
                        'exception'  => $e,
                        'providerId' => $userId,
                    ]
                );
            }
        }

        // Both shapes matter: the integrations page sends a flat googleCalendarId (an empty string when
        // the selection is cleared), the Employees page a nested googleCalendar object. Keys are tested
        // for presence rather than for a value, so neither an empty nor a null one slips past and leaves
        // a watch running. For an employee with a connection of their own this is a no-op: the global
        // path bows out and manageChannel() handles them.
        if (
            array_key_exists('googleCalendar', $providerData) ||
            array_key_exists('googleCalendarId', $providerData)
        ) {
            /** @var AbstractGoogleCalendarService $googleCalendarService */
            $googleCalendarService = $this->container->get('infrastructure.google.calendar.service');

            try {
                $googleCalendarService->ensureTwoWaySyncForGlobalProvider($userId);
            } catch (\Throwable $e) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                    'GoogleCalendar: ensureTwoWaySync after provider update failed',
                    [
                        'exception'  => $e,
                        'providerId' => $userId,
                    ]
                );
            }
        }

        // The Employees page sends a nested outlookCalendar object; the integrations page sends a flat
        // outlookCalendarId for employees on the global connection, including an empty one to clear it.
        // Presence rather than value, for the same reason as Google above.
        if (
            array_key_exists('outlookCalendar', $providerData) ||
            array_key_exists('outlookCalendarId', $providerData)
        ) {
            /** @var AbstractOutlookCalendarService $outlookCalendarService */
            $outlookCalendarService = $this->container->get('infrastructure.outlook.calendar.service');

            try {
                $outlookCalendarService->ensureTwoWaySyncForProvider($userId);
            } catch (\Throwable $e) {
                $this->container->getLoggerService()->channel(LoggerInterface::CHANNEL_SYNC)->error(
                    'OutlookCalendar: ensureTwoWaySync after provider update failed',
                    [
                        'exception'  => $e,
                        'providerId' => $userId,
                    ]
                );
            }
        }

        do_action('amelia_after_provider_updated', $newUser ? $newUser->toArray() : null, $oldUser ? $oldUser->toArray() : null);

        return $result;
    }

    /**
     * @param array $providerData
     * @param ProviderApplicationService $providerAS
     * @param $userId
     * @return array
     * @throws QueryExecutionException
     */
    public function getGoogleCalendarProviderData(array $providerData, ProviderApplicationService $providerAS, $userId): array
    {
        if (isset($providerData['googleCalendar'])) {
            $googleCalendarSettings = [];

            if (isset($providerData['googleCalendar']['insertPendingAppointments'])) {
                $googleCalendarSettings['insertPendingAppointments'] = $providerData['googleCalendar']['insertPendingAppointments'];
            }

            if (isset($providerData['googleCalendar']['includeBufferTime'])) {
                $googleCalendarSettings['includeBufferTime'] = $providerData['googleCalendar']['includeBufferTime'];
            }

            if (isset($providerData['googleCalendar']['title'])) {
                $googleCalendarSettings['title'] = $providerData['googleCalendar']['title'];
            }

            if (isset($providerData['googleCalendar']['description'])) {
                $googleCalendarSettings['description'] = $providerData['googleCalendar']['description'];
            }

            if (!empty($googleCalendarSettings)) {
                $providerAS->updateProviderGoogleCalendarAccountSettings($userId, $googleCalendarSettings);
            }
        }
        return $providerData;
    }

    /**
     * @param array $providerData
     * @param ProviderApplicationService $providerAS
     * @param $userId
     * @return array
     * @throws QueryExecutionException
     */
    public function getOutlookCalendarProviderData(array $providerData, ProviderApplicationService $providerAS, $userId): array
    {
        if (isset($providerData['outlookCalendar'])) {
            $outlookCalendarSettings = [];

            if (isset($providerData['outlookCalendar']['insertPendingAppointments'])) {
                $outlookCalendarSettings['insertPendingAppointments'] = $providerData['outlookCalendar']['insertPendingAppointments'];
            }

            if (isset($providerData['outlookCalendar']['includeBufferTime'])) {
                $outlookCalendarSettings['includeBufferTime'] = $providerData['outlookCalendar']['includeBufferTime'];
            }

            if (isset($providerData['outlookCalendar']['title'])) {
                $outlookCalendarSettings['title'] = $providerData['outlookCalendar']['title'];
            }

            if (isset($providerData['outlookCalendar']['description'])) {
                $outlookCalendarSettings['description'] = $providerData['outlookCalendar']['description'];
            }

            if (!empty($outlookCalendarSettings)) {
                $providerAS->updateProviderOutlookCalendarAccountSettings($userId, $outlookCalendarSettings);
            }
        }
        return $providerData;
    }
}
