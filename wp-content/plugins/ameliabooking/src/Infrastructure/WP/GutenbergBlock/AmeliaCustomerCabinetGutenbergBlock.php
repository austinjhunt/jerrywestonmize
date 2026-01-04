<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

/**
 * Class AmeliaCustomerCabinetGutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class AmeliaCustomerCabinetGutenbergBlock extends GutenbergBlock
{
    /**
     * Register Amelia Search block for gutenberg
     */
    public static function registerBlockType()
    {
        // Enqueue shared icon
        parent::enqueueSharedIcon();

        wp_enqueue_script(
            'amelia_customer_cabinet_gutenberg_block',
            AMELIA_URL . 'public/js/gutenberg/amelia-cabinet/amelia-customer-cabinet-gutenberg.js',
            array('wp-blocks', 'wp-components', 'wp-element', 'wp-editor', 'amelia_block_icon')
        );

        register_block_type(
            'amelia/customer-cabinet-gutenberg-block',
            array('editor_script' => 'amelia_customer_cabinet_gutenberg_block')
        );
    }
}
