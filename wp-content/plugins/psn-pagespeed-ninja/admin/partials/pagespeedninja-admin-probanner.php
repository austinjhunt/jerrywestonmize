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
/** @var PagespeedNinja_View $this */

if (apply_filters('psn_is_pro', false)) {
    return;
}

?>
<link rel="stylesheet" href="<?php echo esc_attr(plugins_url('/assets/css/pagespeedninja-probanner.css', PAGESPEEDNINJA_PLUGIN_FILE)); ?>">
<div id="psn-pro-banner">
    <span><?php printf(__('Upgrade to %s and Unlock Exclusive Features', 'psn-pagespeed-ninja'), '<b>PageSpeed Ninja Pro</b>'); ?></span>
    <a href="https://pagespeed.ninja/download/?utm_source=psnbackend&utm_medium=Pro-Banner&utm_campaign=pro-banner-upgrade" target="_blank"><?php _e('UPGRADE', 'psn-pagespeed-ninja'); ?></a>
</div>