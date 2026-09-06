<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace Elementor;

use AmeliaBooking\Infrastructure\WP\ShortcodeService\ShortcodeAliasService;

class AmeliaElementorWhiteLabelHelper
{
    public static function shortcodeTag(string $view, string $legacyTag): string
    {
        return ShortcodeAliasService::activeShortcodeTag($view, $legacyTag);
    }

    public static function label(string $label): string
    {
        // Keep custom name, or default "Amelia" when white-label has no plugin name.
        return str_replace(
            '{pluginName}',
            ShortcodeAliasService::getBuilderBrandName(),
            $label
        );
    }

    /**
     * Always use Amelia logo class.
     * Custom white-label image is applied via CSS when uploaded (same as Divi).
     */
    public static function icon(): string
    {
        return 'amelia-logo';
    }

    public static function categoryTitle(): string
    {
        return ShortcodeAliasService::getBuilderBrandName();
    }
}
