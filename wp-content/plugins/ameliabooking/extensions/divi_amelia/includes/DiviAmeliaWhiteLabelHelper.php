<?php

use AmeliaBooking\Infrastructure\WP\ShortcodeService\ShortcodeAliasService;

class DIVI_AmeliaWhiteLabelHelper
{
    /**
     * Resolve module picker titles.
     *
     * - No white label: "{pluginName} - Form" → "Amelia - Form".
     * - White label + custom plugin name: "CustomName - Form".
     * - White label without custom name: "Amelia - Form" (default).
     *
     * @param string $label
     *
     * @return string
     */
    public static function label($label)
    {
        return str_replace(
            '{pluginName}',
            ShortcodeAliasService::getBuilderBrandName(),
            $label
        );
    }

    public static function shortcodeTag($view, $legacyTag)
    {
        return ShortcodeAliasService::activeShortcodeTag($view, $legacyTag);
    }
}
