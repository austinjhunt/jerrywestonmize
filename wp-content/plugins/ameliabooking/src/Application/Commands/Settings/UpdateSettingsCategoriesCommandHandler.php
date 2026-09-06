<?php

namespace AmeliaBooking\Application\Commands\Settings;

use AmeliaBooking\Application\Commands\CommandHandler;
use AmeliaBooking\Application\Commands\CommandResult;
use AmeliaBooking\Application\Common\Exceptions\AccessDeniedException;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\UserRoles\SuperAdminRoleService;
use AmeliaBooking\Infrastructure\WP\UserRoles\UserRoles;
use Exception;
use Interop\Container\Exception\ContainerException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class UpdateSettingsCategoriesCommandHandler
 *
 * @package AmeliaBooking\Application\Commands\Settings
 */
class UpdateSettingsCategoriesCommandHandler extends CommandHandler
{
    /**
     * @param UpdateSettingsCategoriesCommand $command
     *
     * @return CommandResult
     * @throws AccessDeniedException
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     * @throws Exception
     */
    public function handle(UpdateSettingsCategoriesCommand $command)
    {
        $result = new CommandResult();

        if (!$this->getContainer()->getPermissionsService()->currentUserCanWrite(Entities::SETTINGS)) {
            /** @var AbstractUser $loggedInUser */
            $loggedInUser = $this->container->get('logged.in.user');

            if (
                !$loggedInUser || !(
                    $loggedInUser->getType() === AbstractUser::USER_ROLE_ADMIN ||
                    $loggedInUser->getType() === AbstractUser::USER_ROLE_MANAGER
                )
            ) {
                throw new AccessDeniedException('You are not allowed to write settings.');
            }
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get('domain.settings.service');

        $superAdminService = new SuperAdminRoleService();
        $pendingSuperAdminGrantUserId = null;
        $savedActivationSettings = null;

        foreach ($command->getField('categories') as $category => $data) {
            if ($category === 'activation' && !$superAdminService->canAccessActivationSettings()) {
                continue;
            }

            $categorySettings = $settingsService->getCategorySettings($category);

            if ($categorySettings !== null) {
                if (
                    $category === 'activation' &&
                    !empty($data['active']) &&
                    empty($categorySettings['licenseActivatorUserId']) &&
                    get_current_user_id() &&
                    (current_user_can('manage_options') || is_super_admin())
                ) {
                    $savedActivationSettings = $categorySettings;
                    $pendingSuperAdminGrantUserId = get_current_user_id();
                }

                if ($category === 'general' && array_key_exists('usedLanguages', $data)) {
                    $categorySettings['usedLanguages'] = $data['usedLanguages'];
                    unset($data['usedLanguages']);
                }

                if ($category === 'ivy') {
                    $categorySettings = !empty($data['ivy']) ? $data['ivy'] : [];
                }

                $categorySettings = array_replace_recursive($categorySettings, $data);

                $settingsService->setCategorySettings($category, $categorySettings);
            }
        }

        $updatedCategories = array_keys($command->getField('categories') ?: []);

        if (
            in_array('whiteLabel', $updatedCategories, true) ||
            in_array('featuresIntegrations', $updatedCategories, true)
        ) {
            UserRoles::syncRoleLabels();
        }

        if (in_array('activation', $updatedCategories, true)) {
            UserRoles::syncSuperAdminRoleAvailability();
        }

        if ($pendingSuperAdminGrantUserId) {
            if (!$superAdminService->grant($pendingSuperAdminGrantUserId)) {
                if (is_array($savedActivationSettings)) {
                    $settingsService->setCategorySettings('activation', $savedActivationSettings);
                    UserRoles::syncSuperAdminRoleAvailability();
                }

                $result->setResult(CommandResult::RESULT_ERROR);
                $result->setMessage('Failed to assign Superadmin to the activating user.');
                $result->setData([]);

                return $result;
            }

            $activationSettings = $settingsService->getCategorySettings('activation');
            $activationSettings['licenseActivatorUserId'] = $pendingSuperAdminGrantUserId;
            $settingsService->setCategorySettings('activation', $activationSettings);
        }

        $result->setResult(CommandResult::RESULT_SUCCESS);
        $result->setMessage('Successfully updated settings.');
        $result->setData(
            [
                'isSuperAdmin'              => $superAdminService->isCurrentUserSuperAdmin(),
                'isSuperAdminRoleAvailable' => SuperAdminRoleService::isAvailable(),
                'superAdminCount'           => $superAdminService->countSuperAdmins(),
            ]
        );

        return $result;
    }
}
