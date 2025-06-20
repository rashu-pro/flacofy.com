<?php
/**
 * Template Name: Forgot Password
 */
session_start();
require 'config.php';

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Adding security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token. Please refresh the page and try again.");
    }

    // Sanitize the contact input to prevent malicious code injection
    $contact = filter_var(trim($_POST['contact']), FILTER_SANITIZE_STRING);

    if (empty($contact)) {
        $error = "Please enter your phone number or email!";
    } else {
        $is_phone = preg_match('/^01[3-9]\d{8}$/', $contact);
        $is_email = filter_var($contact, FILTER_VALIDATE_EMAIL);

        if (!$is_phone && !$is_email) {
            $error = "Invalid phone number or email address!";
        } else {
            $field = $is_phone ? 'phone' : 'email';

            // Check if contact exists in users table
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE $field = ?");
            $checkStmt->bind_param("s", $contact);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows === 0) {
                $error = "No account found with this contact!";
            } else {
                // Delete existing OTPs
                $deleteStmt = $conn->prepare("DELETE FROM otps WHERE $field = ?");
                $deleteStmt->bind_param("s", $contact);
                $deleteStmt->execute();
                $deleteStmt->close();

                // Generate and insert new OTP
                $otp = random_int(100000, 999999);
                $hashed_otp = hash('sha256', $otp);
                $expires_at = date('Y-m-d H:i:s', time() + 60);

                $insertStmt = $conn->prepare("INSERT INTO otps ($field, otp_code, expires_at) VALUES (?, ?, ?)");
                $insertStmt->bind_param("sss", $contact, $hashed_otp, $expires_at);

                if ($insertStmt->execute()) {
                    // 🧼 Clean previous registration session if exists
                    unset($_SESSION['reg_data']);

                    // ✅ Set reset flow session
                    $_SESSION['reset_contact'] = $contact;

                    // Send OTP based on whether the contact is email or phone
                    if ($is_email) {
                        require 'send_email_otp.php';
                        sendEmailOtp($contact, $otp);
                    } else {
                        require 'send_sms_otp.php';
                        sendSmsOtp($contact, $otp);
                    }

                    header("Location: https://flacofy.com/otp-verification/");
                    exit();
                } else {
                    $error = "Failed to send OTP. Please try again.";
                    // Log the error for debugging purposes
                    error_log("Failed to insert OTP for contact: $contact");
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        .center-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding-top: 10vh;
            background-color: #f8f9fa;
        }
        .forgot-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            max-width: 400px;
            width: 100%;
        }
        .forgot-card h4 {
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .forgot-card p {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="center-wrapper">
    <div class="forgot-card">
        <h4>Forgot Password</h4>
        <p>Enter your phone or email</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="mb-3">
                <input type="text" class="form-control" name="contact" placeholder="Phone or Email" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Send OTP</button>
        </form>
    </div>
</div>

</body>
</html>