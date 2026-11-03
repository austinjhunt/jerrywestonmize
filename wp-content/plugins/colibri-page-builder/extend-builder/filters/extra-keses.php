<?php
namespace ExtendBuilder;
use ColibriWP\PageBuilder\Utils\Utils;

// Only the 'post' context; see colibri_get_svg_kses_allowed_elements() for why.
function colibri_get_extra_kses_elements( $allowed_html = array(), $context = '' ) {

    if ( 'post' !== $context ) {
        return $allowed_html;
    }

    $extra_elements = [
        'input' => [
            'placeholder' => true
        ],
        'textarea' => array(
            'placeholder' => true
        )
    ];
    $svg_elements = array(
        'template'     =>
            array(

            ),

    );

    $shared_attrs = array( 'data-*', 'id', 'class' );

    foreach ( $svg_elements as $element => $attrs ) {
        if ( ! isset( $allowed_html[ $element ] ) ) {
            $allowed_html[ $element ] = array();
        }

        $allowed_html[ $element ] = Utils::mergeArrays( $allowed_html[ $element ], array_fill_keys( array_merge( $attrs, $shared_attrs ), true ) );
    }


    $allowed_html = Utils::mergeArrays($allowed_html, $extra_elements);

    return $allowed_html;

}

add_filter( 'wp_kses_allowed_html', '\ExtendBuilder\colibri_get_extra_kses_elements', PHP_INT_MAX, 2 );
