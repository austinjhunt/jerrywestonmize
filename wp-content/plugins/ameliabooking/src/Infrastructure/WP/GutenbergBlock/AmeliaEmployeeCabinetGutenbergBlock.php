<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\WP\GutenbergBlock;

/**
 * Class AmeliaEmployeeCabinetGutenbergBlock
 *
 * @package AmeliaBooking\Infrastructure\WP\GutenbergBlock
 */
class AmeliaEmployeeCabinetGutenbergBlock extends GutenbergBlock
{
    /**
     * Register Amelia Employee CabinetGutenberg block for gutenberg
     */
    public static function registerBlockType()
    {
        // Enqueue shared icon
        parent::enqueueSharedIcon();

        wp_enqueue_script(
            'amelia_employee_cabinet_gutenberg_block',
            AMELIA_URL . 'public/js/gutenberg/amelia-cabinet/amelia-employee-cabinet-gutenberg.js',
            array('wp-blocks', 'wp-components', 'wp-element', 'wp-editor', 'amelia_block_icon')
        );

        register_block_type(
            'amelia/employee-cabinet-gutenberg-block',
            array('editor_script' => 'amelia_employee_cabinet_gutenberg_block')
        );
    }
}
