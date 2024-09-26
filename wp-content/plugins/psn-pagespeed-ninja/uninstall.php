<?php
/**
 * PageSpeed Ninja
 * https://pagespeed.ninja/
 *
 * @version    1.4.5
 * @license    GNU/GPL v2 - http://www.gnu.org/licenses/gpl-2.0.html
 * @copyright  (C) 2016-2024 PageSpeed Ninja Team
 * @date       September 2024
 */

defined('WP_UNINSTALL_PLUGIN') || die;

// stops worker threads
@touch(WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/cache/worker_config.stamp');

/**
 * @param string $dir
 * @return void
 */
function psn_rmdir_recursive($dir)
{
    $entries = scandir($dir, SCANDIR_SORT_NONE);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_file($path) || is_link($path)) {
            unlink($path);
        } else {
            psn_rmdir_recursive($path);
        }
    }
    rmdir($dir);
}

// uninstalling may take some time
set_time_limit(0);

$homeDir = rtrim(ABSPATH, '/');

// Restore .htaccess
$marker = 'Page Speed Ninja';
$files = array(
    $homeDir . '/wp-includes/.htaccess',
    $homeDir . '/wp-content/.htaccess',
    $homeDir . '/uploads/.htaccess'
);
foreach ($files as $file) {
    if (is_file($file)) {
        insert_with_markers($file, $marker, '');
    }
}

global $wpdb;

/** @var array $config */
$config = get_site_option('pagespeedninja_config');

// Drop static files, plugin settings, and URLs database table
if (is_multisite()) {
    foreach (get_sites() as $site) {
        switch_to_blog($site->blog_id);
        $site_config = get_option('pagespeedninja_config');
        $staticdir = $site_config['staticdir'];
        if (strlen($staticdir) >= 2 && $staticdir[0] === '/') {
            $staticdir = $homeDir . $staticdir;
            if (is_dir($staticdir)) {
                psn_rmdir_recursive($staticdir);
            }
        }
        delete_option('pagespeedninja_config');
        $delete = $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}psninja_urls`");
        wp_unschedule_hook('pagespeedninja_daily_event');
        wp_unschedule_hook('pagespeedninja_minutely_event');
        restore_current_blog();
    }
    delete_site_option('pagespeedninja_config');
} else {
    $staticdir = $config['staticdir'];
    if (strlen($staticdir) >= 2 && $staticdir[0] === '/') {
        $staticdir = $homeDir . $staticdir;
        if (is_dir($staticdir)) {
            psn_rmdir_recursive($staticdir);
        }
    }
    delete_option('pagespeedninja_config');
    $delete = $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}psninja_urls`");
    wp_unschedule_hook('pagespeedninja_daily_event');
    wp_unschedule_hook('pagespeedninja_minutely_event');
}

// Remove advanced-cache.php
$advancedCache = WP_CONTENT_DIR . '/advanced-cache.php';
if (is_file($advancedCache)) {
    $content = file_get_contents($advancedCache);
    if (strpos($content, 'psn-pagespeed-ninja') !== false) {
        @unlink($advancedCache);
    }
}

// Remove caches
psn_rmdir_recursive(WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja');
