<?php
/*
 * Plugin Name: Makeup Credits Required
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin offers a shortcode [makeup_credits_required lesson_type="[virtual,inperson]"]CONTENT[/makeup_credits_required] that only shows content if the signed in user has at least one makeup credit of the specified lesson type. If no credits, redirect to home page.
 * Version: 1.0.0
 * Requires PHP: 8
 * Author: Austin Hunt
 * Author URI: https://austinjhunt.com
 */

defined('ABSPATH') or die('You should not be here');
if (!class_exists('MakeupCreditsRequired')) {
    class MakeupCreditsRequired
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
            add_shortcode("makeup_credits_required", array($this, "makeup_credits_required_callback"));

            plugin_basename(__FILE__);
        }

        function enqueue()
        {
            // enqueue our scripts
            wp_enqueue_style($this->plugin_name . "_style", plugins_url('/assets/style.css', __FILE__));
            wp_enqueue_script($this->plugin_name . "_script", plugins_url('/assets/main.js', __FILE__));
        }


        // [makeup_credits_required   lesson_type="virtual/inperson"]CONTENT[/makeup_credits_required]
        public function makeup_credits_required_callback($atts, $content = '')
        {
            $login_url = "https://jerrywestonmize.com/index.php/login/";
            ob_start();
            $attributes = shortcode_atts(
                array(
                    'content_is_shortcode' => true,
                    'lesson_type' => 'virtual'
                ),
                $atts
            );
            $makeup_credits = get_field('' . $attributes['lesson_type'] . '_makeup_lesson_credits', 'user_' . get_current_user_id());
            // redirect unauthenticated users or users without makeup credits to home page
            if (!is_user_logged_in() || $makeup_credits < 1) {
                echo "<script>window.location = '" . $login_url . "';</script>";
            }
            if ($attributes['content_is_shortcode'] == true) {
                echo do_shortcode($content);
            } else if ($attributes['content_is_shortcode'] == false) {
                echo $content;
            }

            return ob_get_clean();
        }
    }
}

$makeupCreditsRequiredPlugin = new MakeupCreditsRequired();
$makeupCreditsRequiredPlugin->register();