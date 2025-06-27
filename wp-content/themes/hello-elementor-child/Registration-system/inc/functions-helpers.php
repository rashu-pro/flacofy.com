<?php

/**
 * DISPLAY RATE LIMIT INFORMATION
 */
function display_rate_limit_info($action_type) {
    global $wp_rate_limiter;

    $rate_check = $wp_rate_limiter->is_rate_limited($action_type, 3, 120);

    if (!$rate_check['blocked'] && $rate_check['attempts'] > 0) {
        return 'Attempts remaining: ' . $rate_check['remaining'] . '/3';
    }
    return false;
}

function create_wordpress_user($reg_data, $contact, $contact_type) {
    $full_name = sanitize_text_field($reg_data['full_name']);
    $password = $reg_data['password']; // already hashed

    // Set username and email
    $username = $contact;
    $email = $contact_type === 'email' ? $contact : 'phone_' . $username . '@example.com';

    // Create WordPress user
    $user_id = wp_insert_user([
        'user_login' => $username,
        'user_pass' => $password,
        'user_email' => $email,
        'display_name' => $full_name,
        'first_name' => $full_name,
        'role' => 'subscriber' // Set default role
    ]);

    if (is_wp_error($user_id)) {
        return array(
            'success' => false,
            'error' => 'Failed to create account: ' . $user_id->get_error_message()
        );
    }

    // Save phone as user meta if needed
    if ($contact_type === 'phone') {
        update_user_meta($user_id, 'phone', $contact);
    }

    // Auto-login the user
    $user = get_user_by('id', $user_id);
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    do_action('wp_login', $user->user_login, $user);

    return array(
        'success' => true,
        'user_id' => $user_id
    );
}


function generate_otp($field, $contact, $expires_duration = 300){
    global $wpdb;
    // Generate OTP and expiration time
    $otp = random_int(100000, 999999);
    $expires_at = current_time('mysql', 1); // GMT
    $expires_at = date('Y-m-d H:i:s', strtotime($expires_at) + $expires_duration); // +5 mins
    $table_name = $wpdb->prefix . 'reg_system_otps';

    // Clean up old OTPs
    if (in_array($field, ['phone', 'email'])) {
        $wpdb->delete($table_name, [$field => $contact]);
    }

    // Save new OTP
    $result = $wpdb->insert(
        $table_name,
        [
            $field => $contact,
            'otp_code' => $otp,
            'expires_at' => $expires_at
        ],
        ['%s', '%s', '%s']
    );
    if(!$result) return;
    return $otp;
}
