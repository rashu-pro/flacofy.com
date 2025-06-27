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

/**
 * Send otp to email
 * @param $email
 * @param $otp
 * @return bool
 */
function send_otp_to_email($email, $otp)
{
    $to = $email;
    $subject = 'Your OTP Verification Code From FLACOFY';
    $message = '
    <html>
    <head>
        <title>OTP Verification</title>
    </head>
    <body>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #333;">OTP Verification for Registration to FLACOFY</h2>
            <p style="font-size: 16px; color: #666;">Your OTP verification code is:</p>
            <div style="background-color: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px;">
                <h1 style="color: #007cba; font-size: 32px; margin: 0; letter-spacing: 5px;">' . $otp . '</h1>
            </div>
            <p style="font-size: 14px; color: #666;">This code will expire in 5 minutes. Do not share this code with anyone.</p>
            <p style="font-size: 12px; color: #999;">If you did not request this code, please ignore this email.</p>
        </div>
    </body>
    </html>
    ';

    // Set content type to send HTML email
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
    );

    $sent = wp_mail($to, $subject, $message, $headers);

    if (!$sent) {
        error_log('Email OTP sending failed for: ' . $email);
        return false;
    }

    return true;
}




