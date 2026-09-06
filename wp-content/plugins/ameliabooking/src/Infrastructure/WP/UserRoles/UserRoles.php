<?php

namespace AmeliaBooking\Infrastructure\WP\UserRoles;

use AmeliaBooking\Infrastructure\WP\config\Roles;
use AmeliaBooking\Infrastructure\WP\InstallActions\ActivationRolesHook;

/**
 * Class UserRoles
 *
 * @package AmeliaBooking\Infrastructure\WP
 */
class UserRoles
{
    /**
     * @param $roles
     */
    public static function init($roles)
    {
        /** @var array $roles */
        foreach ($roles as $role) {
            if (!\wp_roles()->is_role($role['name'])) {
                \add_role($role['name'], $role['label'], $role['capabilities']);
            } else {
                self::updateRoleLabel($role['name'], $role['label']);
            }
        }
    }

    /**
     * Re-apply Amelia role display names (white-label plugin name when enabled).
     */
    public static function syncRoleLabels()
    {
        self::syncSuperAdminRoleAvailability();

        $roles = new Roles();

        self::init($roles());
    }

    /**
     * Register or remove SuperAdmin based on Elite (Developer) licence.
     */
    public static function syncSuperAdminRoleAvailability()
    {
        if (SuperAdminRoleService::isAvailable()) {
            if (!\wp_roles()->is_role(SuperAdminRoleService::ROLE)) {
                ActivationRolesHook::init();
            }

            return;
        }

        // Strip the role from users before removing the role definition so
        // usermeta does not resurrect SuperAdmin when Elite is re-enabled.
        if (\wp_roles()->is_role(SuperAdminRoleService::ROLE)) {
            foreach (\get_users(['role' => SuperAdminRoleService::ROLE]) as $user) {
                $user->remove_role(SuperAdminRoleService::ROLE);
            }

            \remove_role(SuperAdminRoleService::ROLE);
        }
    }

    /**
     * Hide SuperAdmin from WP role dropdowns when the licence is not Elite.
     *
     * @param array $roles
     *
     * @return array
     */
    public static function filterEditableRoles($roles)
    {
        if (!SuperAdminRoleService::isAvailable()) {
            unset($roles[SuperAdminRoleService::ROLE]);
        }

        return $roles;
    }

    /**
     * @param string $roleName
     * @param string $roleLabel
     */
    private static function updateRoleLabel($roleName, $roleLabel)
    {
        $wpRoles = \wp_roles();

        if (
            !isset($wpRoles->roles[$roleName]['name']) ||
            $wpRoles->roles[$roleName]['name'] === $roleLabel
        ) {
            return;
        }

        $wpRoles->roles[$roleName]['name'] = $roleLabel;
        $wpRoles->role_names[$roleName] = $roleLabel;

        if ($wpRoles->use_db) {
            \update_option($wpRoles->role_key, $wpRoles->roles);
        }
    }

    /**
     * Return the current user amelia role
     *
     * @param $wpUser
     * @return string|null
     */
    public static function getUserAmeliaRole($wpUser)
    {
        if (
            in_array('administrator', $wpUser->roles, true) ||
            \is_super_admin($wpUser->ID) ||
            (
                SuperAdminRoleService::isAvailable() &&
                in_array(SuperAdminRoleService::ROLE, (array)$wpUser->roles, true)
            )
        ) {
            return 'admin';
        }

        if (in_array('wpamelia-manager', $wpUser->roles, true)) {
            return 'manager';
        }

        if (in_array('wpamelia-provider', $wpUser->roles, true)) {
            return 'provider';
        }

        if (in_array('wpamelia-customer', $wpUser->roles, true)) {
            return 'customer';
        }

        return null;
    }
}
