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

/** @var PagespeedNinja_View $this */

global $parent_file;
$isPSNpage = ($parent_file === $this->admin->get_plugin_name());

?>
<style>
#psn-licensekey-notice-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem 1.5rem;
    padding: 10px 8px;
    line-height: 1.5;
}
#psn-licensekey-notice-text {
    flex: 1 0 0;
}
#psn-licensekey-notice-wrap > form {
    display: flex;
    align-items: center;
    gap: 10px 1.5rem;
    line-height: 2.5rem;
}
#psn-licensekey-notice-wrap b {
    display: block;
    margin-bottom: 4px;
}
@media screen and (max-width: 782px) {
    #psn-licensekey-notice-wrap {
        flex-direction: column;
        align-items: center;
    }
    #psn-licensekey-notice-text {
        align-self: stretch;
        text-align: center;
    }
}
@media screen and (max-width: 360px) {
    #psn-licensekey-notice-wrap {
        padding: 10px 20px;
    }
    #psn-licensekey-notice-wrap > form {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>
<?php

if (!$isPSNpage) {

?>
<script>
jQuery(document).ready(function ($) { setTimeout(function () {
    $('.psn-licensekey-notice .notice-dismiss').on('click keydown', function (e) {
        if (e.type === 'keydown' && e.which !== 13) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        $('.psn-licensekey-notice').remove();
        $.post(ajaxurl, {action: 'pagespeedninja_dismiss_licensekey_notice'});
    });
})});
</script>
<?php

} else {

?>
<link rel="stylesheet" href="<?php echo esc_attr(plugins_url('/assets/css/pagespeedninja-getfreelicensekey.css', PAGESPEEDNINJA_PLUGIN_FILE)); ?>">
<?php

}

global $current_user;

?>
<div class="pagespeedninja psn-licensekey-notice notice notice-alt notice-large notice-info<?php echo $isPSNpage ? '' : ' is-dismissible'; ?>">
    <div id="psn-licensekey-notice-wrap">
        <div id="psn-licensekey-notice-text">
            <b><?php _e('Please activate your PageSpeed Ninja license key (free)', 'psn-pagespeed-ninja'); ?></b>
            <?php _e("A <u>free</u> license key is required to access all features of PageSpeed Ninja plugin. It's free and helps support our development efforts.", 'psn-pagespeed-ninja'); ?>
        </div>
        <form action="https://pagespeed.ninja/download/getfreelicensekey.html" target="_blank" method="GET">
            <input type="email" name="email" placeholder="<?php esc_attr_e('Email address', 'psn-pagespeed-ninja'); ?>" value="<?php echo esc_attr($current_user->user_email); ?>">
            <input type="submit" value="<?php esc_attr_e('Get License Key', 'psn-pagespeed-ninja'); ?>">
        </form>
    </div>
</div>
<?php
