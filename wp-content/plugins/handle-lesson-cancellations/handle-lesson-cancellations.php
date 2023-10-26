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

        function increment_virtual_makeup_lesson_credits($user_id)
        {
            error_log('increment_virtual_makeup_lesson_credits for user id: ' . $user_id . '');
            // Get current values
            $virtual_credits = get_field('virtual_makeup_lesson_credits', 'user_' . $user_id);
            error_log('before value: ' . $virtual_credits . '');
            // Increment values
            $virtual_credits++;
            error_log('after value: ' . $virtual_credits . '');
            // Update fields with incremented values
            update_field('virtual_makeup_lesson_credits', $virtual_credits, 'user_' . $user_id);
        }
        function increment_inperson_makeup_lesson_credits($user_id)
        {
            error_log('increment_inperson_makeup_lesson_credits for user id: ' . $user_id . '');
            // Get current values 
            $inperson_credits = get_field('inperson_makeup_lesson_credits', 'user_' . $user_id);

            error_log('before value: ' . $inperson_credits . '');
            // Increment values 
            $inperson_credits++;

            error_log('after value: ' . $inperson_credits . '');

            // Update fields with incremented values 
            update_field('inperson_makeup_lesson_credits', $inperson_credits, 'user_' . $user_id);
        }

        function get_amelia_service_category_name_from_service_id($service_id)
        {
            global $wpdb;
            $category_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT categoryId FROM wp_amelia_services WHERE id = %d",
                    $service_id
                )
            );

            $category_name = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT name FROM wp_amelia_categories WHERE id = %d",
                    $category_id
                )
            );
            return $category_name;
        }

        function handle_lesson_cancellation($reservation, $bookings, $container)
        {

            // Extract email from the array
            $customer_email = $bookings[0]['customer']['email'];
            error_log('customer email: ' . $customer_email . '');

            // Get WordPress user by email
            $user = get_user_by('email', $customer_email);

            // Check if a user exists with the provided email
            if ($user) {
                error_log('User ID: ' . $user->ID);
                $service_id = $reservation['serviceId'];
                $category_name = $this->get_amelia_service_category_name_from_service_id($service_id);

                if ($category_name == "Virtual Lessons") {
                    $this->increment_virtual_makeup_lesson_credits($user->ID);
                } else if ($category_name == "In Person Lessons") {
                    $this->increment_inperson_makeup_lesson_credits($user->ID);
                }
            }
        }


    }
}

$handleLessonCancellationPlugin = new HandleLessonCancellation();
$handleLessonCancellationPlugin->register();