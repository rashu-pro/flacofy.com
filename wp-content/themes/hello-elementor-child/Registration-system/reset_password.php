<?php
/**
 * Template Name: Reset Password
 */
session_start();
require 'config.php';

if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_contact'])) {
    header("Location: /");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm || strlen($password) < 6) {
        $error = "Passwords do not match or too short.";
    } else {
        $contact = $_SESSION['reset_contact'];
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $type = filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE $type = ?");
        $stmt->bind_param("ss", $hashed, $contact);
        if ($stmt->execute()) {
            unset($_SESSION['otp_verified'], $_SESSION['reset_contact']);
            header("Location: /login"); // or wherever login is
            exit();
        } else {
            $error = "Failed to reset password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card mx-auto" style="max-width: 400px;">
        <div class="card-body">
            <h4 class="card-title mb-4">Set New Password</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-success w-100">Reset Password</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>