<?php

/*
Plugin Name: Divi Amelia
Plugin URI:
Description:
Version:     1.0.0
Author:
Author URI:
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: divi-divi_amelia
Domain Path: /languages

Divi Amelia is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Divi Amelia is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Divi Amelia. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\WP\SettingsService\SettingsStorage;
use AmeliaBooking\Infrastructure\WP\ShortcodeService\ShortcodeAliasService;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

if (! function_exists('divi_initialize_extension_amelia')) :
/**
 * Creates the extension's main class instance.
 *
 * @since 1.0.0
 */
    function divi_initialize_extension_amelia()
    {
        require_once plugin_dir_path(__FILE__) . 'includes/DiviAmelia.php';

        wp_register_style('wpamelia-divi', plugins_url('styles/divi-amelia.css', __FILE__), [], AMELIA_VERSION);
        wp_enqueue_style('wpamelia-divi');

        $settingsService = new SettingsService(new SettingsStorage());
        $useNeutral = ShortcodeAliasService::shouldUseNeutralShortcodes($settingsService);

        // Divi Visual Builder (frontend iframe) does not use admin_body_class.
        if ($useNeutral) {
            wp_register_script('amelia-divi-white-label', false, [], AMELIA_VERSION, false);
            wp_enqueue_script('amelia-divi-white-label');
            wp_add_inline_script(
                'amelia-divi-white-label',
                '(function(){var c="amelia-white-label-active";document.documentElement.classList.add(c);'
                . 'function a(){if(document.body){document.body.classList.add(c)}}'
                . 'document.body?a():document.addEventListener("DOMContentLoaded",a);})();'
            );

            // Custom image uploaded: replace Amelia logo in the module picker only.
            // Canvas shortcode display stays logo-free (same as default Amelia logo behaviour).
            $pluginImage = ShortcodeAliasService::getWhiteLabelPluginImage($settingsService);
            if ($pluginImage !== '') {
                wp_register_style('amelia-white-label-builders', false, ['wpamelia-divi'], AMELIA_VERSION);
                wp_enqueue_style('amelia-white-label-builders');

                $moduleSelectors = [
                    '.divi_customer:before',
                    '.divi_employee:before',
                    '.divi_step_booking:before',
                    '.divi_catalog_booking:before',
                    '.divi_events_list_booking:before',
                    '.divi_events_calendar_booking:before',
                    '.divi_booking:before',
                    '.divi_catalog:before',
                    '.divi_events:before',
                    '.divi_search:before',
                ];
                $selectors = [];
                foreach ($moduleSelectors as $moduleSelector) {
                    $selectors[] = 'html.amelia-white-label-active .et-fb-modules-list ' . $moduleSelector;
                    $selectors[] = 'body.amelia-white-label-active .et-fb-modules-list ' . $moduleSelector;
                }
                $css = implode(', ', $selectors) . '{'
                    . 'content:""!important;'
                    . 'display:block!important;'
                    . 'background-image:url(' . esc_url_raw($pluginImage) . ')!important;'
                    . 'background-repeat:no-repeat!important;'
                    . 'background-position:center center!important;'
                    . 'background-size:contain!important;'
                    . 'height:20px!important;'
                    . 'margin:0 auto 1px!important;'
                    . '}';
                wp_add_inline_style('amelia-white-label-builders', $css);
            }
        }

        add_filter('admin_body_class', static function ($classes) use ($useNeutral) {
            if ($useNeutral) {
                $classes .= ' amelia-white-label-active';
            }

            return $classes;
        });

        add_filter('body_class', static function ($classes) use ($useNeutral) {
            if ($useNeutral) {
                $classes[] = 'amelia-white-label-active';
            }

            return $classes;
        });
    }
    add_action('divi_extensions_init', 'divi_initialize_extension_amelia');
endif;

/**
 * Localize white-label shortcode data for Divi 4 Visual Builder.
 * Helpers live in includes/neutral-labels.js and must load before the builder bundle.
 */
if (! function_exists('divi_amelia_add_white_label_data')) :
    function divi_amelia_add_white_label_data()
    {
        static $printed = false;

        if ($printed) {
            return;
        }

        $isBuilder = (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled())
            || (function_exists('et_fb_is_enabled') && et_fb_is_enabled())
            || (is_admin() && (!empty($_GET['et_fb']) || !empty($_GET['et_pb_preview'])));

        if (!$isBuilder) {
            return;
        }

        $printed = true;

        $settingsService = new SettingsService(new SettingsStorage());

        $localize = 'window.wpAmeliaLabels = window.wpAmeliaLabels || ' . wp_json_encode(BackendStrings::getAllStrings()) . ';'
            . 'window.wpAmeliaUseNeutralShortcodes = ' . wp_json_encode(ShortcodeAliasService::shouldUseNeutralShortcodes($settingsService)) . ';'
            . 'window.wpAmeliaShortcodeAliases = ' . wp_json_encode(ShortcodeAliasService::getAliases()) . ';'
            . 'window.wpAmeliaPluginName = ' . wp_json_encode(ShortcodeAliasService::getWhiteLabelPluginName($settingsService)) . ';'
            . 'window.wpAmeliaBuilderBrandName = ' . wp_json_encode(ShortcodeAliasService::getBuilderBrandName($settingsService)) . ';'
            . 'window.wpAmeliaPluginImage = ' . wp_json_encode(ShortcodeAliasService::getWhiteLabelPluginImage($settingsService)) . ';';

        wp_register_script(
            'amelia-divi-neutral-labels',
            plugins_url('includes/neutral-labels.js', __FILE__),
            [],
            AMELIA_VERSION,
            false
        );
        wp_add_inline_script('amelia-divi-neutral-labels', $localize, 'before');
        wp_enqueue_script('amelia-divi-neutral-labels');
    }
    // Enqueue early so helpers exist before the Divi builder bundle runs.
    add_action('wp_enqueue_scripts', 'divi_amelia_add_white_label_data', 1);
    add_action('admin_enqueue_scripts', 'divi_amelia_add_white_label_data', 1);
    // VB iframe / late boot paths.
    add_action('wp_head', 'divi_amelia_add_white_label_data', 1);
    add_action('admin_head', 'divi_amelia_add_white_label_data', 1);
    add_action('et_fb_framework_loaded', 'divi_amelia_add_white_label_data');
endif;
