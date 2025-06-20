<?php
/**
 * Template Name: verify otp
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
//require 'config.php';

global $wpdb;

$error = '';
$success = '';
$cooldown_seconds = 0; // Default: resend button active
$max_attempts = 3; // Maximum allowed attempts

// Registration or Forgot Password Flow
if (!isset($_SESSION['reg_data']) && !isset($_SESSION['reset_contact'])) {
    wp_redirect(home_url('/'));
    exit();
}

if (isset($_SESSION['reg_data'])) {
    // Registration flow
    $contact = $_SESSION['reg_data']['contact'];
    $contact_type = $_SESSION['reg_data']['contact_type'];
} else {
    // Forgot password flow
    $contact = $_SESSION['reset_contact'];
    $contact_type = filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
}

// ✅ Resend OTP logic
if (isset($_POST['resend']) && $cooldown_seconds === 0) {
    // resend otp login here
}

// ✅ OTP verification logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $is_email = filter_var($contact, FILTER_VALIDATE_EMAIL);
    $user_otp = trim($_POST['otp']);

    // Retrieve session data
    $reg_data = $_SESSION['reg_data'] ?? null;

    if (!$reg_data) {
        $error = "Session expired. Please register again.";
    } else {
        $full_name    = sanitize_text_field($reg_data['full_name']);
        $contact      = sanitize_text_field($reg_data['contact']);
        $contact_type = $reg_data['contact_type']; // 'email' or 'phone'
        $password     = $reg_data['password']; // already hashed

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
                $error = "OTP not found. Please try registering again.";
            } elseif ($row->otp_code !== $user_otp) {
                $error = "Incorrect OTP. Please try again.";
            } elseif (strtotime($row->expires_at) < time()) {
                $error = "OTP has expired. Please register again.";
            } else {
                $user_exists = false;
                // ✅ Create WordPress user
                $username = $contact;
                $email    = $contact_type === 'email' ? $contact : 'phone_'.$username . '@example.com';

                $user_id = wp_insert_user([
                    'user_login' => $username,
                    'user_pass'  => $password,
                    'user_email' => $email,
                    'display_name' => $full_name,
                    'first_name' => $full_name
                ]);

                if (is_wp_error($user_id)) {
                    $error = "Failed to create account: " . $user_id->get_error_message();
                    if(array_key_exists('existing_user_login', $user_id->errors)){
                        $user_exists = true;
                    }
                } else {
                    // Save phone as user meta if needed
                    if ($contact_type === 'phone') {
                        update_user_meta($user_id, 'phone', $contact);
                    }

                    // Cleanup
                    $wpdb->delete($table_name, [ $contact_type => $contact ]);
                    unset($_SESSION['reg_data']);

                    $success = "Account created successfully!";
                    // You can auto-login user here or redirect
                    // Log in the user
                    $user = get_user_by('id', $user_id);
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    do_action('wp_login', $user->user_login, $user);
                    wp_redirect(home_url('/my-account'));
                }
            }
        } else {
            $error = "Invalid contact type.";
        }
    }

    // Show messages
    if (!empty($error)) {
        echo '<div class="error-message">' . esc_html($error) . '</div>';
    } elseif (!empty($success)) {
        echo '<div class="success-message">' . esc_html($success) . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .otp-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .otp-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        .otp-message {
            margin-bottom: 2rem;
            color: #555;
        }
        .otp-inputs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            gap: 10px;
        }
        .otp-input {
            width: 35px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            margin-bottom: 1.5rem;
        }
        .resend-container {
            margin-top: 1rem;
        }
        #resendBtn {
            background: none;
            border: none;
            color: #0d6efd;
            cursor: pointer;
        }
        #resendBtn:disabled {
            color: #aaa;
            cursor: not-allowed;
        }

        /* Responsive tweak for mobile/tablet */
        @media (max-width: 768px) {
            body {
                align-items: flex-start;
                padding-top: 40px;
            }

            .otp-container {
                margin-top: 20px;
            }
        }

        /* Responsive alert box size for mobile/tablet */
        @media (max-width: 768px) {
            .alert {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                margin-bottom: 0.8rem;
            }
        }

    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // OTP input auto-focus and navigation
            const otpInputs = document.querySelectorAll('.otp-input');

            otpInputs.forEach((input, index) => {
                // Handle input navigation
                input.addEventListener('input', (e) => {
                    if (input.value.length === 1) {
                        if (index < 5) {
                            otpInputs[index + 1].focus();
                        }
                    }
                });

                // Handle backspace
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && input.value.length === 0 && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });
            });

            // Form submission handler
            document.getElementById('otpForm').addEventListener('submit', function(e) {
                let otp = '';
                otpInputs.forEach(input => {
                    otp += input.value;
                });
                document.getElementById('otp').value = otp;
            });

            // Countdown timer
            let countdown = <?= $cooldown_seconds ?>;
            const timerElement = document.getElementById('timer');
            const resendBtn = document.getElementById('resendBtn');

            function updateTimer() {
                if (countdown > 0) {
                    const minutes = Math.floor(countdown / 60);
                    const seconds = countdown % 60;
                    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    countdown--;
                    setTimeout(updateTimer, 1000);
                } else {
                    timerElement.textContent = '';
                    resendBtn.disabled = false;
                }
            }

            if (countdown > 0) {
                resendBtn.disabled = true;
                updateTimer();
            }
        });
    </script>
</head>
<body>
<div class="otp-container">
    <h2 class="otp-title">OTP Verify</h2>
    <p class="otp-message">Please enter the 6-digit code sent to<br><strong><?= htmlspecialchars($contact) ?></strong></p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
        <input type="hidden" name="otp" id="otp">

        <div class="otp-inputs">
            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autofocus required>
            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
        </div>

        <button type="submit" name="verify" class="btn btn-primary btn-submit">Submit</button>
    </form>

    <div class="resend-container">
        <?php if ($cooldown_seconds > 0): ?>
            <p>Resend code in <span id="timer">0:<?= str_pad($cooldown_seconds, 2, '0', STR_PAD_LEFT) ?></span></p>
        <?php endif; ?>
        <form method="POST">
            <button type="submit" name="resend" id="resendBtn" <?= $cooldown_seconds > 0 ? 'disabled' : '' ?>>Resend OTP</button>
        </form>
    </div>
</div>
</body>
</html>
