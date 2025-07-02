<?php
/**
 * Template Name: Reset Password (dev)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$reset_password_result = handle_custom_reset_password();
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

            <?php
            global $wp_rate_limiter;
            $rate_check = $wp_rate_limiter->is_rate_limited('reset_password', 3, 60);
            ?>

            <?php if (!empty($reset_password_result['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo esc_html($reset_password_result['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($reset_password_result['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $reset_password_result['success']; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php wp_nonce_field('reset_password_action', 'reset_password_nonce'); ?>
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <input type="hidden" value="<?php echo $_SESSION['reset_contact'] ?>" name="contact">
                <button type="submit" name="reset_password_submit" class="btn btn-success w-100">Reset Password</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
