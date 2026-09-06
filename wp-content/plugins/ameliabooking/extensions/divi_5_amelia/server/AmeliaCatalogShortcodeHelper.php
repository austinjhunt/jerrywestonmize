<?php

namespace Divi5Amelia;

/**
 * Shared catalog shortcode helpers for Divi 5 renderers.
 */
class AmeliaCatalogShortcodeHelper
{
    /**
     * Build the show= fragment for catalog-booking shortcodes.
     *
     * Mirrors TinyMCE (catalogbooking) and Elementor catalog-booking gating.
     *
     * @param string     $catalogView
     * @param mixed|null $typeValue
     *
     * @return string
     */
    public static function buildCatalogBookingShowAttribute(string $catalogView, $typeValue): string
    {
        if ($typeValue === null || $typeValue === '' || $typeValue === '0') {
            return '';
        }

        if ($catalogView === 'package') {
            return '';
        }

        if ($catalogView === 'service' && $typeValue === 'packages') {
            return '';
        }

        return ' show=' . esc_attr((string) $typeValue);
    }
}
