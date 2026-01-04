<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;
use AmeliaBooking\Infrastructure\Licence;

/**
 * Class AmeliaEventsGutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class AmeliaEventsGutenbergBlock extends GutenbergBlock
{
    /**
     * Register Amelia Events block for Gutenberg
     */
    public static function registerBlockType()
    {
        // Enqueue shared icon
        parent::enqueueSharedIcon();

        wp_enqueue_script(
            'amelia_events_gutenberg_block',
            AMELIA_URL . 'public/js/gutenberg/amelia-events/amelia-events-gutenberg.js',
            array('wp-blocks', 'wp-components', 'wp-element', 'wp-editor', 'amelia_block_icon')
        );

        wp_localize_script(
            'amelia_events_gutenberg_block',
            'wpAmeliaLabels',
            array_merge(
                BackendStrings::getAllStrings(),
                self::getEntitiesData(),
                array('isLite' => !Licence\Licence::isPremium())
            )
        );

        wp_enqueue_style(
            'amelia_events_gutenberg_styles',
            AMELIA_URL . 'public/js/gutenberg/amelia-events/amelia-events-gutenberg.css',
            [],
            AMELIA_VERSION
        );

        register_block_type(
            'amelia/events-gutenberg-block',
            array('editor_script' => 'amelia_events_gutenberg_block')
        );
    }
}
