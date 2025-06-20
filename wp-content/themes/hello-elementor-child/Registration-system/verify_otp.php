<?php
/**
 * Template Name: verify otp
 */
session_start();
require 'config.php';

$error = '';
$success = '';
$cooldown_seconds = 0; // Default: resend button active
$max_attempts = 3; // Maximum allowed attempts

// Registration or Forgot Password Flow
if (!isset($_SESSION['reg_data']) && !isset($_SESSION['reset_contact'])) {
    header("Location: /");
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

// ✅ Get last_sent_at, attempts and calculate cooldown
$cooldown_stmt = $conn->prepare("SELECT last_sent_at, attempts FROM otps WHERE $contact_type = ? ORDER BY last_sent_at DESC LIMIT 1");
$cooldown_stmt->bind_param("s", $contact);
$cooldown_stmt->execute();
$cooldown_stmt->store_result();
$cooldown_stmt->bind_result($last_sent_at, $attempts);
if ($cooldown_stmt->num_rows > 0) {
    $cooldown_stmt->fetch();
    $last_sent = strtotime($last_sent_at);
    $now = time();
    $cooldown_seconds = max(0, 60 - ($now - $last_sent));
}
$cooldown_stmt->close();

// ✅ Resend OTP logic
if (isset($_POST['resend']) && $cooldown_seconds === 0) {
    $new_otp = rand(100000, 999999);
    $hashed_otp = hash('sha256', $new_otp);
    $expires_at = date('Y-m-d H:i:s', time() + 60);

    $delete_stmt = $conn->prepare("DELETE FROM otps WHERE $contact_type = ?");
    $delete_stmt->bind_param("s", $contact);
    $delete_stmt->execute();
    $delete_stmt->close();

    $insert_stmt = $conn->prepare("INSERT INTO otps ($contact_type, otp_code, expires_at, verified, attempts, last_sent_at) VALUES (?, ?, ?, 0, 0, NOW())");
    $insert_stmt->bind_param("sss", $contact, $hashed_otp, $expires_at);
    if ($insert_stmt->execute()) {
        // Send OTP (not hash!) to user
        if ($contact_type === 'email') {
            require 'send_email_otp.php';
            sendEmailOtp($contact, $new_otp);
        } else {
            require 'send_sms_otp.php';
            sendSmsOtp($contact, $new_otp);
        }

        $success = "A new OTP has been sent to your $contact_type.";
        $cooldown_seconds = 60;
    } else {
        $error = "Failed to resend OTP.";
    }
    $insert_stmt->close();
}

// ✅ OTP verification logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $entered_otp = trim($_POST['otp']);

    $stmt = $conn->prepare("SELECT id, otp_code, expires_at, attempts FROM otps WHERE $contact_type = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $contact);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($otp_id, $otp_code, $expires_at, $attempts);

    if ($stmt->num_rows === 1) {
        $stmt->fetch();

        if ($attempts >= $max_attempts) {
            $error = "Maximum attempts reached. Please try again later.";
        } else {
            if (hash('sha256', $entered_otp) === $otp_code && strtotime($expires_at) > time()) {

                // ✅ Mark OTP as verified
                $update_stmt = $conn->prepare("UPDATE otps SET verified = 1 WHERE id = ?");
                $update_stmt->bind_param("i", $otp_id);
                $update_stmt->execute();
                $update_stmt->close();

                // ✅ Delete the OTP entry after successful verification
                $delete_otp_stmt = $conn->prepare("DELETE FROM otps WHERE id = ?");
                $delete_otp_stmt->bind_param("i", $otp_id);
                $delete_otp_stmt->execute();
                $delete_otp_stmt->close();

                if (isset($_SESSION['reg_data'])) {
                    $data = $_SESSION['reg_data'];
                    $full_name = $data['full_name'];
                    $password = $data['password'];
                    $verified = 1;
                    $created_at = date('Y-m-d H:i:s');
                    $email = ($contact_type === 'email') ? $contact : null;
                    $phone = ($contact_type === 'phone') ? $contact : null;

                    $stmt_insert = $conn->prepare("INSERT INTO users (full_name, phone, email, password, verified, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("ssssis", $full_name, $phone, $email, $password, $verified, $created_at);

                    if ($stmt_insert->execute()) {
                        unset($_SESSION['reg_data']);

                        // ✅ Smart redirect logic (except login/register pages)
                        $redirect_url = $_SESSION['redirect_url'] ?? '/';
                        if (!str_contains($redirect_url, 'login') && !str_contains($redirect_url, 'register')) {
                            header("Location: $redirect_url");
                        } else {
                            header("Location: /");
                        }
                        exit();
                    } else {
                        $error = "Registration failed. Try again.";
                    }
                } else {
                    $_SESSION['otp_verified'] = true;
                    header("Location: https://flacofy.com/reset-password/");
                    exit();
                }
            } else {
                $attempts++;
                $update_attempts_stmt = $conn->prepare("UPDATE otps SET attempts = ? WHERE id = ?");
                $update_attempts_stmt->bind_param("ii", $attempts, $otp_id);
                $update_attempts_stmt->execute();
                $update_attempts_stmt->close();

                $error = "Invalid or expired OTP.";
            }
        }
    } else {
        $error = "No OTP found for this contact.";
    }
    $stmt->close();
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