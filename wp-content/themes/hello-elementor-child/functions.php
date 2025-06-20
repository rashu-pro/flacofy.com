<?php
// Load parent and child theme styles
function hello_elementor_child_enqueue_styles() {
    wp_enqueue_style('hello-elementor-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('hello-elementor-child-style', get_stylesheet_directory_uri() . '/style.css', array('hello-elementor-style'), wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles');

include_once ('registration-system/functions-registration-system.php');

function wp_unique_username($base) {
    $username = sanitize_user($base);
    $suffix = 1;
    while (username_exists($username)) {
        $username = sanitize_user($base . $suffix++);
    }
    return $username;
}

function wp_unique_email($base) {
    $email = sanitize_email($base);
    $suffix = 1;
    while (email_exists($email)) {
        $email = preg_replace('/@/', $suffix++ . '@', $base, 1);
    }
    return $email;
}
