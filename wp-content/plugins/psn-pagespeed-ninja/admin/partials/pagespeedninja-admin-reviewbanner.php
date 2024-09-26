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

?>
<link rel="stylesheet" href="<?php echo esc_attr(plugins_url('/assets/css/pagespeedninja-reviewbanner.css', PAGESPEEDNINJA_PLUGIN_FILE)); ?>">
<div id="psn-review-banner-wrapper">
    <div id="psn-review-banner">
        <div id="psn-review-banner-text">
            <p class="title"><b><?php _e('Love the Speed Boost?', 'psn-pagespeed-ninja'); ?> ⚡️⚡️⚡️⚡️⚡️</b></p>
            <p>
                <?php _e('Help others discover the power of PageSpeed Ninja by sharing your experience on WP.org', 'psn-pagespeed-ninja'); ?>&nbsp;<b>⭐⭐⭐⭐⭐</b>
                <i><?php _e('(No pressure, just good karma!)', 'psn-pagespeed-ninja'); ?></i>
            </p>
        </div>
        <div id="psn-review-banner-btn">
            <a href="https://login.wordpress.org/?redirect_to=https%3A%2F%2Fwordpress.org%2Fsupport%2Fplugin%2Fpsn-pagespeed-ninja%2Freviews%2F%3Frate=5%23new-post" target="_blank"><?php _e('Review Now', 'psn-pagespeed-ninja'); ?></a>
        </div>
    </div>
</div>
