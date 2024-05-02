<?php
/**
 * PageSpeed Ninja
 * https://pagespeed.ninja/
 *
 * @version    1.3.13
 * @license    GNU/GPL v2 - http://www.gnu.org/licenses/gpl-2.0.html
 * @copyright  (C) 2016-2024 PageSpeed Ninja Team
 * @date       March 2024
 */
defined('ABSPATH') || die();

/** @var array $config */
/** @var PagespeedNinja_View $this */

$plugin_dir = dirname(dirname(__DIR__));

$presets_list = $this->loadJsonPhp($plugin_dir . '/includes/presets.json.php');

$extra_presets_list = array();
$extra_presets_dir = $plugin_dir . '/admin/extras/presets';
$extra_presets_files = glob($extra_presets_dir . '/*.json');
foreach ($extra_presets_files as $preset_file) {
    $preset_name = basename($preset_file, '.json');
    $preset_data = @file_get_contents($preset_file);
    $preset_data = @json_decode($preset_data);
    if (!isset($preset_data->base, $preset_data->title, $preset_data->tooltip, $preset_data->options)) {
        continue;
    }
    $extra_presets_list[$preset_name] = $preset_data;
    $extra_presets_list[$preset_name]->name = $preset_name;
}

$options = $this->loadJsonPhp($plugin_dir . '/includes/options.json.php');

$default_preset = 'optimal';
$popup_settings = array('email', 'apikey', 'allow_ext_atfcss', 'allow_ext_stats', 'footer');

$settings = array();
foreach ($options as $section) {
    if (isset($section->items)) {
        /** @var array {$section->items} */
        foreach ($section->items as $item) {
            if (isset($item->name) && in_array($item->name, $popup_settings, true)) {
                $settings[$item->name] = $item;
            }
        }
    }
}

set_transient('pagespeedninja_show_tour', 60);

?>
<div class="pagespeedninja">
<div id="psnwrap">
    <div id="pagespeedninja-content">
        <div class="headerbar">
            <div class="logo"></div>
        </div>
    </div>
</div>
</div>

<div id="pagespeedninja_afterinstall_popup" style="display:none">
<div class="pagespeedninja pagespeedninja-afterinstall-popup">
<div id="pagespeedninja-content">
<div class="column-wide tooltip-container">
    <form action="<?php echo esc_html(admin_url('options.php')); ?>" method="post" id="pagespeedninja-popup-form" class="content show">
        <?php settings_fields('pagespeedninja_config'); ?>
        <?php
            $config['afterinstall_popup'] = '1';
            $this->hidden($config, 'afterinstall_popup');
        ?>
        <div class="presets_popup hidden">
            <?php
            foreach ($extra_presets_list as $preset) {
                ?><label><input type="radio" name="pagespeedninja_preset" value="<?php echo $preset->name; ?>"> <span class="presettitle"><?php echo $preset->title; ?></span><span class="presettooltip"><?php echo $preset->tooltip; ?></span></label><?php
            }
            foreach ($presets_list as $preset) {
                ?><label><input type="radio" name="pagespeedninja_preset" value="<?php echo $preset->name; ?>"<?php echo $preset->name === $default_preset ? ' checked' : ''; ?>> <span class="presettitle"><?php echo $preset->title; ?></span><span class="presettooltip"><?php echo $preset->tooltip; ?></span></label><?php
            }
            ?>
        </div>
        <div class="preset line">
            <div class="title"><?php _e('Optimization Profile Preset', 'psn-pagespeed-ninja'); ?></div>
            <div class="field"><div class="dropdown"><span id="pagespeedninja_profilename"></span><span class="expando"></span></div></div>
        </div>
        <?php
        $tabindex = 1;
        foreach ($popup_settings as $name) {
            $item = $settings[$name];
            ?><div class="line"><?php
            $this->title($item->title, isset($item->tooltip) ? $item->tooltip : '');
            $this->render($item->type, isset($item->name) ? $item->name : '', $config, $item);
            ?></div><?php
        }
        ?>
        <p><?php _e('These settings may be changed further in the Advanced settings of PageSpeed Ninja plugin.', 'psn-pagespeed-ninja'); ?></p>
        <input type="submit" value="<?php esc_attr_e('Next'); ?>" />
    </form>
</div>
</div>
</div>
</div>

<style>
    #TB_title, #TB_closeAjaxWindow {
        display: none;
    }
</style>

<script>
    jQuery(function () {
        setTimeout(function () {
            window.tb_remove = function () {
                return false;
            };
            tb_show('', '#TB_inline?width=727&height=595&inlineId=pagespeedninja_afterinstall_popup');
        }, 0);
        jQuery('#pagespeedninja_profilename').html(
            jQuery('#pagespeedninja-popup-form > .presets_popup input[type=radio][value=<?php echo $default_preset; ?>] + .presettitle').html()
        );
        jQuery('#pagespeedninja-popup-form > .presets_popup input:radio:checked').parent().addClass('checked');
        jQuery('#pagespeedninja-popup-form > .presets_popup input:radio').click(function() {
            jQuery('#pagespeedninja-popup-form .presets_popup label').removeClass('checked');
            jQuery(this).parent().addClass('checked');
            jQuery('#pagespeedninja_profilename').html(
                jQuery(this).next('.presettitle').html()
            );
            jQuery('#pagespeedninja-popup-form > .presets_popup').addClass('hidden');
        });
        jQuery('#pagespeedninja-popup-form > .preset > .field > .dropdown').click(function() {
            jQuery('#pagespeedninja-popup-form > .presets_popup').toggleClass('hidden');
            jQuery(this).parent().addClass('checked');
            return false;
        });
        jQuery('body').click('#TB_window', function() {
            jQuery('#pagespeedninja-popup-form > .presets_popup').addClass('hidden');
        });
    });
</script>
