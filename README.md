# jerrywestonmize.com

## Colibri Theme Update Documentation

When you update the theme, theme file edits are cleared away. These modifications need to be added back after updating the colibri theme.

### `header.php`

```

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
	<?php acf_form_head();?>
    <?php wp_head(); ?>
    <?php colibriwp_theme()->get( 'css' )->render(); ?>
</head>

<body id="colibri" <?php body_class(); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
    wp_body_open();
} else {
    do_action( 'wp_body_open' );
}
?>


<div class="site" id="page-top">
    <?php colibriwp_theme()->get( 'header' )->render(); ?>

```

### `functions.php`

```
<?php

/**
 *
 * Sets up theme defaults and registers support for various WordPress features.
 *
 */

if ( ! defined( 'COLIBRI_THEME_REQUIRED_PHP_VERSION' ) ) {
	define( 'COLIBRI_THEME_REQUIRED_PHP_VERSION', '5.6.0' );
}


// begin mods
// change the logout url
add_filter( 'logout_url', 'jwm_logout_url' );
function jwm_logout_url( $default )
{
    // set your URL here
    // // Parse the URL into its components.
	$parsedUrl = parse_url($default);

	// Get the query string from the parsed URL.
	$queryString = $parsedUrl['query'];

	// Remove the leading question mark from the query string.
    return  'https://jerrywestonmize.com/jwmsecure.php?' . $queryString  ;
}

// change the behavior after log out completion (default redirects to wp-login.php)
add_action('wp_logout','jwm_logout_complete');

function jwm_logout_complete(){
  wp_redirect( site_url() );
  exit();
}

add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar() {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
}

// end of mods

add_action( 'after_switch_theme', 'colibriwp_check_php_version' );.....

.......





```
