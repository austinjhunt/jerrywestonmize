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
            $query_params = array();
            // add each user attribute as a query param to the redirect uri
            foreach ($user_attr_query_params as $attr) {
                // if attr is user_email 
                if ($attr == "user_email") {
                    $value = get_userdata(get_current_user_id())->user_email;
                } else {
                    $value = get_user_meta(user_id: get_current_user_id(), key: $attr, single: true);
                }
                if ($value) {
                    $query_params[$attr] = $value;
                }
            }
            $url_with_params = add_query_arg($query_params, $redirect_uri);
            return $url_with_params;
        }

        // [show_only_for_regulars newcomer_redirect_uri="REDIRECT NEWCOMERS TO THIS URL"]CONTENT[/show_only_for_regulars]
        public function show_only_for_regulars_callback($atts, $content = '')
        {

            ob_start();
            error_log('show_only_for_regulars_callback');
            $attributes = shortcode_atts(
                array(
                    'newcomer_redirect_uri' => '',
                    'content_is_shortcode' => true,
                    'user_attr_query_params' => ''
                ),
                $atts
            );

            $login_url = "https://jerrywestonmize.com/index.php/login/";
            $current_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $current_url = urlencode($current_url);
            $login_url = $login_url . '?redirect_to=' . $current_url . '';
            // redirect unauthenticated users to home page
            if (!is_user_logged_in()) {
                error_log('user is not logged in');
                // echo "<script>window.location = '" . $login_url . "';</script>";
                echo "<script>setTimeout(function(){document.location.href = '" . $login_url . "';},250);</script>";
            } else {
                // logged in users only

                // split include_user_meta_attrs into array  
                $user_attr_query_params = array_map('trim', explode(',', $attributes['user_attr_query_params']));

                $customer_status = get_user_meta(user_id: get_current_user_id(), key: 'customer_status', single: true);
                $customer_status = explode(':', $customer_status)[0];

                error_log('User ID: ' . get_current_user_id());
                error_log('Customer status of user: ' . $customer_status);
                $customer_status = trim($customer_status);
                if ($customer_status == "regular") {
                    error_log('A');
                    if ($attributes['content_is_shortcode'] == true) {
                        error_log('A1');
                        echo do_shortcode($content);
                    } else if ($attributes['content_is_shortcode'] == false) {
                        error_log('A2');
                        echo $content;
                    }
                } else if ($customer_status == "newcomer") {
                    error_log('B');
                    if (!empty($attributes['newcomer_redirect_uri'])) {
                        error_log('B1');
                        $redirect = $attributes['newcomer_redirect_uri'];
                        $redirect = $this->get_redirect_uri_with_user_meta_query_params($redirect, $user_attr_query_params);
                        echo "<script>setTimeout(function(){document.location.href = '" . $redirect . "';},250);</script>";
                    }
                } else if ($customer_status == "") {
                    error_log('C');
                    // set customer status to newcomer
                    update_user_meta(user_id: get_current_user_id(), meta_key: 'customer_status', meta_value: 'newcomer: Newcomer');
                    if (!empty($attributes['newcomer_redirect_uri'])) {
                        error_log('C1');
                        $redirect = $attributes['newcomer_redirect_uri'];
                        $redirect = $this->get_redirect_uri_with_user_meta_query_params($redirect, $user_attr_query_params);
                        echo "<script>setTimeout(function(){document.location.href = '" . $redirect . "';},250);</script>";
                    } else {
                        error_log('C2');
                    }
                }
            }

            return ob_get_clean();
        }
    }
}

$showOnlyForRegularsPlugin = new ShowOnlyForRegulars();
$showOnlyForRegularsPlugin->register();
