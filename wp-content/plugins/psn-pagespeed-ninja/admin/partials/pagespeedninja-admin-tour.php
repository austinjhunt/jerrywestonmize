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

?>
<link rel="stylesheet" href="<?php echo esc_attr(plugins_url('/assets/css/driver.css', PAGESPEEDNINJA_PLUGIN_FILE)); ?>">
<style>
    .driver-popover *,
    .driver-popover-title,
    .driver-popover-description,
    .driver-popover-footer button {
        font-family: Montserrat, Verdana, 'DejaVu Sans', system-ui, sans-serif;
    }

    .driver-popover {
        max-width: 400px;
        padding: 16px 24px;
        border-radius: 0;
    }

    .driver-popover-close-btn {
        font-size: 1.5rem;
    }

    .driver-popover-title {
        padding: 0 16px 4px 0;
    }

    .driver-popover-description {
        line-height: 1.7;
    }

    .driver-popover-progress-text {
        margin-right: 24px;
    }

    .driver-popover-footer {
        margin-top: 24px;
    }

    .driver-popover-footer button {
        padding: 8px 16px;
        min-width: 100px;
        border-radius: 0;
        font-size: 14px;
        line-height: 34px;
        text-transform: uppercase;
        text-align: center;
        font-weight: bold;
        text-shadow: none;
    }

    .driver-popover-navigation-btns button+button {
        margin-left: 16px;
    }

    .driver-popover-prev-btn {
        border: 1px solid #2d2d2d;
    }

    button.driver-popover-next-btn, button.driver-popover-next-btn:hover {
        border: 1px solid #1088FF;
        background-color: #1088FF;
        color: #fff;
    }
</style>
<script defer src="<?php echo esc_attr(plugins_url('/assets/js/driver.js.iife.js', PAGESPEEDNINJA_PLUGIN_FILE)); ?>"></script>
<script>
    window.addEventListener('load', function () {
        var pro = document.getElementById('psn-pro-banner');
        pro && (pro.style.position = 'static');
        window.driver.js.driver({
            showProgress: true,
            smoothScroll: true,
            progressText: "<?php printf(__('%1$s of %2$s', 'psn-pagespeed-ninja'), '{{current}}', '{{total}}'); ?>",
            prevBtnText: "<?php _e('Previous', 'psn-pagespeed-ninja'); ?>",
            nextBtnText: "<?php _e('Next', 'psn-pagespeed-ninja'); ?>",
            doneBtnText: "<?php _e('Done', 'psn-pagespeed-ninja'); ?>",
            steps: [
                { popover: {
                        title: "<?php _e('Welcome to PageSpeed Ninja!', 'psn-pagespeed-ninja'); ?>",
                        description: "<?php _e('Let\'s explore the interface and its features.', 'psn-pagespeed-ninja'); ?>"
                    } },
                { element: '#pagespeedninja-content #desktop .gps', popover: {
                        side: 'bottom',
                        title: "<?php _e('PageSpeed Insights Scores', 'psn-pagespeed-ninja'); ?>",
                        description: "<?php _e('View both unoptimized and optimized Google PageSpeed Insights scores for your homepage. After changing the settings, an estimated score is also displayed.', 'psn-pagespeed-ninja'); ?>"
                    } },
                { element: '#pagespeedninja-content #desktop .settings', popover: {
                        side: 'top',
                        align: 'start',
                        title: "<?php _e('Settings', 'psn-pagespeed-ninja'); ?>",
                        description: "<?php _e('Customize the optimization settings. All settings are initially disabled for safety. After running the PageSpeed Insights analysis, the list of settings is divided into three groups: Should Fix, Consider Fixing, and Passed.', 'psn-pagespeed-ninja'); ?>"
                    } },
                { element: '#pagespeedninja-content .save', popover: {
                        title: "<?php _e('Save Button', 'psn-pagespeed-ninja'); ?>",
                        description: "<?php _e('Click here to apply your new settings.', 'psn-pagespeed-ninja'); ?>"
                    } },
                { element: '#pagespeedninja-content .tabs .advanced', popover: {
                        title: "<?php _e('Advanced Settings', 'psn-pagespeed-ninja'); ?>",
                        description: "<?php _e('Fine-tune optimization options or choose from alternative presets.', 'psn-pagespeed-ninja'); ?>"
                    } },
                <?php if (!apply_filters('psn_is_pro', false)): ?>
                { element: '#psn-pro-banner a', popover: {
                        side: 'bottom',
                        title: "<?php _e('Upgrade to Pro', 'psn-pagespeed-ninja'); ?>",
                        description: "<?php _e('Enjoy advanced features and additional amazing optimizations with the Pro version of PageSpeed Ninja.', 'psn-pagespeed-ninja'); ?>"
                    } },
                <?php endif; ?>
            ],
            onDestroyed: function () { pro && pro.style.removeProperty('position'); },
        }).drive();
    });
</script>
