<?php

/**
 * Integration Name: Webhook
 * Version: 1.0
 * Plugin URI:  https://wpmudev.com/
 * Description: Integrate Forminator Custom Forms with Webhook to execute various action you like
 * Author: WPMU DEV
 * Author URI: http://wpmudev.com
 */
define( 'FORMINATOR_ADDON_WEBHOOK_VERSION', '1.0' );

function forminator_addon_webhook_dir() {
	return trailingslashit( dirname( __FILE__ ) );
}

Forminator_Integration_Loader::get_instance()->register( 'webhook' );
