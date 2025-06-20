<?php
function sendSmsOtp($phone, $otp) {
    $url = "http://portal.khudebarta.com:3775/sendtext";
    $data = [
        "apikey" => "299901d82f606e24",
        "secretkey" => "3769af17",
        "callerID" => "FLACOFY",
        "toUser" => "+88" . $phone,
        "messageContent" => "Your OTP code is: $otp (Valid for 2 minutes)"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}
?>