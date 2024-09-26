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

$options = $this->loadJsonPhp($plugin_dir . '/includes/options.json.php');
$options = apply_filters('psn_options_loaded', $options);

$presets = array();
foreach ($extra_presets_list as $preset) {
    $presets[$preset->name] = array();
}
foreach ($presets_list as $preset) {
    $presets[$preset->name] = array();
}

foreach ($options as $section) {
    if (isset($section->items)) {
        /** @var array {$section->items} */
        foreach ($section->items as $item) {
            if (isset($item->presets) && !isset($item->pro)) {
                $item_presets = get_object_vars($item->presets);
                foreach ($item_presets as $name => $value) {
                    if (!isset($presets[$name])) {
                        trigger_error("PageSpeed Ninja: unknown preset name $name in {$item->name} section.");
                    }
                }
                foreach ($presets_list as $preset) {
                    $name = $preset->name;
                    $value = isset($item_presets[$name]) ? $item_presets[$name] : $item->default;
                    $presets[$name][$item->name] = "'" . $item->name . "':" . (is_string($value) ? "'$value'" : $value);
                }
            }
        }
    }
}

foreach ($extra_presets_list as $preset) {
    $name = $preset->name;
    $presets[$name] = $presets[$preset->base];
    foreach ($preset->options as $option_name => $option_value) {
        $presets[$name][$option_name] = "'" . $option_name . "':" . (is_string($option_value) ? "'$option_value'" : $option_value);
    }
}

foreach ($presets as $preset => &$values) {
    $values = "'$preset':{" . implode(',', $values) . '}';
}
unset($values);

?><script>
    var pagespeedninja_presets = {<?php echo implode(',', $presets); ?>};
</script>
<div class="pagespeedninja pagespeedninja-advanced">
<div id="psnwrap">
    <?php do_action('psn_advanced_before'); ?>

    <div id="pagespeedninja-content">
        <div class="buttonbar">
            <a href="#" class="button save disabled" title="<?php esc_attr_e('Save changes'); ?>"><?php _e('Save', 'psn-pagespeed-ninja'); ?></a>
        </div>
        <div class="headerbar">
            <div class="logo"></div>
        </div>
        <div class="tabs">
            <a href="#" class="general"><?php _e('General', 'psn-pagespeed-ninja'); ?></a>
            <a href="#" class="active advanced"><?php _e('Advanced', 'psn-pagespeed-ninja'); ?></a>
        </div>

        <div class="main column-wide tooltip-container">
            <?php $this->load('admin-probanner', $config); ?>

            <div class="presets">
                <h3><?php _e('Presets', 'psn-pagespeed-ninja'); ?></h3>
                <?php foreach ($extra_presets_list as $preset): ?>
                    <label data-tooltip="<?php echo esc_attr($preset->tooltip); ?>"><input type="radio" name="preset" id="pagespeedninja_preset_<?php echo $preset->name; ?>" onclick="pagespeedninjaLoadPreset('<?php echo $preset->name; ?>')"> <?php echo $preset->title; ?></label>
                <?php endforeach; ?>
                <?php foreach ($presets_list as $preset): ?>
                    <label data-tooltip="<?php echo esc_attr($preset->tooltip); ?>"><input type="radio" name="preset" id="pagespeedninja_preset_<?php echo $preset->name; ?>" onclick="pagespeedninjaLoadPreset('<?php echo $preset->name; ?>')"> <?php echo $preset->title; ?></label>
                <?php endforeach; ?>
                <label data-tooltip="<?php esc_attr_e('Your current preset.', 'psn-pagespeed-ninja'); ?>"><input type="radio" name="preset" id="pagespeedninja_preset_custom" onclick="pagespeedninjaLoadPreset('')"> <?php _e('Custom', 'psn-pagespeed-ninja'); ?></label>
            </div>

            <form id="pagespeedninja_form" action="<?php echo esc_attr(admin_url('options.php')); ?>" method="post">
                <?php settings_fields('pagespeedninja_config'); ?>
                <div class="pagespeedninja_filter_wrapper">
                    <input type="text" id="pagespeedninja_filter" placeholder="<?php esc_attr_e('Search settings...', 'psn-pagespeed-ninja'); ?>">
                </div>
                <?php
                $first = true;
                /** @var stdClass $section */
                /** @var array {$section->items} */
                foreach ($options as $section) :
                    if (isset($section->items) && count($section->items) === 0) {
                        continue;
                    }
                    ?><div<?php echo isset($section->id) ? ' id="psi_' . $section->id . '"' : ''; ?>>
                        <div class="header">
                            <div class="expando<?php echo $first ? ' open' : ''; ?>"></div>
                            <div class="title"><?php echo $section->title; ?></div>
                            <?php
                            if (isset($section->id)) {
                                $this->render('checkbox', 'psi_' . $section->id, $config);
                            }
                            ?>
                        </div>
                        <div class="content<?php
                        if ($first) {
                            echo ' show';
                        }
                        if (isset($section->id) && !$config['psi_' . $section->id]) {
                            echo ' disabled';
                        }
                        ?>">
                            <?php $first = false; ?>
                            <?php if (!isset($section->items) || count($section->items) === 0) : ?>
                                <div class="line todo"><?php _e('Will be implemented further.', 'psn-pagespeed-ninja'); ?></div>
                            <?php else : ?>
                                <?php foreach ($section->items as $item) :
                                    switch ($item->type) {
                                        case 'hidden':
                                            break;
                                        case 'subsection':
                                            ?><div class="line"><div class="subsection"><?php
                                                $this->title($item->title, isset($item->tooltip) ? $item->tooltip : '');
                                                ?></div></div><?php
                                            break;
                                        default:
                                            $class = 'line';
                                            $attrs = '';
                                            if (isset($item->pro)) {
                                                $class .= ' pro';
                                                $attrs .= ' data-html-tooltip-ref="psnprobanner"';
                                            }
                                            if (isset($item->global) && $item->global === 1 && is_multisite()) {
                                                $class .= ' global';
                                            }
                                            ?><div class="<?php echo $class; ?>"<?php echo $attrs; ?>><?php
                                            $this->title($item->title, isset($item->tooltip) ? $item->tooltip : '');
                                            $this->render($item->type, isset($item->name) ? $item->name : '', $config, $item);
                                            ?></div><?php
                                    }
                                endforeach;
                            endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div id="psnprobanner" data-html-tooltip data-html-tooltip-active data-html-tooltip-pos="baseline" style="max-width: 480px">
                    🚨 <?php printf(__('This feature is only available in %s', 'psn-pagespeed-ninja'),
                        '<a href="https://pagespeed.ninja/download/?utm_source=psnbackend-popupbanner&amp;utm_medium=Advanced-tab-upgrade&amp;utm_campaign=Admin-upgrade" target="_blank">PageSpeed Ninja Pro</a>'
                    ); ?>
                </div>
            </form>
        </div>
        <div class="footer"><?php printf(__('Need help? Ask a question in the PageSpeed Ninja <a %s>Support Forum</a>.', 'psn-pagespeed-ninja'), 'href="https://wordpress.org/support/plugin/psn-pagespeed-ninja/" target="_blank"'); ?></div>
    </div>
    <?php do_action('psn_advanced_after'); ?>
