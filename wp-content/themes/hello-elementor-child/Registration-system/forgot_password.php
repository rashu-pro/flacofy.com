<?php
/**
 * Template Name: Forgot Password (dev)
 */
$forgot_result = handle_forgot_password();
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

        <?php if (!empty($forgot_result['error'])): ?>
            <div class="alert alert-danger"><?php echo esc_html($forgot_result['error']); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php wp_nonce_field('forgot_password_action', 'forgot_password_nonce'); ?>

            <div class="mb-3">
                <input type="text" class="form-control" name="contact" placeholder="Phone or Email" required>
            </div>
            <button type="submit" name="forgot_password_submit" class="btn btn-primary w-100">Send OTP</button>
        </form>
    </div>
</div>

</body>
</html>
