<?php

/**
 * Integration Name: Slack
 * Version: 1.0
 * Plugin URI:  https://wpmudev.com/
 * Description: Integrate Forminator Custom Forms with Slack to get notified in real time.
 * Author: WPMU DEV
 * Author URI: http://wpmudev.com
 */

define( 'FORMINATOR_ADDON_SLACK_VERSION', '1.1' );

function forminator_addon_slack_dir() {
	return trailingslashit( dirname( __FILE__ ) );
}

Forminator_Integration_Loader::get_instance()->register( 'slack' );
