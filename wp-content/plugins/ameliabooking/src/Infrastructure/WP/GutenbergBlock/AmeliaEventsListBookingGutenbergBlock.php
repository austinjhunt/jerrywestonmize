<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\Licence;

/**
 * Class AmeliaEventsListBookingGutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class AmeliaEventsListBookingGutenbergBlock extends GutenbergBlock
{
    /**
     * Register Amelia Events block for Gutenberg
     */
    public static function registerBlockType()
    {
        // Enqueue shared icon
        parent::enqueueSharedIcon();

        wp_enqueue_script(
            'amelia_events_list_booking_gutenberg_block',
            AMELIA_URL . 'public/js/gutenberg/amelia-events-list-booking/amelia-events-list-booking-gutenberg.js',
            array('wp-blocks', 'wp-components', 'wp-element', 'wp-editor', 'amelia_block_icon')
        );

        wp_localize_script(
            'amelia_events_list_booking_gutenberg_block',
            'wpAmeliaLabels',
            array_merge(
                BackendStrings::getAllStrings(),
                self::getEntitiesData(),
                array('isLite' => !Licence\Licence::isPremium())
            )
        );

        wp_enqueue_style(
            'amelia_events_list_booking_gutenberg_styles',
            AMELIA_URL . 'public/js/gutenberg/amelia-events-list-booking/amelia-gutenberg-styles.css',
            [],
            AMELIA_VERSION
        );

        register_block_type(
            'amelia/events-list-booking-gutenberg-block',
            array('editor_script' => 'amelia_events_list_booking_gutenberg_block')
        );
    }
}
