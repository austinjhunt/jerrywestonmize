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

// Migrate fonts loading method
switch ($config['css_googlefonts']) {
    case 'foit':
    case 'first':
    case 'sync':
        $config['css_googlefonts'] = 'auto';
        break;
    case 'fout':
    case 'async':
        $config['css_googlefonts'] = 'swap';
        break;
}

$config['css_fontdisplay'] = $config['css_fontdisplayswap'] ? 'swap' : '';
unset($config['css_fontdisplayswap']);
