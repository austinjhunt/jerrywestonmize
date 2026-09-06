<?php

namespace AmeliaBooking\Infrastructure\WP\UserRoles;

use AmeliaBooking\Infrastructure\Licence\Licence;
use AmeliaBooking\Infrastructure\Licence\LicenceConstants;

/**
 * Centralizes SuperAdmin role checks and role membership changes.
 *
 * SuperAdmin is Elite (Developer) only — it exists to protect White Label /
 * Activation for agency workflows that are not available on lower licences.
 */
class SuperAdminRoleService
{
    public const ROLE = 'wpamelia-super-admin';
    public const CAPABILITY = 'amelia_super_admin';
    private const ROLE_LOCK_NAME = 'amelia_super_admin_role_lock';
    private const BLOCKED_GRANT_ROLES = [
        'wpamelia-customer',
        'wpamelia-provider',
        'wpamelia-manager',
    ];

    /**
     * SuperAdmin is only available on Elite (Developer) licence.
     */
    public static function isAvailable(): bool
    {
        return Licence::hasLicenseAccess(LicenceConstants::DEVELOPER);
    }

    public function isCurrentUserSuperAdmin(): bool
    {
        return self::isAvailable() && current_user_can(self::CAPABILITY);
    }

    public function canAccessActivationSettings(): bool
    {
        // Without Elite there is no SuperAdmin gate — Activation stays open to
        // users who already have settings access.
        if (!self::isAvailable()) {
            return true;
        }

        if ($this->isCurrentUserSuperAdmin()) {
            return true;
        }

        // Bootstrap / recovery: no SuperAdmins yet (first activation, or role
        // restored after switching back to Elite).
        return $this->countSuperAdmins() === 0;
    }

    public function countSuperAdmins(): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        return count($this->getSuperAdminWpUsers());
    }

    public function grant(int $userId): bool
    {
        if (!self::isAvailable()) {
            return true;
        }

        $user = get_user_by('id', (int)$userId);

        if (!$user) {
            return false;
        }

        if (in_array(self::ROLE, (array)$user->roles, true)) {
            self::removeSecondaryAmeliaRoles((int)$user->ID);
            return true;
        }

        return $this->withRoleLock(function () use ($user): bool {
            $user = get_user_by('id', (int)$user->ID);

            if (!$user) {
                return false;
            }

            foreach (self::BLOCKED_GRANT_ROLES as $role) {
                if (in_array($role, (array)$user->roles, true)) {
                    $user->remove_role($role);
                }
            }

            $user = get_user_by('id', (int)$user->ID);

            if (!$user) {
                return false;
            }

            if (in_array(self::ROLE, (array)$user->roles, true)) {
                return true;
            }

            $user->add_role(self::ROLE);

            return true;
        });
    }

    public static function removeSecondaryAmeliaRoles(int $userId): void
    {
        $user = get_user_by('id', (int)$userId);

        if (!$user || !self::userHasRole((int)$user->ID)) {
            return;
        }

        foreach (self::BLOCKED_GRANT_ROLES as $role) {
            if (in_array($role, (array)$user->roles, true)) {
                $user->remove_role($role);
            }
        }
    }

    public static function userHasRole(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $user = get_user_by('id', (int)$userId);

        return $user && in_array(self::ROLE, (array)$user->roles, true);
    }

    private function getSuperAdminWpUsers(): array
    {
        return get_users([
            'role__in' => [self::ROLE],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ]);
    }

    private function withRoleLock(callable $callback): bool
    {
        global $wpdb;

        $lockAcquired = (int)$wpdb->get_var(
            $wpdb->prepare('SELECT GET_LOCK(%s, 5)', self::ROLE_LOCK_NAME)
        );

        if ($lockAcquired !== 1) {
            return false;
        }

        try {
            return (bool)$callback();
        } finally {
            $wpdb->get_var(
                $wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::ROLE_LOCK_NAME)
            );
        }
    }
}
