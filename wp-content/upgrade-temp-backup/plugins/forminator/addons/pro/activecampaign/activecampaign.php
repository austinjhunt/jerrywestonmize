<?php

/**
 * Integration Name: Activecampaign
 * Version: 1.0
 * Plugin URI:  https://wpmudev.com/
 * Description: Integrate Forminator Custom Forms with Activecampaign to get notified in real time.
 * Author: WPMU DEV
 * Author URI: http://wpmudev.com
 */

define( 'FORMINATOR_ADDON_ACTIVECAMPAIGN_VERSION', '1.0' );

function forminator_addon_activecampaign_dir() {
	return trailingslashit( dirname( __FILE__ ) );
}
Forminator_Integration_Loader::get_instance()->register( 'activecampaign' );
