<?php
/**
 * Template Name: verify otp
 */
$otp_result = handle_otp_verification();

$cooldown_seconds = 120;
//if(isset($otp_result['cooldown'])) $cooldown_seconds = $otp_result['cooldown'];
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
            flex-direction: column;
            gap: 20px;
        }
        .top-logo {
            text-align: center;
            padding: 20px 0 10px;
        }

        .top-logo img {
            width: 50px;
            border-radius: 10px;
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
            let countdown = <?php echo $cooldown_seconds; ?>
            // let countdown = 20;
            console.log("countdown:", countdown);
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
<div class="top-logo">
    <a href="/"><img src="https://flacofy.com/wp-content/uploads/2025/01/logo.png" alt="Logo"></a>
</div>

<div class="otp-container">
    <h2 class="otp-title">OTP Verify</h2>
    <?php
    global $wp_rate_limiter;
    $rate_check = $wp_rate_limiter->is_rate_limited('otp_verification', 3, 60);
    if (!empty($otp_result['error'])) {
        $css_class = "alert alert-danger ";
        $css_class .= isset($otp_result['rate_limited']) ? 'rate-limit-blocked' : 'error-message';
        echo '<div class="' . $css_class . '">' . esc_html($otp_result['error']) . '</div>';
        // Handle error redirect
        if (isset($otp_result['redirect'])) {
            echo '<script>setTimeout(function(){ window.location.href = "' . $otp_result['redirect'] . '"; }, 2000);</script>';
        }
    }

    if (!empty($otp_result['success'])) {
        echo '<div class="alert alert-success success-message">' . esc_html($otp_result['success']) . '</div>';

        // Handle auto-login and redirect
        if (isset($otp_result['auto_login']) && $otp_result['auto_login']) {
            echo '<script>setTimeout(function(){ window.location.href = "' . $otp_result['redirect'] . '"; }, 2000);</script>';
        }
    }
    ?>

    <?php if (!isset($otp_result['auto_login'])): ?>
        <p class="otp-message">Please enter the 6-digit code sent to<br><strong><?= htmlspecialchars($otp_result['contact']) ?></strong></p>
    <?php
        if(isset($rate_check['remaining'])){
            ?>
            <p>Attempts remaining <?php echo $rate_check['remaining'] ?>/3</p>
    <?php
        }
        ?>
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
    <?php endif; ?>
</div>
</body>
</html>
