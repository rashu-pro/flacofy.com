<?php
function sendEmailOtp($email, $otp) {
    $subject = "Your Verification OTP";
    $message = "Your OTP code is: $otp\nValid for 2 minutes";
    
    $headers = [
        'From: flacofy0@gmail.com',
        'Content-Type: text/plain; charset=utf-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail(
        $email,
        $subject,
        $message,
        implode("\r\n", $headers),
        "-f flacofy0@gmail.com -au " . SMTP_USER . " -ap " . SMTP_PASS
    );
}
?>