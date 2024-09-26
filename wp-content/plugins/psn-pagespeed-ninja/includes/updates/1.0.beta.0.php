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
defined('ABSPATH') || die();

/** @var array $config */
/** @var string $prev_version */
/** @var string $version */

// Migrate settings from 0.9 to 1.0
$config['psi_uses-text-compression'] = $config['psi_EnableGzipCompression'];
$config['psi_uses-long-cache-ttl'] = $config['psi_LeverageBrowserCaching'];
$config['psi_server-response-time'] = $config['psi_MainResourceServerResponseTime'];
$config['psi_total-byte-weight'] = ($config['psi_MinifyCss'] || $config['psi_MinifyHTML'] || $config['psi_MinifyJavaScript']) ? 1 : 0;
$config['psi_render-blocking-resources'] = $config['psi_MinimizeRenderBlockingResources'];
$config['psi_uses-optimized-images'] = $config['psi_OptimizeImages'];
$config['psi_offscreen-images'] = $config['psi_PrioritizeVisibleContent'];
$config['psi_dom-size'] = $config['psi_AvoidPlugins'];
$config['psi_viewport'] = $config['psi_ConfigureViewport'];
$config['psi_unsized-images'] = $config['psi_OptimizeImages'];

$config['js_automove'] = $config['js_autoasync'];
$config['img_lazyload_lqip'] = $config['img_lazyload_lqip'] ? 'full' : 'none';

switch ($config['css_di_cssMinify']) {
    case 'csstidy':
    case 'both':
        $config['css_di_cssMinify'] = 'ress';
}

if (!function_exists('_psn_updater_100_convertRules')) {
    function _psn_updater_100_convertRules($rules, $attr)
    {
        if (empty($rules)) {
            return '';
        }
        $newrules = array();
        foreach (explode("\n", $rules) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $newrules[] = "$attr=$line";
            }
        }
        return implode("\n", $newrules);
    }
}

$config['css_rules_merge_exclude'] = _psn_updater_100_convertRules($config['css_excludelist'], 'href');
$config['js_rules_merge_exclude'] = _psn_updater_100_convertRules($config['js_excludelist'], 'src');

unset(
    $config['css_excludelist'],
    $config['js_excludelist'],
    $config['img_bufferwidth'],
    $config['img_lazyload_addsrcset'],
    $config['img_scaletype'],
    $config['img_templatewidth'],
    $config['img_wideimgclass'],
    $config['img_wrapwide'],
    $config['ress_caching_ttl'],

    $config['psi_AvoidLandingPageRedirects'],
    $config['psi_EnableGzipCompression'],
    $config['psi_LeverageBrowserCaching'],
    $config['psi_MainResourceServerResponseTime'],
    $config['psi_MinifyCss'],
    $config['psi_MinifyHTML'],
    $config['psi_MinifyJavaScript'],
    $config['psi_MinimizeRenderBlockingResources'],
    $config['psi_OptimizeImages'],
    $config['psi_PrioritizeVisibleContent'],
    $config['psi_AvoidPlugins'],
    $config['psi_ConfigureViewport'],
    $config['psi_SizeContentToViewport'],
    $config['psi_SizeTapTargetsAppropriately'],
    $config['psi_UseLegibleFontSizes'],
);


// Drop psninja_amdd and psninja_amdd_cache tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->base_prefix}psninja_amdd`");
$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->base_prefix}psninja_amdd_cache`");

$plugin_path = dirname(dirname(__DIR__));

// Move optimized images to new directory /s/img
$logFilename = "$plugin_path/ress/imgoptimizer.log";
if (is_file($logFilename)) {
    $img_dir = ABSPATH . trim($config['staticdir'], '/') . '/img/';
    $lenABSPATH = strlen(ABSPATH);
    foreach (explode("\n", file_get_contents($logFilename)) as $srcdest) {
        if (preg_match('#"(.*?)"="(.*?)"#', $srcdest, $matches)) {
            $image_backup = $matches[2];
            if (is_file($image_backup)) {
                $image = $matches[1];
                if ($image !== '' && is_file($image)) {
                    $timeStamp_image = filemtime($image);
                    $timeStamp_backup = filemtime($image_backup);
                    if ($timeStamp_image === $timeStamp_backup) {
                        if (strncmp($image, ABSPATH, $lenABSPATH) === 0) {
                            $target = $img_dir . substr($image, $lenABSPATH);
                            mkdir(dirname($target), 0775, true);
                            if (!rename($image, $target)) {
                                unlink($image);
                            }
                        }
                        if (!rename($image_backup, $image)) {
                            if (copy($image_backup, $image)) {
                                touch($image, $timeStamp_backup);
                            }
                        }
                    }
                }
                @unlink($image_backup);
            }
        }
    }

    unlink($logFilename);
}
