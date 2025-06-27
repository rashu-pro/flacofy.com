<?php

/**
 * DISPLAY RATE LIMIT INFORMATION
 */
function display_rate_limit_info($action_type) {
    global $wp_rate_limiter;

    $rate_check = $wp_rate_limiter->is_rate_limited($action_type, 5, 900);

    if (!$rate_check['blocked'] && $rate_check['attempts'] > 0) {
        echo '<div class="rate-limit-info">';
        echo 'Attempts remaining: ' . $rate_check['remaining'] . '/5';
        echo '</div>';
    }
}

/**
 * CSS for rate limit messages
 */
function rate_limit_styles() {
    ?>
    <style>
        .rate-limit-info {
            background-color: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .rate-limit-blocked {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
    <?php
}
add_action('wp_head', 'rate_limit_styles');
