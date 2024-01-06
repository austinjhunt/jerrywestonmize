<?php

/**
 *
 * Sets up theme defaults and registers support for various WordPress features.
 *
 */

if (!defined('COLIBRI_THEME_REQUIRED_PHP_VERSION')) {
	define('COLIBRI_THEME_REQUIRED_PHP_VERSION', '5.6.0');
}


// begin mods
// change the logout url
add_filter('logout_url', 'jwm_logout_url');
function jwm_logout_url($default)
{
	// set your URL here
	// // Parse the URL into its components.
	$parsedUrl = parse_url($default);

	// Get the query string from the parsed URL.
	$queryString = $parsedUrl['query'];

	// Remove the leading question mark from the query string.
	return 'https://jerrywestonmize.com/jwmsecure.php?' . $queryString;
}


// change login url
add_filter('login_url', 'jwm_login_url');
function jwm_login_url($default) {
	$parsedUrl = parse_url($default);
	$queryString = $parsedUrl['query'];
	return 'https://jerrywestonmize.com/jwmsecure.php?' . $queryString;
}



// change the forgot password URL on login page
function jwm_lostpassword_url()
{
	return 'https://jerrywestonmize.com/jwmsecure.php?action=lostpassword';
}
add_filter('lostpassword_url', 'jwm_lostpassword_url');

// change the reset password link in the email you get after clicking forgot password
function custom_reset_password_message($message, $key, $user_login, $user_data)
{
	// Check if the message contains the URL we want to replace
	if (strpos($message, 'wp-login.php?action=rp') !== false) {
		$user_locale = get_user_meta($user_data->ID, 'locale', true);
		$wp_lang = !empty($user_locale) ? $user_locale : 'en_US';
		// Construct the new URL
		$new_url = 'https://jerrywestonmize.com/jwmsecure.php?action=rp&key=' . $key . '&login=' . rawurlencode($user_login) . '&wp_lang=' . $wp_lang;

		$old_url = 'https://jerrywestonmize.com/wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode($user_login) . '&wp_lang=' . $wp_lang;
		// Replace the old URL with the new URL
		$message = str_replace($old_url, $new_url, $message);
	}

	return $message;
}
add_filter('retrieve_password_message', 'custom_reset_password_message', 10, 4);

// change the behavior after log out completion (default redirects to wp-login.php)
add_action('wp_logout', 'jwm_logout_complete');

function jwm_logout_complete()
{
	wp_redirect(site_url());
	exit();
}

add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar()
{
	if (!current_user_can('administrator') && !is_admin()) {
		show_admin_bar(false);
	}
}

// end of mods

add_action('after_switch_theme', 'colibriwp_check_php_version');

function colibriwp_check_php_version()
{
	// Compare versions.
	if (version_compare(phpversion(), COLIBRI_THEME_REQUIRED_PHP_VERSION, '<')):
		// Theme not activated info message.
		add_action('admin_notices', 'colibriwp_php_version_notice');

		// Switch back to previous theme.
		switch_theme(get_option('theme_switched'));

		return false;
	endif;
}

function colibriwp_php_version_notice()
{
	?>
	<div class="notice notice-alt notice-error notice-large">
		<h4>
			<?php esc_html_e('Colibri theme activation failed!', 'colibri-wp'); ?>
		</h4>
		<p>
			<?php printf(
				esc_html__('You need to update your PHP version to use the %s.', 'colibri-wp'),
				' <strong>Colibri</strong>'
			); ?>
			<br />
			<?php printf(
				esc_html__(
					'Current php version is: %1$s and the mininum required version is %2$s',
					'colibri-wp'
				),
				"<strong>" . esc_html(phpversion()) . "</strong>",
				"<strong>" . esc_html(COLIBRI_THEME_REQUIRED_PHP_VERSION) . "</strong>"
			);
			?>

		</p>
	</div>
	<?php
}

if (version_compare(phpversion(), COLIBRI_THEME_REQUIRED_PHP_VERSION, '>=')) {
	require_once get_template_directory() . "/inc/functions.php";
} else {
	add_action('admin_notices', 'colibriwp_php_version_notice');
}
