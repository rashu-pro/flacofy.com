<?php
/**
 * Custom login function
 * @return string[]|void
 */
function handle_custom_login() {
    global $wp_rate_limiter;

    $error_message = '';
    $success_message = '';

    if (isset($_POST['custom_login_submit'])) {
        // Check rate limit (5 attempts per 15 minutes)
        $rate_check = $wp_rate_limiter->is_rate_limited('login', 3, 60);

        if ($rate_check['blocked']) {
            return array(
                'error' => $rate_check['message'] . ' Try again in ' . ceil($rate_check['remaining_time'] / 60) . ' minutes.',
                'rate_limited' => true
            );
        }
        // Log the attempt
        $wp_rate_limiter->log_attempt('login');

        // Verify nonce for security
        if (!wp_verify_nonce($_POST['login_nonce'], 'custom_login_nonce')) {
            $error_message = 'Security check failed. Please try again.';
        } else {
            // Get form data
            $username = sanitize_text_field($_POST['username']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']) ? true : false;

            // Validate inputs
            if (empty($username) || empty($password)) {
                $error_message = 'Please enter both username and password.';
            } else {
                // Attempt to authenticate user
                $user_data = array(
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => $remember
                );

                $user = wp_signon($user_data, false);

                if (is_wp_error($user)) {
                    // Handle different error types
                    $error_code = $user->get_error_code();

                    switch ($error_code) {
                        case 'invalid_username':
                            $error_message = 'Invalid username. Please check your username and try again.';
                            break;
                        case 'incorrect_password':
                            $error_message = 'Incorrect password. Please check your password and try again.';
                            break;
                        case 'empty_username':
                            $error_message = 'Please enter your username.';
                            break;
                        case 'empty_password':
                            $error_message = 'Please enter your password.';
                            break;
                        default:
                            $error_message = 'Login failed. Please check your credentials and try again.';
                            break;
                    }
                } else {
                    // Login successful
                    $success_message = 'Login successful! Redirecting...';
                    // Success - reset rate limit
                    $wp_rate_limiter->reset_attempts('login');

                    // Redirect to dashboard or intended page
                    $redirect_url = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : admin_url();

                    // Clean redirect URL
                    $redirect_url = wp_validate_redirect($redirect_url, home_url());

                    // Redirect after successful login
                    wp_redirect($redirect_url);
                    exit;
                }
            }
        }
    }

    return array(
        'error' => $error_message,
        'success' => $success_message
    );
}

/**
 * Handle otp verification
 * @return array|string[]
 */
function handle_otp_verification() {
    global $wpdb, $wp_rate_limiter;

    $error = '';
    $success = '';
    $cooldown_seconds = 0;
    $max_attempts = 3;

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if we have required session data
    if (!isset($_SESSION['reg_data']) && !isset($_SESSION['reset_contact'])) {
        return array(
            'error' => 'Session expired. Please start the process again.',
            'redirect' => home_url('/register/')
        );
    }

    // Determine contact and type
    if (isset($_SESSION['reg_data'])) {
        // Registration flow
        $contact = $_SESSION['reg_data']['contact'];
        $contact_type = $_SESSION['reg_data']['contact_type'];
    } else {
        // Forgot password flow
        $contact = $_SESSION['reset_contact'];
        $contact_type = filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    }

    // Handle resend OTP
    if (isset($_POST['resend']) && $cooldown_seconds === 0) {
        $otp = generate_otp($contact_type, $contact, '120');
        if(!$otp){
            return array(
                'error' => 'Otp sending failed, try again.',
                'cooldown' => 120
            );
        }
        if($contact_type === 'phone') send_otp_to_phone($contact, $otp);
        if($contact_type === 'email') send_otp_to_email($contact, $otp);
        return array(
            'success' => 'OTP has been resent successfully.',
            'cooldown' => 120
        );
    }

    // Handle OTP verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {

        // Check rate limit (3 attempts per 5 minutes for OTP)
        $rate_check = $wp_rate_limiter->is_rate_limited('otp_verification', 3, 60);

        if ($rate_check['blocked']) {
            return array(
                'error' => $rate_check['message'] . ' Wait ' . ceil($rate_check['remaining_time'] / 60) . ' minutes.',
                'rate_limited' => true
            );
        }

        // Log the attempt
        $wp_rate_limiter->log_attempt('otp_verification');

        $user_otp = trim($_POST['otp']);

        // Validate OTP input
        if (empty($user_otp)) {
            return array('error' => 'Please enter the OTP code.');
        }

        // Retrieve session data
        $reg_data = $_SESSION['reg_data'] ?? null;

        if (!$reg_data && !isset($_SESSION['reset_contact'])) {
            return array('error' => 'Session expired. Please register again.');
        }

        // Check OTP from database
        $table_name = $wpdb->prefix . 'reg_system_otps';

        if (in_array($contact_type, ['email', 'phone'])) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT otp_code, expires_at FROM $table_name WHERE $contact_type = %s ORDER BY id DESC LIMIT 1",
                    $contact
                )
            );

            if (!$row) {
                return array('error' => 'OTP not found. Please try registering again.');
            }

            if ($row->otp_code !== $user_otp) {
                return array('error' => 'Incorrect OTP. Please try again.');
            }

            if (strtotime($row->expires_at) < time()) {
                return array('error' => 'OTP has expired. Please register again.');
            }

            // OTP is valid - proceed with account creation or password reset
            if (isset($_SESSION['reg_data'])) {
                // Registration flow - create WordPress user
                $result = create_wordpress_user($reg_data, $contact, $contact_type);

                if ($result['success']) {
                    // Cleanup
                    $wpdb->delete($table_name, [$contact_type => $contact]);
                    unset($_SESSION['reg_data']);

                    // Success - reset rate limit
                    $wp_rate_limiter->reset_attempts('otp_verification');

                    return array(
                        'success' => 'Account created successfully. Your are being redirected to the My Account page.',
                        'user_id' => $result['user_id'],
                        'auto_login' => true,
                        'redirect' => home_url('/my-account')
                    );
                } else {
                    return array('error' => $result['error']);
                }

            } else {
                // Forgot password flow - verify OTP for password reset
                // Cleanup OTP
                $wpdb->delete($table_name, [$contact_type => $contact]);

                // Success - reset rate limit
                $wp_rate_limiter->reset_attempts('otp_verification');

                return array(
                    'success' => 'OTP verified successfully. You are being redirected to reset password page to setup a new password.',
                    'verified' => true,
                    'auto_login' => true,
                    'redirect' => home_url('/reset-password/')
                );
            }

        } else {
            return array('error' => 'Invalid contact type.');
        }
    }

    // Return current state
    return array(
        'contact' => $contact,
        'contact_type' => $contact_type,
        'cooldown' => $cooldown_seconds
    );
}

