<?php
session_start();

$host = 'localhost';
$dbname = 'flacofy.com';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// ✅ Set MySQL session timezone to Asia/Dhaka
$conn->query("SET time_zone = '+06:00'");

// ✅ Set PHP timezone to Asia/Dhaka
date_default_timezone_set('Asia/Dhaka');

// 📧 Email Configuration
define('SMTP_USER', 'flacofy0@gmail.com');
define('SMTP_PASS', 'wdth zexq eymz tuvn');
?>
