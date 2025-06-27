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
        $rate_check = $wp_rate_limiter->is_rate_limited('login', 3, 900);

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
