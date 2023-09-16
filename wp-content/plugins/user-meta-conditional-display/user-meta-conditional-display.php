<?php
/*
 * Plugin Name: User Meta Conditional Display
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin, triggered with shortcode [user_meta_conditional_display required_user_meta_properties="a,b,c..." missing_properties_action="show|hide" trigger="all|any"] CONTENT [/user_meta_conditional_display] checks to see if all/any of specified (comma-separated) meta properties are missing for an authenticated user. If any/all (default=all) are missing/empty,  either show or hide (default=hide) content between opening/closing shortcode, e.g., show a form asking for those properties. The wrapped content will always show by default for users who are not logged in, so the condition only applies when users are logged in.
 * Version: 1.0.0
 * Requires PHP: 8
 * Author: Austin Hunt
 * Author URI: https://austinjhunt.com
*/

defined('ABSPATH') or die('You should not be here');
if (!class_exists('UserMetaConditionalDisplay')) {
    class UserMetaConditionalDisplay
    {
        public $plugin_name;
        function __construct()
        {
            $this->plugin_name = plugin_basename(__FILE__);
        }
        function register()
        {
            add_action('admin_enqueue_scripts', array($this, 'enqueue'));

            // do not need menu, using only shortcodes
            // add_action('admin_menu', array($this, 'add_admin_pages'));

            // allow shortcode with args
            add_shortcode("user_meta_conditional_display", array($this, "user_meta_conditional_display_callback"));

            plugin_basename(__FILE__);

            // if we want admin page for plugin, add settings link to that page on plugin item in plugins list
            // do not need for this plugin
            // add_filter("plugin_action_links_$this->plugin_name", array($this, 'settings_link'));
        }

        // public function settings_link($links)
        // {
        //     // do not need this function for this plugin,
        //     $settings_link = '<a href="https://jerrywestonmize.com/wp-admin/admin.php?page=jwm_user_meta_conditional_display_plugin">Settings</a>';
        //     array_push($links, $settings_link);
        //     return $links;
        // }

        function enqueue()
        {
            // enqueue our scripts
            wp_enqueue_style($this->plugin_name . "_style", plugins_url('/assets/style.css', __FILE__));
            wp_enqueue_script($this->plugin_name . "_script", plugins_url('/assets/main.js', __FILE__));
        }

        function count_missing_user_meta_properties($properties)
        {
            $empty_count = 0;
            $simple_empty_count = "none";
            foreach ($properties as $prop) {
                $val = get_user_meta(user_id: get_current_user_id(), key: $prop, single: true);
                echo "<p>Value of " . $prop . ": " . $val . "</p>";
                if (empty($val)) {
                    $empty_count = $empty_count + 1;
                }
            }
            if ($empty_count > 0) {
                $simple_empty_count = "some";
                if ($empty_count == count($properties)) {
                    $simple_empty_count = "all";
                }
            }
            return $simple_empty_count;
        }

        // [user_meta_conditional_display missing_properties_action="show|hide" user_meta_properties="propA,propB,..."]
        public function user_meta_conditional_display_callback($atts, $content = '')
        {
            // this function is invoked by the shortcode, and renders the wrapped content ($content) based on
            // non-existence of any or all specified meta properties of user
            if (!is_user_logged_in()) {
                // return content by default if user is not logged in. no way to check user meta properties
                // for non-authenticated user.
                return $content;
            }
            $attributes = shortcode_atts(
                array(
                    'missing_properties_action' => 'show',
                    'required_user_meta_properties' => '',
                    'trigger' => 'all' // alternative is any
                ),
                $atts
            );
            if (empty($attributes['required_user_meta_properties'])) {
                $output = '<p>Please provide (at minimum) required_user_meta_propertiess="propA,propB,propC,..." argument to the [user_meta_conditional_display...] shortcode.</p>';
                return $output;
            }
            // First, sanitize the data and remove white spaces
            $user_meta_properties = preg_replace('/\s*,\s*/', ',', filter_var($attributes['user_meta_properties']));
            echo "<p>Properties: " . $user_meta_properties . "</p>";
            $user_meta_properties_array = explode(',', $user_meta_properties);
            var_dump($user_meta_properties_array);
            $simple_empty_count = $this->count_missing_user_meta_properties($user_meta_properties_array);

            // initialize default output
            $output = '';
            $stat = 0;
            if ($attributes['missing_properties_action'] == 'show') {
                if ($attributes['trigger'] == 'all') {
                    // show content if all properties missing
                    if ($simple_empty_count == 'all') {
                        $stat = 1;
                        $output = $content;
                    } // default already empty
                } else if ($attributes['trigger'] == 'any') {
                    // show content if any properties missing
                    if ($simple_empty_count == 'any' or $simple_empty_count == 'all') {
                        $stat = 2;
                        $output = $content;
                    }
                }
            } else if ($attributes['missing_properties_action'] == 'hide') {
                $output .=  "<p>Action: hide</p>";
                if ($attributes['trigger'] == 'all') {
                    // hide content if all properties missing
                    if ($simple_empty_count == 'all') {
                        $output = '';
                        $stat = 3;
                    } else {
                        // show if not all properties missing
                        $stat = 4;
                        $output = $content;
                    }
                } else if ($attributes['trigger'] == 'any') {
                    // hide content if any properties missing
                    if ($simple_empty_count == 'any' or $simple_empty_count == 'all') {
                        $output = '';
                        $stat = 5;
                    } else {
                        // show if no properties missing
                        $output = $content;
                        $stat = 6;
                    }
                }
            }

            $output .=  "<p>Trigger: " . $attributes['trigger'] . "</p>";
            $output .=  "<p>Action: " . $attributes['missing_properties_action'] . "</p>";
            $output .=  "<p>Empty Count: " . $simple_empty_count . "</p>";
            $output .=  "<p>Stat: " . $stat . "</p>";

            return $output;
        }
        public function add_admin_pages()
        {
            add_menu_page(
                'User Meta Conditional Display Plugin',
                'User Meta Conditional Display',
                'manage_options',
                'jwm_user_meta_conditional_display_plugin',
                array($this, 'admin_index'),
                'dashicons-visibility',
                110
            );
        }
        // do not need admin page for this plugin
        // public function admin_index()
        // {
        //     // require template
        //     require_once plugin_dir_path(__FILE__) . 'templates/admin.php';
        // }
    }
}

$conditionalFormDisplayPlugin = new UserMetaConditionalDisplay();
$conditionalFormDisplayPlugin->register();
