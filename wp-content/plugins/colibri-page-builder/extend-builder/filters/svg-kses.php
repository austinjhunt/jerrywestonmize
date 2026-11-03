<?php
namespace ExtendBuilder;

/**
 * Widens the 'post' kses list with the SVG markup the builder renders through wp_kses_post().
 *
 * wp_kses_allowed_html runs for every context, not only 'post': comments, user bios and explicit
 * lists pass through here too. Those must keep WordPress' own list, otherwise an anonymous
 * commenter can store these elements with arbitrary data-* attributes, which colibri.js reads.
 *
 * @param array  $allowed_html Elements, and the attributes allowed on them.
 * @param string $context      Context the sanitisation is running in.
 * @return array
 */
function colibri_get_svg_kses_allowed_elements( $allowed_html = array(), $context = '' ) {

	if ( 'post' !== $context ) {
		return $allowed_html;
	}

	$svg_elements = array(
		'svg'     =>
		array(
			'xmlns',
			'viewbox',
			'id',
			'data-name',
			'width',
			'height',
			'version',
			'xmlns:xlink',
			'x',
			'y',
			'enable-background',
			'xml:space',
		),
		'path'    =>
		array(
			'd',
			'id',
			'class',
			'data-name',
		),
		'g'       =>
		array(
			'id',
			'stroke',
			'stroke-width',
			'fill',
			'fill-rule',
			'transform',
		),
		'title'   =>
		array(),
		'polygon' =>
		array(
			'id',
			'points',
		),
		'rect'    =>
		array(
			'x',
			'y',
			'width',
			'height',
			'transform',
		),
		'circle'  =>
		array(
			'cx',
			'cy',
			'r',
		),
		'ellipse' =>
		array(
			'cx',
			'cy',
			'rx',
			'ry',
		),
	);

	$shared_attrs = array( 'data-*', 'id', 'class' );

	foreach ( $svg_elements as $element => $attrs ) {
		if ( ! isset( $allowed_html[ $element ] ) ) {
			$allowed_html[ $element ] = array();
		}

		$allowed_html[ $element ] = array_merge( $allowed_html[ $element ], array_fill_keys( array_merge( $attrs, $shared_attrs ), true ) );
	}

	return $allowed_html;

}

add_filter( 'wp_kses_allowed_html', '\ExtendBuilder\colibri_get_svg_kses_allowed_elements', 10, 2 );
