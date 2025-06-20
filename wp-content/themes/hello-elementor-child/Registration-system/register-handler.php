<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['reg_error'] = "Security validation failed. Please try again.";
    return;
}

$full_name = sanitize_text_field($_POST['full_name']);
$contact = trim($_POST['contact']);
$password = trim($_POST['password']);
$confirm_password = trim($_POST['confirm_password']);

$error = null;

if (empty($full_name) || empty($contact) || empty($password)) {
    $error = "All fields are required!";
} elseif (strlen($password) < 6) {
    $error = "Password must be at least 6 characters!";
} elseif ($password !== $confirm_password) {
    $error = "Passwords do not match!";
} else {
    global $wpdb;
    $is_phone = preg_match('/^01[3-9]\d{8}$/', $contact);
    $is_email = filter_var($contact, FILTER_VALIDATE_EMAIL);

    if (!$is_phone && !$is_email) {
        $error = "Invalid phone number or email address!";
    } else {
        $otp = random_int(100000, 999999);
        $expires_at = current_time('mysql', 1);
        $expires_at = date('Y-m-d H:i:s', strtotime($expires_at) + 300);

        $field = $is_phone ? 'phone' : 'email';
        $table_name = $wpdb->prefix . 'reg_system_otps';

        $wpdb->delete($table_name, [$field => $contact]);
        $wpdb->insert(
            $table_name,
            [
                $field => $contact,
                'otp_code' => $otp,
                'expires_at' => $expires_at
            ],
            ['%s', '%s', '%s']
        );

        $_SESSION['reg_data'] = [
            'full_name'     => $full_name,
            'contact'       => $contact,
            'contact_type'  => $field,
            'password'      => password_hash($password, PASSWORD_DEFAULT)
        ];

        if ($is_email) {
            require_once get_stylesheet_directory() . '/registration-system/send_email_otp.php';
            sendEmailOtp($contact, $otp);
        } else {
            send_otp_to_phone($contact, $otp);
        }

        wp_redirect(home_url('/otp-verification/'));
        exit;
    }
}

$_SESSION['reg_error'] = $error;
