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


        // [show_only_for_regulars newcomer_redirect_uri="REDIRECT NEWCOMERS TO THIS URL"]CONTENT[/show_only_for_regulars]
        public function show_only_for_regulars_callback($atts, $content = '')
        {
            ob_start();
            $attributes = shortcode_atts(
                array(
                    'newcomer_redirect_uri' => '',
                    'content_is_shortcode' => true
                ),
                $atts
            );

            // this function only affects logged in users.
            if (!is_user_logged_in()) {
                // return content by default if user is not logged in. no way to check user meta properties
                // for non-authenticated user.
                if ($attributes['content_is_shortcode'] == true) {
                    echo do_shortcode($content);
                } else if ($attributes['content_is_shortcode'] == false) {
                    echo $content;
                }
            }


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
                    echo "<script>window.location.href = '" . $redirect . "';</script>";
                }
            }

            return ob_get_clean();
        }
    }
}

$showOnlyForRegularsPlugin = new ShowOnlyForRegulars();
$showOnlyForRegularsPlugin->register();