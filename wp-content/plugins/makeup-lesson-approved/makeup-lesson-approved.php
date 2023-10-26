<?php
/*
 * Plugin Name: Make Up Lesson Approved - Decrement Make Up Credits
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin adds additional business logic on top of Amelia appointment (lesson) booking, specifically for lessons in the Make Up Lessons category. When those lessons are booked, it decrements either virtual_makeup_lesson_credits or inperson_makeup_lesson_credits for the user. 
 * Version: 1.0.0
 * Requires PHP: 8
 * Author: Austin Hunt
 * Author URI: https://austinjhunt.com
 */

defined('ABSPATH') or die('You should not be here');
if (!class_exists('DecrementMakeupCredits')) {
    class DecrementMakeupCredits
    {
        public $plugin_name;
        function __construct()
        {
            $this->plugin_name = plugin_basename(__FILE__);
        }

        function register()
        {

            add_action('AmeliaAppointmentBookingStatusUpdated', array($this, 'handle_makeup_lesson_booking'), 10, 3);
            plugin_basename(__FILE__);
        }

        function decrement_virtual_makeup_lesson_credits($user_id)
        {
            error_log('decrement_virtual_makeup_lesson_credits for user id: ' . $user_id . '');
            // Get current values
            $virtual_credits = get_field('virtual_makeup_lesson_credits', 'user_' . $user_id);
            error_log('before value: ' . $virtual_credits . '');
            // decrement values
            $virtual_credits--;
            error_log('after value: ' . $virtual_credits . '');
            // Update fields with decremented values
            update_field('virtual_makeup_lesson_credits', $virtual_credits, 'user_' . $user_id);
        }
        function decrement_inperson_makeup_lesson_credits($user_id)
        {
            error_log('decrement_inperson_makeup_lesson_credits for user id: ' . $user_id . '');
            // Get current values 
            $inperson_credits = get_field('inperson_makeup_lesson_credits', 'user_' . $user_id);

            error_log('before value: ' . $inperson_credits . '');
            // decrement values 
            $inperson_credits--;

            error_log('after value: ' . $inperson_credits . '');

            // Update fields with decremented values 
            update_field('inperson_makeup_lesson_credits', $inperson_credits, 'user_' . $user_id);
        }

        function get_amelia_service_name($service_id)
        {
            global $wpdb;
            error_log('get_amelia_service_name for service id: ' . $service_id . '');
            $service_name = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT name FROM wp_amelia_services WHERE id = %d",
                    $service_id
                )
            );
            error_log('service name: ' . $service_name . '');
            return $service_name;
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
        function handle_makeup_lesson_booking($reservation, $bookings, $container)
        {
            error_log('AmeliaAppointmentBookingStatusUpdated. calling handle_makeup_lesson_booking');

            // continue only if reservation status is now approved
            if ($reservation['status'] != 'approved') {
                error_log('reservation status is not approved. exiting.');
                return;
            }

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
                $service_name = $this->get_amelia_service_name($service_id);
                if ($category_name == "Make Up Lessons") {
                    error_log('Category = makeup lessons');
                    // if service  name contains "Virtual", decrement virtual credits
                    if (strpos(strtolower($service_name), 'virtual') !== false) {
                        $this->decrement_virtual_makeup_lesson_credits($user->ID);
                    }
                    // if service name lowercase contains in-person, inperson, or in person, decrement in person credits
                    else if (strpos(strtolower($service_name), 'in-person') !== false || strpos(strtolower($service_name), 'inperson') !== false || strpos(strtolower($service_name), 'in person') !== false) {
                        $this->decrement_inperson_makeup_lesson_credits($user->ID);
                    }
                }

            }
        }

    }
}

$decrementMakeupCreditsPlugin = new DecrementMakeupCredits();
$decrementMakeupCreditsPlugin->register();