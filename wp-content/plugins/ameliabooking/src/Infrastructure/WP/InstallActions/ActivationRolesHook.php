<?php

/**
 * Role hook for activation
 */

namespace AmeliaBooking\Infrastructure\WP\InstallActions;

use AmeliaBooking\Infrastructure\WP\config\Roles;
use AmeliaBooking\Infrastructure\WP\UserRoles\SuperAdminRoleService;
use AmeliaBooking\Infrastructure\WP\UserRoles\UserRoles;

/**
 * Class ActivationRolesHook
 *
 * @package AmeliaBooking\Infrastructure\WP\InstallActions
 */
class ActivationRolesHook
{
    /**
     * Add new custom roles and add capabilities to administrator role
     */
    public static function init()
    {
        $roles = new Roles();

        UserRoles::init($roles());

        if (!SuperAdminRoleService::isAvailable()) {
            \remove_role(SuperAdminRoleService::ROLE);

            self::syncAdministratorRoleCapabilities();

            return;
        }

        self::syncSuperAdminRoleCapabilities();
        self::syncAdministratorRoleCapabilities();
    }

    private static function syncSuperAdminRoleCapabilities()
    {
        $superAdminRole = get_role(SuperAdminRoleService::ROLE);
        $adminRole = get_role('administrator');

        if ($superAdminRole === null) {
            return;
        }

        if ($adminRole !== null) {
            foreach ($adminRole->capabilities as $capability => $enabled) {
                if ($enabled) {
                    $superAdminRole->add_cap($capability);
                }
            }
        }

        foreach (Roles::$rolesList as $capability) {
            $superAdminRole->add_cap($capability);
        }
    }

    private static function syncAdministratorRoleCapabilities()
    {
        $adminRole = get_role('administrator');

        if ($adminRole === null) {
            return;
        }

        $adminRole->remove_cap(SuperAdminRoleService::CAPABILITY);

        foreach (Roles::$rolesList as $capability) {
            if ($capability === SuperAdminRoleService::CAPABILITY) {
                continue;
            }

            $adminRole->add_cap($capability);
        }
    }
}
