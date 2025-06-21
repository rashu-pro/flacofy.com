<?php
/**
 * Template Name: My Account
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

get_header();

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

$current_user = wp_get_current_user();
?>

<div class="my-account-page" style="max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
    <h2>My Account</h2>

    <p><strong>Name:</strong> <?php echo esc_html($current_user->display_name); ?></p>
    <p><strong>Username:</strong> <?php echo esc_html($current_user->user_login); ?></p>
    <?php
    $email = $current_user->user_email;
    if (strpos($email, 'phone_') === 0) {
        $phone_meta = get_user_meta($current_user->ID, 'phone', true);
        $display_phone = $phone_meta ? $phone_meta : 'Not set';
        echo '<p><strong>Phone:</strong> ' . esc_html($display_phone) . '</p>';
    } else {
        echo '<p><strong>Email:</strong> ' . esc_html($email) . '</p>';
    }
    ?>

    <?php
    // If you're storing phone number as user meta, e.g. `phone_number`
    $phone = get_user_meta($current_user->ID, 'phone_number', true);
    if ($phone) {
        echo '<p><strong>Phone:</strong> ' . esc_html($phone) . '</p>';
    }
    ?>

    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="logout-button" style="display:inline-block; padding:10px 20px; background:#c00; color:#fff; border-radius:5px; text-decoration:none;">Logout</a>
</div>

<?php get_footer(); ?>
