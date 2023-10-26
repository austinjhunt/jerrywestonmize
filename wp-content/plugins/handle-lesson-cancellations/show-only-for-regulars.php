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
if (!class_exists('HandleLessonCancellation')) {
    class HandleLessonCancellation
    {
        public $plugin_name;
        function __construct()
        {
            $this->plugin_name = plugin_basename(__FILE__);
        }

        function register()
        {

            add_action('AmeliaBookingCanceled', array($this, 'handle_lesson_cancellation'), 10, 3);

            plugin_basename(__FILE__);
        }

        function handle_lesson_cancellation($reservation, $bookings, $container)
        {
            // actions
            echo "HANDLE LESSON CANCELLATION";
            echo $reservation;
            echo $bookings;
            echo $container;
        }


    }
}

$handleLessonCancellationPlugin = new HandleLessonCancellation();
$handleLessonCancellationPlugin->register();