<?php
/*
 * Plugin Name: Extend User List Columns with ACF Fields
 * Plugin URI: https://github.com/austinjhunt/jerrywestonmize
 * Description: This plugin allows the admin to extend the users list in the admin dashboard to include ACF fields that have been created for users.
 * Version: 1.1.0
 * Requires PHP: 8
 * Author: Austin Hunt
 * Author URI: https://austinjhunt.com
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add ACF fields to the user list table
add_filter('manage_users_columns', function ($columns) {
    $acf_fields = get_acf_user_fields();
    $selected_fields = get_option('acf_user_list_columns', []);
    
    foreach ($acf_fields as $field_key => $field_label) {
        if (in_array($field_key, $selected_fields)) {
            $columns[$field_key] = $field_label;
        }
    }
    
    return $columns;
});

// Populate ACF field values in the user list table
add_filter('manage_users_custom_column', function ($value, $column_name, $user_id) {
    if (array_key_exists($column_name, get_acf_user_fields())) {
        $acf_field = get_field_object($column_name, 'user_' . $user_id);
        $acf_value = get_field($column_name, 'user_' . $user_id);

        if (is_numeric($acf_value) && empty($acf_value) && $acf_value !== '0') {
            return 0;
        }

        if ($acf_field && $acf_field['type'] === 'number' && empty($acf_value) && $acf_value !== '0') {
            return 0;
        }

        return $acf_value ?: 0;
    }
    return $value;
}, 10, 3);

// Make ACF columns sortable
add_filter('manage_users_sortable_columns', function ($columns) {
    $acf_fields = get_acf_user_fields();
    $selected_fields = get_option('acf_user_list_columns', []);
    
    foreach ($acf_fields as $field_key => $field_label) {
        if (in_array($field_key, $selected_fields)) {
            $columns[$field_key] = $field_key;
        }
    }
    
    return $columns;
});

// Handle custom sorting for ACF columns
add_action('pre_get_users', function ($query) {
    if (is_admin() && $query->get('orderby') && array_key_exists($query->get('orderby'), get_acf_user_fields())) {
        $orderby = $query->get('orderby');
        $order = $query->get('order', 'ASC');

        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value_num'); // Use meta_value_num for numeric sorting
        $query->set('order', $order);
    }
});

// Function to retrieve ACF user fields
function get_acf_user_fields() {
    if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields')) {
        $acf_fields = [];
        $groups = acf_get_field_groups(['user' => 'all']);
        
        foreach ($groups as $group) {
            $fields = acf_get_fields($group['key']);
            if($fields) {
                foreach ($fields as $field) {
                    $acf_fields[$field['name']] = $field['label'];
                }
            }
        }
        return $acf_fields;
    }
    return [];
}

// Add settings page for column visibility
add_action('admin_menu', function () {
    add_submenu_page('users.php', 'ACF User List Columns', 'ACF User List Columns', 'manage_options', 'acf-user-list-columns', 'acf_user_list_columns_page');
});

function acf_user_list_columns_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if ($_POST['acf_user_list_columns']) {
        update_option('acf_user_list_columns', $_POST['acf_user_list_columns']);
    }
    
    $selected_fields = get_option('acf_user_list_columns', []);
    $acf_fields = get_acf_user_fields();
    
    echo '<div class="wrap">';
    echo '<h1>Extend User List Columns with ACF User Fields</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';
    foreach ($acf_fields as $field_key => $field_label) {
        $checked = in_array($field_key, $selected_fields) ? 'checked' : '';
        echo '<tr><th>' . esc_html($field_label) . '</th><td>';
        echo '<input type="checkbox" name="acf_user_list_columns[]" value="' . esc_attr($field_key) . '" ' . $checked . ' />';
        echo '</td></tr>';
    }
    echo '</table>';
    submit_button('Save Changes');
    echo '</form>';
    echo '</div>';
}