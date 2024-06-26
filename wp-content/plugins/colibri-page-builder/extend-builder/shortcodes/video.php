<?php
namespace ExtendBuilder;

add_shortcode( 'colibri_video_player', function ( $atts ) {
	ob_start();
	if ( key_exists( 'type', $atts ) && $atts['type'] === 'external' ) {
		colibri_html_embed_iframe( $atts['url'], $atts['autoplay'] );
	} else {
		colibri_html_embed_video( $atts['url'], $atts['attributes'] );
	}
	$content = ob_get_clean();

	return $content;
} );

function colibri_html_embed_iframe($url,$autoplay){
    echo "<iframe src=".esc_url($url)." class='h-video-main'".(($autoplay === 'true') ? 'allow="autoplay"' : '')."  allowfullscreen></iframe>";
}

function colibri_html_embed_video( $url, $attributes ) {
	$attrs         = explode( " ", $attributes );
	$allowed_attrs = [
		'controls',
		'muted',
		'loop',
		'autoplay'
	];

	$filtered_attrs = array_filter( $attrs, function ( $attr ) use ( $allowed_attrs ) {
		[ $name ] = explode( "=", $attr );
		if ( ! in_array( $name, $allowed_attrs ) ) {
			return false;
		}

		return true;
	} );

	$sanitized_attrs = array_map( function ( $attr ) {
		if ( ! str_contains( $attr, "=" ) ) {
			return preg_replace( '/[^[a-z]*/i', "", $attr );
		}

		[ $name, $value ] = explode( "=", $attr );
		$sanitized_name  = preg_replace( '/[^[a-z]*/i', "", $name );
		$sanitized_value = preg_replace( '/[^[a-z|A-Z|0-9]*/i', "", $value );

		return implode( '=', array( $sanitized_name, $sanitized_value ) );
	}, $filtered_attrs );

	echo "<video class='h-video-main' " . esc_attr( implode( " ", $sanitized_attrs ) ) . " ><source src=" . esc_url( $url ) . " type='video/mp4' /></video>";
}


