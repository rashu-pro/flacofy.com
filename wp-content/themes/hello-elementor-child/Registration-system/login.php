<?php
/**
 * Template Name: Login
 */

// Handle login if form was submitted
$login_result = handle_custom_login();

// Check if user is already logged in
if (is_user_logged_in()) {
    echo '<div class="login-message success">You are already logged in. <p></p>Go to my account page: <a href="' . home_url('/my-account/') . '">My Account</a></p></div>';
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
        }

        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: start;
            padding: 0px 10px 5px;
            flex-direction: column;
            position: relative;
            min-height: 86vh;
        }

        .page-logo {
            width: 100%;
            text-align: center;
            margin-top: 20px;
            margin-bottom: 25px;
        }

        .page-logo img {
            width: 50px;
            border-radius: 10px;
        }

        .login-box {
            border-radius: 12px;
            width: 100%;
            max-width: 380px;
            padding: 25px 20px;
            background-color: #fff;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            margin: 0 auto;
        }

        .login-box:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .login-box h2 {
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .login-box label {
            font-weight: 600;
            margin-top: 12px;
            display: block;
            font-size: 14px;
        }

        .login-box input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .login-box input:focus {
            border-color: #FF9800;
            outline: none;
        }

        .login-box button {
            width: 100%;
            background-color: #FF9800;
            color: #fff;
            padding: 12px;
            border: none;
            font-weight: 600;
            border-radius: 25px;
            margin-top: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .login-box button:hover {
            background-color: #f58c00;
        }

        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .info-text a {
            color: #0066c0;
        }

        .error {
            color: red;
            margin-bottom: 12px;
        }

        .footer {
            border-top: 1px solid #ddd;
            padding: 20px 0;
            text-align: center;
            font-size: 12px;
            background-color: #fff;
            color: #666;
        }

        .footer a {
            color: #0066c0;
            text-decoration: none;
            margin: 0 10px;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .forgot-link {
            font-size: 12px;
            margin-top: 6px;
            display: block;
            text-align: right;
        }

        .forgot-link a {
            color: #0066c0;
            text-decoration: none;
        }

        .forgot-link a:hover {
            text-decoration: underline;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
        }

        @media (max-width: 768px) {
            html, body {
                height: auto;
            }

            .login-wrapper {
                padding-top: 0 !important;
                margin-top: -15px !important;
                align-items: center !important;
            }

            .page-logo {
                margin-top: 35px !important;
                margin-bottom: 25px !important;
            }

            .page-logo img {
                width: 65px;
            }

            .login-box {
                padding: 20px 15px;
                margin-top: 0;
            }

            .footer {
                margin-top: 85px;
            }
        }

        @media (min-width: 769px) {
            .login-wrapper {
                padding-top: 10px;
                padding-bottom: 50px;
            }

            .footer {
                margin-top: 40px;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="page-logo">
        <a href="<?php echo home_url('/') ?>">
            <img src="https://flacofy.com/wp-content/uploads/2025/01/logo.png" alt="FLACOFY Logo">
        </a>
    </div>

    <div class="login-box">
        <div class="logo">
            <h2><strong>Login</strong></h2>
        </div>

        <?php
        global $wp_rate_limiter;
        $rate_check = $wp_rate_limiter->is_rate_limited('login', 3, 60);
        ?>

        <?php if (!empty($login_result['error'])): ?>
            <div class="login-message error">
                <?php echo esc_html($login_result['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($login_result['success'])): ?>
            <div class="login-message success">
                <?php echo esc_html($login_result['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="custom-login-form">
            <?php wp_nonce_field('custom_login_nonce', 'login_nonce'); ?>

            <label for="contact">Enter mobile number or email</label>
            <input type="text" id="contact" name="username" placeholder="01XXXXXXXXX or email@example.com"
                   value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>"
                   required>

            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <i class="bi bi-eye toggle-password" id="togglePassword"></i>
            </div>

            <div class="forgot-link">
                <a href="https://flacofy.com/forget_password/">Forgot Password?</a>
            </div>

            <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/my-account/')); ?>">

            <button type="submit" name="custom_login_submit">Login</button>

            <div class="info-text">
                By continuing, you agree to <a href="#">FLACOFY's Conditions of Use</a> and <a href="#">Privacy
                    Notice</a><br>
                Need help? <a href="#">Contact Support</a>
            </div>

            <?php if (get_option('users_can_register')): ?>
                <div class="info-text">
                    <a href="<?php echo home_url('/register/') ?>">Create your FLACOFY account</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="footer">
    <a href="#">Conditions of Use</a>
    <a href="#">Privacy Notice</a>
    <a href="#">Help</a><br>
    © 1996-2025, FLACOFY.com, Inc. or its affiliates
</div>

<script>
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    toggle.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('bi-eye-slash');
    });
</script>

</body>
</html>