/**
 * Handler for forgot_password submit action
 * @return array|string[]|void
 * @throws Exception
 */
function handle_forgot_password() {
    global $wp_rate_limiter, $wpdb;

    $error_message = '';
    $success_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password_submit'])) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // ✅ Rate limit (3 attempts per 5 minutes)
        $rate_check = $wp_rate_limiter->is_rate_limited('forgot_password', 3, 60);
        if ($rate_check['blocked']) {
            return [
                'error' => $rate_check['message'] . ' Try again in ' . ceil($rate_check['remaining_time'] / 60) . ' minutes.',
                'rate_limited' => true
            ];
        }

        $wp_rate_limiter->log_attempt('forgot_password');

        // ✅ CSRF validation
        if (!isset($_POST['forgot_password_nonce']) || !wp_verify_nonce($_POST['forgot_password_nonce'], 'forgot_password_action')) {
            return ['error' => 'Invalid request. Please refresh the page and try again.'];
        }

        // ✅ Sanitize input
        $contact = sanitize_text_field($_POST['contact'] ?? '');

        if (empty($contact)) {
            return ['error' => 'Please enter your phone number or email!'];
        }

        // Determine contact type
        $is_phone = preg_match('/^01[3-9]\d{8}$/', $contact);
        $is_email = is_email($contact);

        if (!$is_phone && !$is_email) {
            return ['error' => 'Invalid phone number or email address!'];
        }

        // Check if user exists using WP API
        $user = $is_email ? get_user_by('email', $contact) : get_user_by('login', $contact); // assumes phone stored in usermeta
        if (!$user) {
            return ['error' => 'No account found with this contact!'];
        }

        $field = $is_email ? 'email' : 'phone';

        // 🔐 OTP Table (assuming 'wp_otps')
        $table = $wpdb->prefix . 'reg_system_otps';

        // Delete any previous OTP for this contact
        $wpdb->delete($table, [$field => $contact]);

        // Generate new OTP
        $otp = generate_otp($field, $contact, '120');

        if (!$otp) {
            error_log("Failed to insert OTP for $contact");
            return ['error' => 'Failed to send OTP. Please try again later.'];
        }

        if (isset($_SESSION['reg_data'])) {
            unset($_SESSION['reg_data']);
        }
        // ✅ Store reset context
        $_SESSION['reset_contact'] = $contact;

        // 📤 Send OTP
        if ($is_email) {
            send_otp_to_email($contact, $otp);
        } else {
            send_otp_to_phone($contact, $otp);
        }

        // ✅ Redirect
        wp_redirect(home_url('/otp-verification/'));
        exit;
    }

    return [
        'error' => $error_message,
        'success' => $success_message
    ];
}

/**
 * Handler for reset password
 * @return array|string[]
 */
function handle_custom_reset_password() {
    global $wp_rate_limiter;

    $error_message = '';
    $success_message = '';

    if (isset($_POST['reset_password_submit'])) {
        // Rate limit: 3 attempts per 10 minutes (adjust as needed)
        $rate_check = $wp_rate_limiter->is_rate_limited('reset_password', 3, 60);

        if ($rate_check['blocked']) {
            return array(
                'error' => $rate_check['message'] . ' Try again in ' . ceil($rate_check['remaining_time'] / 60) . ' minutes.',
                'rate_limited' => true
            );
        }

        // Log the attempt
        $wp_rate_limiter->log_attempt('reset_password');

        // Verify nonce
        if (!wp_verify_nonce($_POST['reset_password_nonce'], 'reset_password_action')) {
            $error_message = 'Security check failed. Please try again.';
        } else {
            // Get data
            $user_identifier = sanitize_text_field($_POST['contact']);
            $password = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            // Find user by email or username
            $user = is_email($user_identifier)
                ? get_user_by('email', $user_identifier)
                : get_user_by('login', $user_identifier);

            if (!$user) {
                $error_message = 'User not found.';
            } elseif (empty($password) || empty($confirm)) {
                $error_message = 'Please enter both password fields.';
            } elseif ($password !== $confirm) {
                $error_message = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error_message = 'Password must be at least 6 characters.';
            } else {
                // Set new password
                wp_set_password($password, $user->ID);

                // Optionally reset login attempts
                $wp_rate_limiter->reset_attempts('reset_password');

                // Clear session if used
                if (session_status() === PHP_SESSION_ACTIVE) {
                    unset($_SESSION['reset_contact']);
                }

                $success_message = 'Password has been reset successfully. <a href="' . home_url('/login/') . '">Login Here</a>';
            }
        }
    }

    return array(
        'error' => $error_message,
        'success' => $success_message
    );
}


