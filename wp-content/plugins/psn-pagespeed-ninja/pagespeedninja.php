<?php
/**
 * PageSpeed Ninja
 *
 * @link              https://pagespeed.ninja
 * @wordpress-plugin
 * Plugin Name:       PageSpeed Ninja
 * Plugin URI:        https://pagespeed.ninja/download/
 * Description:       Unleash lightning fast page speed performance: image, CSS, JS optimization, lazy loading, compression, caching, convert to WebP, and more!
 * Version:           1.4.5
 * Requires at least: 4.6
 * Requires PHP:      5.6
 * Author:            PageSpeed Ninja
 * Author URI:        https://pagespeed.ninja/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       psn-pagespeed-ninja
 * Network:           true
 */

defined('WPINC') || die;

/** @return void */
function run_pagespeedninja()
{
    define('PAGESPEEDNINJA_PLUGIN_FILE', __FILE__);

    // optional error logging (start before instantiating the PagespeedNinja class)
    $options = get_option('pagespeedninja_config');
    if (!empty($options['errorlogging'])) {
        include_once __DIR__ . '/includes/class-pagespeedninja-errorlogging.php';
        PagespeedNinja_ErrorLogging::init();
    }

    require __DIR__ . '/includes/class-pagespeedninja.php';
    $plugin_name = basename(__FILE__, '.php');
    $plugin_slug = basename(__DIR__);
    $plugin = new PagespeedNinja($plugin_slug, $plugin_name);

    register_activation_hook(__FILE__, array($plugin, 'activate'));
    register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));

    $plugin->run();
}

run_pagespeedninja();
