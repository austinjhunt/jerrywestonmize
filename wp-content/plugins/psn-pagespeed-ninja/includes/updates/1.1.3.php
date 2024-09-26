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

// Migrate settings from 1.1.2 to 1.1.3
if (!$config['psi_total-byte-weight'] && $config['psi_render-blocking-resources']) {
    $config['js_forcedefer'] = 0;
    $config['js_forceasync'] = 0;
}
