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

// Fix video shortcut conflict
if (isset($config['lazyload_rules_video_exclude'])) {
    $config['lazyload_rules_video_exclude'] = trim($config['lazyload_rules_video_exclude'] . "\nclass*=wp-video-shortcode");
}
