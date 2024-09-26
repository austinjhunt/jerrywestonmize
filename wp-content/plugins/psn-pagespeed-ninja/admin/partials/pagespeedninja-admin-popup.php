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

$presets_list = array_replace($extra_presets_list, $presets_list, $extra_presets_list);

$options = $this->loadJsonPhp($plugin_dir . '/includes/options.json.php');

$default_preset = 'optimal';
$popup_settings = array('email', 'apikey', 'allow_ext_atfcss', 'allow_ext_stats', 'footer', 'autoupdate');

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

// start tour after installation wizard
set_transient('pagespeedninja_show_tour', 0);

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
    <div id='TB_closeAjaxWindow'><button type='button' id='TB_closeWindowButton'><span class='tb-close-icon'></span></button></div>
    <div class="pagespeedninja pagespeedninja-afterinstall-popup">
        <div id="pagespeedninja-content">
            <form action="<?php echo esc_attr(admin_url('options.php')); ?>" method="post" id="pagespeedninja-popup-form" class="content show">
                <?php settings_fields('pagespeedninja_config'); ?>
                <?php
                $config['afterinstall_popup'] = '1';
                $this->hidden($config, 'afterinstall_popup');
                $config['afterinstall_popup'] = '0';

                $hello_email = 'hello@pagespeed.ninja';
                $email_link = '<a href="mailto:' . $hello_email . '">' . $hello_email . '</a>';
                ?>
                <div id="pagespeedninja-page-1" class="pagespeedninja-page">
                    <h1><?php esc_html_e("Welcome!"); ?></h1>
                    <p><?php esc_html_e("We’re delighted to have you on board!"); ?></p>
                    <p><?php esc_html_e("In the next 3 steps, we’ll guide you through getting started with PageSpeed Ninja."); ?></p>
                    <p><?php esc_html_e("Don’t worry, you can easily adjust and revert your settings later on."); ?></p>
                    <p><?php printf(esc_html__("If you encounter any issues, reach out to us at %s and we’ll do our best to help you resolve the issue."), $email_link); ?></p>
                    <p><?php esc_html_e("Enjoy your journey!"); ?></p>
                    <p>Denis &amp; Ugur</p>
                </div>
                <div id="pagespeedninja-page-2" class="pagespeedninja-page hidden">
                    <h1><?php esc_html_e("Select a preset"); ?></h1>
                    <p><?php esc_html_e("Please select an optimization preset below. You can change it under Advanced settings later on."); ?></p>
                    <?php
                    foreach ($presets_list as $preset) {
                        ?><label><input type="radio" name="pagespeedninja_preset" value="<?php echo $preset->name; ?>"<?php echo $preset->name === $default_preset ? ' checked' : ''; ?>>
                        <div>
                            <span class="presettitle"><?php echo $preset->title; ?></span>
                            <span class="presettooltip"><?php echo isset($preset->wizard_description) ? $preset->wizard_description : $preset->tooltip; ?></span>
                        </div>
                        </label><?php
                    }
                    ?>
                </div>
                <div id="pagespeedninja-page-3" class="pagespeedninja-page hidden">
                    <h1><?php esc_html_e("License Key"); ?></h1>
                    <p><?php esc_html_e("Please enter your email address and license key to access all features."); ?></p>
                    <div class="pagespeedninja-settings">
                    <?php
                    foreach (array('email', 'apikey') as $name) {
                        $item = $settings[$name];
                        ?>
                        <div class="line">
                        <?php
                            $this->title($item->title);
                            $this->render($item->type, $name, $config, $item);
                        ?>
                        </div>
                    <?php } ?>
                    </div>
                </div>
                <div id="pagespeedninja-page-4" class="pagespeedninja-page hidden">
                    <h1><?php esc_html_e("Finalize"); ?></h1>
                    <p><?php esc_html_e("One last step, you are almost done."); ?></p>
                    <div class="pagespeedninja-settings">
                    <?php
                    $config['autoupdate'] = '1';
                    foreach (array('allow_ext_atfcss', 'allow_ext_stats', 'footer', 'autoupdate') as $name) {
                        $item = $settings[$name];
                        ?>
                        <div class="line">
                            <div>
                                <div class="title"><?php echo $item->title; ?></div>
                                <div class="tooltip"><?php echo isset($item->wizard_description) ? $item->wizard_description : (isset($item->tooltip) ? $item->tooltip : ''); ?></div>
                            </div>
                            <?php $this->render($item->type, $name, $config, $item); ?>
                        </div>
                    <?php } ?>
                    </div>
                </div>
            </form>
        </div>
        <div id="pagespeedninja-popup-footer">
            <div id="pagespeedninja-prev" class="button-left hidden"><?php esc_html_e("Previous"); ?></div>
            <div id="pagespeedninja-next" class="button-right hidden"><?php esc_html_e("Continue"); ?></div>
            <div id="pagespeedninja-save" class="button-right hidden"><?php esc_html_e("Complete"); ?></div>
        </div>
    </div>
</div>

<script>
    jQuery(function () {
        setTimeout(function () {
            window.tb_remove = function () {
                location.href = 'index.php';
                return false;
            };
            var w = Math.min(800, window.innerWidth),
                h = Math.min(700, window.innerHeight);
            tb_show('', '#TB_inline?width='+(w-30)+'&height='+(h-25)+'&inlineId=pagespeedninja_afterinstall_popup&modal=true');
        }, 0);

        var current_page = 1,
            num_pages = 4,
            $prev = jQuery('#pagespeedninja-prev'),
            $next = jQuery('#pagespeedninja-next'),
            $save = jQuery('#pagespeedninja-save');

        function showPage() {
            jQuery('.pagespeedninja-page').addClass('hidden');
            jQuery('#pagespeedninja-page-' + current_page).removeClass('hidden');
            $prev.toggleClass('hidden', current_page === 1);
            $next.toggleClass('hidden', current_page === num_pages);
            $save.toggleClass('hidden', current_page !== num_pages);
        }

        showPage();

        $prev.on('click', function () {
            if (current_page > 1) {
                current_page--;
                showPage();
            }
        });

        $next.on('click', function () {
            if (current_page < num_pages) {
                current_page++;
                showPage();
            }
        });

        $save.on('click', function () {
            jQuery('#pagespeedninja-popup-form').submit();
        });

        jQuery(window).on('resize', function() {
            TB_WIDTH = Math.min(800, window.innerWidth);
            TB_HEIGHT = Math.min(700, window.innerHeight);
            jQuery('#TB_ajaxContent').css({
                width: (TB_WIDTH - 30) + 'px',
                height: (TB_HEIGHT - 45) + 'px',
            });
            tb_position();
        });

    });
</script>
