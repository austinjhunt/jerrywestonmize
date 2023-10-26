<?php
/*
 * Plugin Name: Show Only For Regulars
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin, triggered with shortcode [show_only_for_regulars content_is_shortcode=true/false newcomer_redirect_uri="REDIRECT NEWCOMERS TO THIS URL"]CONTENT[/show_only_for_regulars]  will show CONTENT that it wraps only if signed in user's ACF field "customer_status" is "regular". Set content_is_shortcode=true if the content you are wrapping is a shortcode, e.g. [ameliastepbooking]. Set content_is_shortcode=false if the content you are wrapping is not a shortcode, e.g. <h1>Some header</h1>. Set newcomer_redirect_uri to redirect newcomers to a specific URL. If newcomer_redirect_uri is set, content_is_shortcode is ignored. If newcomer_redirect_uri is not set and the person is a newcomer, they'll see nothing. If visitor is not logged in at all, this plugin has no effect and simply returns content. 
 * Version: 1.0.0
 * Requires PHP: 8
 * Author: Austin Hunt
 * Author URI: https://austinjhunt.com
 */

defined('ABSPATH') or die('You should not be here');
if (!class_exists('ShowOnlyForRegulars')) {
    class ShowOnlyForRegulars
    {
        public $plugin_name;
        function __construct()
        {
            $this->plugin_name = plugin_basename(__FILE__);
        }

        function register()
        {
            add_action('admin_enqueue_scripts', array($this, 'enqueue'));

            // allow shortcode with args
            add_shortcode("show_only_for_regulars", array($this, "show_only_for_regulars_callback"));

            plugin_basename(__FILE__);
        }

        function enqueue()
        {
            // enqueue our scripts
            wp_enqueue_style($this->plugin_name . "_style", plugins_url('/assets/style.css', __FILE__));
            wp_enqueue_script($this->plugin_name . "_script", plugins_url('/assets/main.js', __FILE__));
        }

        public function get_redirect_uri_with_user_meta_query_params($redirect_uri, $user_attr_query_params)
        {
            // add each user attribute as a query param to the redirect uri
            foreach ($user_attr_query_params as $attr) {
                $value = get_user_meta(user_id: get_current_user_id(), key: $attr, single: true);
                $redirect_uri = add_query_arg($attr, $value, $redirect_uri);
            }
            return $redirect_uri;
        }

        // [show_only_for_regulars newcomer_redirect_uri="REDIRECT NEWCOMERS TO THIS URL"]CONTENT[/show_only_for_regulars]
        public function show_only_for_regulars_callback($atts, $content = '')
        {
            ob_start();
            $attributes = shortcode_atts(
                array(
                    'newcomer_redirect_uri' => '',
                    'content_is_shortcode' => true,
                    'user_attr_query_params' => ''
                ),
                $atts
            );


            // redirect unauthenticated users to home page
            if (!is_user_logged_in()) {
                echo "<script>window.location = '" . get_home_url() . "';</script>";
            }

            // split include_user_meta_attrs into array  
            $user_attr_query_params = array_map('trim', explode(',', $attributes['user_attr_query_params']));

            $customer_status = get_user_meta(user_id: get_current_user_id(), key: 'customer_status', single: true);
            $customer_status = explode(':', $customer_status)[0];
            $customer_status = trim($customer_status);
            if ($customer_status == "regular") {
                if ($attributes['content_is_shortcode'] == true) {
                    echo do_shortcode($content);
                } else if ($attributes['content_is_shortcode'] == false) {
                    echo $content;
                }
            } else if ($customer_status == "newcomer") {
                if (!empty($attributes['newcomer_redirect_uri'])) {
                    $redirect = $attributes['newcomer_redirect_uri'];
                    $redirect = $this->get_redirect_uri_with_user_meta_query_params($redirect, $user_attr_query_params);
                    echo "<script>window.location = '" . $redirect . "';</script>";
                }
            } else if ($customer_status == "") {
                // set customer status to newcomer
                update_user_meta(user_id: get_current_user_id(), meta_key: 'customer_status', meta_value: 'newcomer: Newcomer');
                if (!empty($attributes['newcomer_redirect_uri'])) {
                    $redirect = $attributes['newcomer_redirect_uri'];
                    $redirect = $this->get_redirect_uri_with_user_meta_query_params($redirect, $user_attr_query_params);
                    echo "<script>window.location = '" . $redirect . "';</script>";
                }
            }

            return ob_get_clean();
        }
    }
}

$showOnlyForRegularsPlugin = new ShowOnlyForRegulars();
$showOnlyForRegularsPlugin->register();