</div>

<div id="psn-ruleslist-popup" style="display:none">
<div class="pagespeedninja">
    <p>Exclude the marked URLs from processing.</p>
    <table id="rules-urls-list">
        <tr>
            <th><?php _e('Exclude', 'psn-pagespeed-ninja'); ?></th>
            <th><?php _e('URL', 'psn-pagespeed-ninja'); ?></th>
        </tr>
        <?php
        foreach ($this->getUrlsList() as $row) {
            echo '<tr class="psn-ruleslist-row-' . $row->type .'">'
                . '<td><input type="checkbox"/></td>'
                . '<td>' . esc_html($row->url) . '</td>'
                . '</tr>';
        }
        ?>
    </table>
    <a href="#" class="button psn-ruleslist-popup-apply" onclick="showExcludeListPopup_apply(); return false;"><?php _e('Apply', 'psn-pagespeed-ninja'); ?></a>
    <a href="#" class="button psn-ruleslist-popup-cancel" onclick="tb_remove(); return false;"><?php _e('Cancel', 'psn-pagespeed-ninja'); ?></a>
</div>
</div>
<div id="psn-rules-popup" style="display:none">
<div class="pagespeedninja">
    <p>Each line corresponds to one rule in 'attribute condition value' (to match attribute value)
        or 'condition value' (to match inner content) format.
        Condition: = exact match, *= partial match, ^= starts with, $= ends with, ~= regular expression.
        E.g. 'src*=debug' line affects all URLs containing 'debug' as a substring of 'src' attribute.</p>
    <textarea id="psn-rules-popup-textarea" rows="9" cols="80"></textarea>
    <a href="#" class="button psn-rules-popup-apply" onclick="showExcludeRulesPopup_apply(); return false;"><?php _e('Apply', 'psn-pagespeed-ninja'); ?></a>
    <a href="#" class="button psn-rules-popup-cancel" onclick="tb_remove(); return false;"><?php _e('Cancel', 'psn-pagespeed-ninja'); ?></a>
</div>
</div>

<?php do_action('psn_advanced_modals'); ?>
</div>
