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

$plugin_dir = dirname(dirname(__DIR__));
$options = $this->loadJsonPhp($plugin_dir . '/includes/options.json.php');
$options = apply_filters('psn_options_loaded', $options);

global $current_user;

if (get_transient('pagespeedninja_show_tour')) {
    delete_transient('pagespeedninja_show_tour');
    $this->load('admin-tour', $config);
}
?>
<div class="pagespeedninja pagespeedninja-general">
<div id="psnwrap">
    <form action="<?php echo esc_attr(admin_url('options.php')); ?>" method="post" id="pagespeedninja_form">
        <?php settings_fields('pagespeedninja_config'); ?>
        <?php $this->hidden($config, 'apikey'); ?>
        <?php $this->hidden($config, 'css_abovethefoldlocal'); ?>
        <?php $this->hidden($config, 'css_abovethefoldstyle'); ?>
        <?php /* @TODO ATF-CSS should be updated automatically after homepage content is changed */ ?>
        <?php
            foreach ($options as $section) {
                if (isset($section->id, $section->items) && count($section->items)) {
                    $this->hidden($config, 'psi_' . $section->id);
                }
            }
        ?>
    </form>

    <div id="pagespeedninja-content">
        <div class="buttonbar">
            <a href="#" class="button save disabled" title="<?php esc_attr_e('Save changes'); ?>"><?php _e('Save', 'psn-pagespeed-ninja'); ?></a>
        </div>
        <div class="headerbar">
            <div class="logo"></div>
        </div>
        <div class="tabs">
            <a href="#" class="active general"><?php _e('General', 'psn-pagespeed-ninja'); ?></a><?php /* @todo Why not direct links??? */ ?>
            <a href="#" class="advanced"><?php _e('Advanced', 'psn-pagespeed-ninja'); ?></a>
        </div>
        <!--div class="preview">
            <div class="iframe">
                <iframe src="about:blank" sandbox="allow-forms allow-pointer-lock allow-popups allow-same-origin allow-scripts"></iframe>
            </div>
            <a class="dragger closed">preview</a>
            <div class="overlay_fix"></div>
        </div!-->
        <div class="main tooltip-container">
            <?php $this->load('admin-probanner', $config); ?>
            <?php $this->load('admin-reviewbanner', $config); ?>
            <?php foreach (array('desktop' => __('Desktop', 'psn-pagespeed-ninja'), 'mobile' => __('Mobile', 'psn-pagespeed-ninja')) as $mode => $title) : ?>
            <div class="column" id="<?php echo $mode; ?>">
                <h2>
                    <?php echo $title; ?>
                    <div class="gps">
                        <div class="gps_result_orig"><span class="gps_loading" id="pagespeed_<?php echo $mode; ?>_orig" title="<?php esc_attr_e('Original score'); ?>">&nbsp;</span></div>
                        <div class="gps_result"><span class="gps_loading" id="pagespeed_<?php echo $mode; ?>" title="<?php esc_attr_e('Current score'); ?>">&nbsp;</span></div>
                        <div class="gps_result_new hide"><a href="#" class="thickbox" title="<?php esc_attr_e('Website Preview'); ?>"><span id="pagespeed_<?php echo $mode; ?>_new" title="<?php esc_attr_e('Estimated new score (click to test website in popup)'); ?>">&nbsp;</span></a></div>
                    </div>
                </h2>
                <div class="settings">
                    <div id="<?php echo $mode; ?>-should-fix" class="hide">
                        <h3><?php _e('Should Fix', 'psn-pagespeed-ninja'); ?></h3>
                    </div>
                    <div id="<?php echo $mode; ?>-consider-fixing" class="hide">
                        <h3><?php _e('Consider Fixing', 'psn-pagespeed-ninja'); ?></h3>
                    </div>
                    <div id="<?php echo $mode; ?>-passed" class="hide">
                        <h3><?php _e('Passed', 'psn-pagespeed-ninja'); ?></h3>
                    </div>
                    <div id="<?php echo $mode; ?>-waiting">
                        <?php
                        foreach ($options as $section) :
                            if (isset($section->id, $section->type, $section->items) && $section->type === 'speed' && count($section->items) > 0) :
                                $id = $mode . '_' . $section->id;
                                ?>
                        <div id="<?php echo $id; ?>">
                            <div class="header">
                                <div class="title"><?php echo $section->title; ?></div>
                                <div class="field" data-html-tooltip-ref="psncolordesctooltip" data-html-tooltip-disable><?php $this->checkbox('pagespeedninja_config_' . $id, $id); ?></div>
                            </div>
                        </div>
                                <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div id="psncolordesctooltip" data-html-tooltip data-html-tooltip-pos="top">
                <b><span style="color:#7ed321"><?php _e('Green:', 'psn-pagespeed-ninja'); ?></span></b> <?php _e('improves the score.', 'psn-pagespeed-ninja'); ?><br>
                <b><span style="color:#fda100"><?php _e('Orange:', 'psn-pagespeed-ninja'); ?></span></b> <?php _e('a minor or no effect on the score.', 'psn-pagespeed-ninja'); ?><br>
                <b><span style="color:#d0021b"><?php _e('Red:', 'psn-pagespeed-ninja'); ?></span></b> <?php _e('negatively affects the score.', 'psn-pagespeed-ninja'); ?><br>
                <?php /* _e('Note that some settings have interrelated effects, so other switches may also change color.', 'psn-pagespeed-ninja'); */ ?>
            </div>
        </div>
        <div class="footer"><?php printf(__('Need help? Ask a question in the PageSpeed Ninja <a %s>Support Forum</a>.', 'psn-pagespeed-ninja'), 'href="https://wordpress.org/support/plugin/psn-pagespeed-ninja/" target="_blank"'); ?></div>
    </div>
</div>
</div>