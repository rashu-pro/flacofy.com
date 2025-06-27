<?php
/**
 * Creates an reg_system_otps table if not exists
 * @return string[]
 */
function create_otp_table_if_not_exists()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'reg_system_otps';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        phone VARCHAR(20) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        otp_code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (phone),
        INDEX (email)
    ) $charset_collate;";

    return dbDelta($sql);
}

/**
 * Send otp to phone number
 * @param $phone
 * @param $otp
 * @return false|string
 */
function send_otp_to_phone($phone, $otp)
{
    $response = wp_remote_post('http://api.icombd.com/api/v2/sendsms/plaintext', [
        'method' => 'POST',
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            "username" => "flacofy",
            "password" => "flacofy534",
            "sender" => "8809617620823",
            "message" => 'Your OTP code is: ' . $otp,
            "to" => '88' . $phone
        ]),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('SMS sending failed: ' . $response->get_error_message());
        return false;
    }

    return wp_remote_retrieve_body($response);
}



