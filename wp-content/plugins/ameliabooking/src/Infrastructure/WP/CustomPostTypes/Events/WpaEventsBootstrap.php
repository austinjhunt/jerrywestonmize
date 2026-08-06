<?php

/**
 * Bootstraps Amelia event landing CPT.
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events;

use AmeliaBooking\Infrastructure\WP\CustomPostTypes\FrontendSingleCptTemplate;
use AmeliaBooking\Infrastructure\WP\ShortcodeService\AmeliaBookingShortcodeService;

/**
 * Class WpaEventsBootstrap
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events
 */
class WpaEventsBootstrap
{
    public static function init()
    {
        WpaEventsPostType::registerHooks();
        WpaEventsSync::registerHooks();

        $singleTemplate = new FrontendSingleCptTemplate(
            WpaEventsConstants::POST_TYPE,
            'amelia_wpa_events_single',
            AMELIA_URL . 'public/css/frontend/wpa-events-single.css',
            'amelia_wpa_events_single_js',
            AMELIA_URL . 'public/js/frontend/wpa-events-single.js',
            AMELIA_PATH . '/view/frontend/wpa-events-single/wpa-events/wpa-events-single.php'
        );
        $singleTemplate->registerHooks();
        add_action('wp_enqueue_scripts', [self::class, 'preloadShellBlockStylesBeforeGlobalStyles'], 9);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueLandingCustomizeInline'], 35);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueBookingAssets'], 30);

        add_action(
            'init',
            static function () {
                if (get_option(WpaEventsConstants::OPTION_REWRITE_FLUSHED, '') === '1') {
                    return;
                }

                flush_rewrite_rules(false);
                update_option(WpaEventsConstants::OPTION_REWRITE_FLUSHED, '1', false);
            },
            100
        );
    }

    public static function preloadShellBlockStylesBeforeGlobalStyles(): void
    {
        if (!self::shouldPreloadShellBlockStylesForLanding()) {
            return;
        }

        $blockNames = apply_filters(
            'amelia_wpa_events_preload_shell_block_styles',
            self::defaultShellBlockNamesForStylePreload(),
            WpaEventsConstants::POST_TYPE
        );

        if (!is_array($blockNames) || $blockNames === [] || !class_exists('WP_Block_Type_Registry')) {
            return;
        }

        $registry = \WP_Block_Type_Registry::get_instance();
        foreach ($blockNames as $blockName) {
            if (!is_string($blockName) || $blockName === '') {
                continue;
            }
            $blockType = $registry->get_registered($blockName);
            if (!$blockType instanceof \WP_Block_Type) {
                continue;
            }
            self::enqueueRegisteredBlockStyleHandles($blockType);
        }
    }

    private static function shouldPreloadShellBlockStylesForLanding(): bool
    {
        return is_singular(WpaEventsConstants::POST_TYPE)
            && function_exists('wp_is_block_theme')
            && wp_is_block_theme()
            && function_exists('wp_should_load_block_assets_on_demand')
            && wp_should_load_block_assets_on_demand();
    }

    /**
     * @return list<string>
     */
    private static function defaultShellBlockNamesForStylePreload(): array
    {
        return [
            'core/site-logo',
            'core/site-title',
            'core/custom-logo',
            'core/navigation',
            'core/navigation-link',
            'core/navigation-submenu',
            'core/page-list',
            'core/home-link',
            'core/group',
            'core/row',
            'core/stack',
            'core/columns',
            'core/column',
            'core/spacer',
            'core/separator',
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/list-item',
            'core/buttons',
            'core/button',
            'core/social-links',
            'core/social-link',
            'core/image',
            'core/search',
        ];
    }

    private static function enqueueRegisteredBlockStyleHandles(\WP_Block_Type $blockType): void
    {
        $handles = array_merge(
            (array) $blockType->style_handles,
            (array) $blockType->view_style_handles
        );
        foreach ($handles as $handle) {
            if (is_string($handle) && $handle !== '') {
                wp_enqueue_style($handle);
            }
        }
    }

    public static function enqueueLandingCustomizeInline(): void
    {
        if (!is_singular(WpaEventsConstants::POST_TYPE)) {
            return;
        }

        if (!wp_style_is('amelia_wpa_events_single', 'enqueued')) {
            return;
        }

        $css = WpaEventsLandingCustomizeCss::buildInlineCss();
        if ($css !== '') {
            wp_add_inline_style('amelia_wpa_events_single', $css);
        }
    }

    public static function enqueueBookingAssets()
    {
        if (!is_singular(WpaEventsConstants::POST_TYPE)) {
            return;
        }

        $postId = get_queried_object_id();
        if (!$postId) {
            return;
        }

        $ameliaEventId = (int)get_post_meta($postId, WpaEventsConstants::META_AMELIA_EVENT_ID, true);
        if ($ameliaEventId <= 0) {
            return;
        }

        try {
            AmeliaBookingShortcodeService::prepareScriptsAndStyles();
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Failed to prepare booking assets for WPA event landing (post %d): %s',
                $postId,
                $e->getMessage()
            ));
        }
    }
}
