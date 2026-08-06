<?php

/**
 * Registers the wpa-events CPT for standalone event landing URLs.
 *
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events;

use AmeliaBooking\Infrastructure\WP\Translations\LiteBackendStrings;

/**
 * Class WpaEventsPostType
 *
 * @package AmeliaBooking\Infrastructure\WP\CustomPostTypes\Events
 */
class WpaEventsPostType
{
    /**
     * Register CPT on WordPress init.
     */
    public static function registerHooks()
    {
        add_action('init', [self::class, 'register'], 5);
        add_action('load-edit.php', [self::class, 'blockListScreen']);
        add_action('load-post-new.php', [self::class, 'blockCreateScreen']);
        add_action('admin_head-post.php', [self::class, 'hideCreateButton']);
        add_action('edit_form_after_title', [self::class, 'renderAmeliaManageEventLink']);
    }

    /**
     * Register post type (idempotent).
     */
    public static function register()
    {
        $labels = [
            'name'               => LiteBackendStrings::get('wpa_events_cpt_name'),
            'singular_name'      => LiteBackendStrings::get('wpa_events_cpt_singular_name'),
            'add_new'            => LiteBackendStrings::get('wpa_events_cpt_add_new'),
            'add_new_item'       => LiteBackendStrings::get('wpa_events_cpt_add_new_item'),
            'edit_item'          => LiteBackendStrings::get('wpa_events_cpt_edit_item'),
            'new_item'           => LiteBackendStrings::get('wpa_events_cpt_new_item'),
            'view_item'          => LiteBackendStrings::get('wpa_events_cpt_view_item'),
            'search_items'       => LiteBackendStrings::get('wpa_events_cpt_search_items'),
            'not_found'          => LiteBackendStrings::get('wpa_events_cpt_not_found'),
            'not_found_in_trash' => LiteBackendStrings::get('wpa_events_cpt_not_found_in_trash'),
            'menu_name'          => LiteBackendStrings::get('wpa_events_cpt_menu_name'),
        ];

        register_post_type(
            WpaEventsConstants::POST_TYPE,
            [
                'labels'              => $labels,
                'public'              => true,
                'publicly_queryable'  => true,
                'exclude_from_search' => false,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_admin_bar'   => false,
                'has_archive'         => false,
                'rewrite'             => [
                    'slug'       => WpaEventsConstants::POST_TYPE,
                    'with_front' => false,
                ],
                'supports'            => ['title'],
                'capability_type'     => 'post',
                'capabilities'        => [
                    'create_posts' => 'do_not_allow',
                ],
                'map_meta_cap'        => true,
            ]
        );
    }

    /**
     * Called from plugin activation to flush rewrite rules after CPT exists.
     */
    public static function onActivation()
    {
        self::register();
        flush_rewrite_rules(false);
    }

    public static function blockCreateScreen(): void
    {
        $postType = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'post';

        if ($postType !== WpaEventsConstants::POST_TYPE) {
            return;
        }

        wp_safe_redirect(self::ameliaEventsAdminUrl());

        exit;
    }

    public static function blockListScreen(): void
    {
        $postType = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'post';

        if ($postType !== WpaEventsConstants::POST_TYPE) {
            return;
        }

        wp_safe_redirect(self::ameliaEventsAdminUrl());

        exit;
    }

    public static function hideCreateButton(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || $screen->post_type !== WpaEventsConstants::POST_TYPE) {
            return;
        }

        echo '<style>.post-type-wpa-events .wrap .page-title-action{display:none!important;}</style>';
    }

    private static function ameliaEventsAdminUrl(): string
    {
        return admin_url('admin.php?page=wpamelia-events');
    }

    public static function renderAmeliaManageEventLink($post)
    {
        if (
            !is_object($post) ||
            empty($post->ID) ||
            empty($post->post_type) ||
            $post->post_type !== WpaEventsConstants::POST_TYPE
        ) {
            return;
        }

        $eventId = (int)get_post_meta($post->ID, WpaEventsConstants::META_AMELIA_EVENT_ID, true);

        if ($eventId <= 0) {
            return;
        }

        $manageUrl = self::ameliaEventsAdminUrl() . '#/manage/' . $eventId . '/details';

        echo '<div class="notice notice-info inline" style="margin: 12px 0 0;">';
        echo '<p>';
        echo esc_html(LiteBackendStrings::get('wpa_events_manage_in_amelia_notice')) . ' ';
        echo '<a href="' . esc_url($manageUrl) . '" class="button button-secondary">';
        echo esc_html(LiteBackendStrings::get('wpa_events_open_in_amelia_button'));
        echo '</a>';
        echo '</p>';
        echo '</div>';
    }
}
