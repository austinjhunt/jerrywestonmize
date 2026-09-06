<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\ShortcodeService;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Licence\Licence;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;

class ShortcodeAliasService
{
    private const ALIAS_PREFIX = '';

    /**
     * @return array
     */
    public static function getAliases()
    {
        return [
            'booking'                => self::ALIAS_PREFIX . 'booking',
            'search'                 => self::ALIAS_PREFIX . 'search',
            'catalog'                => self::ALIAS_PREFIX . 'catalog',
            'events'                 => self::ALIAS_PREFIX . 'events',
            'stepbooking'            => self::ALIAS_PREFIX . 'step-by-step',
            'catalogbooking'         => self::ALIAS_PREFIX . 'catalog-booking',
            'eventslistbooking'      => self::ALIAS_PREFIX . 'events-list-booking',
            'eventscalendarbooking'  => self::ALIAS_PREFIX . 'events-calendar-booking',
            'customer_panel'         => self::ALIAS_PREFIX . 'customer-panel',
            'employee_panel'         => self::ALIAS_PREFIX . 'employee-panel',
        ];
    }

    /**
     * @param string $view
     * @param string $legacyTag
     *
     * @return string
     */
    public static function shortcodeTag($view, $legacyTag)
    {
        $aliases = self::getAliases();

        return isset($aliases[$view]) ? $aliases[$view] : $legacyTag;
    }

    /**
     * @param string $label
     * @param bool   $stripShortPrefix
     *
     * @return string
     */
    public static function neutralLabel($label, $stripShortPrefix = false)
    {
        $patterns = [
            '/^Amelia\s-\s/i',
            '/\bAmelia\s+/i',
            '/^Booking\s-\s/i',
            '/^Booking\s+/i',
            '/\s\(Legacy\)$/',
        ];

        if ($stripShortPrefix) {
            array_unshift($patterns, '/^AM\s-\s/i');
        }

        return trim(preg_replace($patterns, '', $label));
    }

    /**
     * @param SettingsService|null $settingsService
     *
     * @return bool
     */
    public static function shouldUseNeutralShortcodes($settingsService = null)
    {
        $settingsService = $settingsService ?: new SettingsService(new SettingsStorage());

        return self::isWhiteLabelFeatureEnabled($settingsService);
    }

    /**
     * @param string               $view
     * @param string               $legacyTag
     * @param SettingsService|null $settingsService
     *
     * @return string
     */
    public static function activeShortcodeTag($view, $legacyTag, $settingsService = null)
    {
        return self::shouldUseNeutralShortcodes($settingsService)
            ? self::shortcodeTag($view, $legacyTag)
            : $legacyTag;
    }

    /**
     * @param SettingsService|null $settingsService
     *
     * @return string
     */
    public static function getBuilderBrandName($settingsService = null)
    {
        if (!self::shouldUseNeutralShortcodes($settingsService)) {
            return 'Amelia';
        }

        $pluginName = self::getWhiteLabelPluginName($settingsService);

        return $pluginName !== '' ? $pluginName : 'Amelia';
    }

    /**
     * @param SettingsService|null $settingsService
     *
     * @return string
     */
    public static function getWhiteLabelPluginName($settingsService = null)
    {
        $settingsService = $settingsService ?: new SettingsService(new SettingsStorage());

        if (!self::isWhiteLabelFeatureEnabled($settingsService)) {
            return '';
        }

        $whiteLabel = $settingsService->getCategorySettings('whiteLabel');

        return !empty($whiteLabel['pluginName']) ? sanitize_text_field(trim($whiteLabel['pluginName'])) : '';
    }

    /**
     * Resolve white-label logo URL (thumb preferred, full as fallback).
     *
     * @param SettingsService|null $settingsService
     *
     * @return string
     */
    public static function getWhiteLabelPluginImage($settingsService = null)
    {
        $settingsService = $settingsService ?: new SettingsService(new SettingsStorage());

        if (!self::isWhiteLabelFeatureEnabled($settingsService)) {
            return '';
        }

        $whiteLabel = $settingsService->getCategorySettings('whiteLabel');

        if (!empty($whiteLabel['pictureThumbPath'])) {
            return esc_url_raw($whiteLabel['pictureThumbPath']);
        }

        return !empty($whiteLabel['pictureFullPath']) ? esc_url_raw($whiteLabel['pictureFullPath']) : '';
    }

    /**
     * @param SettingsService $settingsService
     *
     * @return bool
     */
    private static function isWhiteLabelFeatureEnabled($settingsService)
    {
        $featuresIntegrations = $settingsService->getCategorySettings('featuresIntegrations');
        $whiteLabelFeature = isset($featuresIntegrations['whiteLabel']) ? $featuresIntegrations['whiteLabel'] : null;

        return self::isFeatureToggleEnabled($whiteLabelFeature) &&
            Licence::isFeatureEnabledWithLicense('whiteLabel', $whiteLabelFeature);
    }

    /**
     * @param array|null $feature
     *
     * @return bool
     */
    private static function isFeatureToggleEnabled($feature)
    {
        return is_array($feature) &&
            array_key_exists('enabled', $feature) &&
            in_array($feature['enabled'], [true, 1, '1'], true);
    }
}
