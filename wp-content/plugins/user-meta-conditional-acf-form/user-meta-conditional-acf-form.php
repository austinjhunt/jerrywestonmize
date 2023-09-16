<?php
/*
 * Plugin Name: Conditional ACF Field Group Display
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin, triggered with shortcode [user_meta_conditional_acf_display required_user_meta_properties="a,b,c..." missing_properties_action="show|hide" trigger="all|any" acf_field_group_ids="..."] checks to see if all/any of specified (comma-separated) meta properties are missing for an authenticated user. If any/all (default=all) are missing/empty, either show or hide (default=hide) an ACF form containing specified field groups. Useful for asking for info from a logged in user if they haven't already provided it.
 * Version: 1.0.0
 * Requires PHP: 8
 * Author: Austin Hunt
 * Author URI: https://austinjhunt.com
*/

defined('ABSPATH') or die('You should not be here');
if (!class_exists('UserMetaConditionalACFFormDisplay')) {
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
            add_shortcode("user_meta_conditional_acf_display", array($this, "user_meta_conditional_acf_display_callback"));

            plugin_basename(__FILE__);

            // if we want admin page for plugin, add settings link to that page on plugin item in plugins list
            // do not need for this plugin
            // add_filter("plugin_action_links_$this->plugin_name", array($this, 'settings_link'));
        }

        // public function settings_link($links)
        // {
        //     // do not need this function for this plugin,
        //     $settings_link = '<a href="https://jerrywestonmize.com/wp-admin/admin.php?page=jwm_user_meta_conditional_acf_display_plugin">Settings</a>';
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
        function generateRandomString($length = 10)
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[random_int(0, $charactersLength - 1)];
            }
            return $randomString;
        }


        // [user_meta_conditional_acf_display missing_properties_action="show|hide" user_meta_properties="propA,propB,..."]
        public function user_meta_conditional_acf_display_callback($atts, $content = '')
        {

            // this function only affects logged in users.
            if (!is_user_logged_in()) {
                // return content by default if user is not logged in. no way to check user meta properties
                // for non-authenticated user.
                return $content;
            }
            $attributes = shortcode_atts(
                array(
                    'acf_field_group_ids' => '',
                    'missing_properties_action' => 'show',
                    'required_user_meta_properties' => '',
                    'trigger' => 'all' // alternative is any

                ),
                $atts
            );
            if (empty($attributes['required_user_meta_properties']) or empty($attributes['acf_field_group_ids'])) {
                $output = '<p>Please provide (at minimum) acf_field_group_ids="fg1,fg2,..." and required_user_meta_properties="propA,propB,propC,..." argument to the [user_meta_conditional_acf_display...] shortcode.</p>';
                return $output;
            }

            // First, sanitize the data and remove white spaces
            $user_meta_properties = preg_replace('/\s*,\s*/', ',', filter_var($attributes['required_user_meta_properties']));
            $user_meta_properties_array = explode(',', $user_meta_properties);
            $simple_empty_count = $this->count_missing_user_meta_properties($user_meta_properties_array);

            $acf_field_group_ids = preg_replace('/\s*,\s*/', ',', filter_var($attributes['acf_field_group_ids']));
            $acf_field_group_ids_array = explode(',', $acf_field_group_ids);
            $acf_form_content = acf_form(array(
                'id' => 'acf-form-' . $this->generateRandomString(),
                'field_groups' => $acf_field_group_ids_array
            ));

            // initialize default output
            $output = '';
            if ($attributes['missing_properties_action'] == 'show') {
                if ($attributes['trigger'] == 'all') {
                    // show content if all properties missing
                    if ($simple_empty_count == 'all') {
                        $output = $acf_form_content;
                    } // default already empty
                } else if ($attributes['trigger'] == 'any') {
                    // show content if any properties missing
                    if ($simple_empty_count == 'some' or $simple_empty_count == 'all') {

                        $output = $acf_form_content;
                    }
                }
            } else if ($attributes['missing_properties_action'] == 'hide') {
                $output .=  "<p>Action: hide</p>";
                if ($attributes['trigger'] == 'all') {
                    // hide content if all properties missing
                    if ($simple_empty_count == 'all') {
                        $output = '';
                    } else {
                        // show if not all properties missing
                        $output = $acf_form_content;
                    }
                } else if ($attributes['trigger'] == 'any') {
                    // hide content if any properties missing
                    if ($simple_empty_count == 'some' or $simple_empty_count == 'all') {
                        $output = '';
                    } else {
                        // show if no properties missing
                        $output = $acf_form_content;
                    }
                }
            }

            return $output;
        }
        public function add_admin_pages()
        {
            add_menu_page(
                'User Meta Conditional ACF Form Display Plugin',
                'User Meta Conditional ACF Form Display',
                'manage_options',
                'jwm_user_meta_conditional_acf_display_plugin',
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
