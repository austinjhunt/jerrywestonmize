<?php
/*
 * Plugin Name: Handle Lesson Cancellations
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin adds additional business logic on top of Amelia appointment (lesson) cancellations. It increments the user's meta field (virtual_makeup_lessons or inperson_makeup_lessons) by 1.  
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