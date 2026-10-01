<?php

//Run this test from terminal only.

if(PHP_SAPI !== "cli"){
    http_response_code(404);
    exit;
}

require_once __DIR__ . "/../includes/mailer.php";
$settings = require __DIR__ . "/../config/mail.local.php";

 try{
    sendEmail(
        $settings["email"],
        "Maintenance system email test",
        "your gmail email configuration is working."
    );
    echo "Email sent successfully." . PHP_EOL;
    }catch(\PHPMailer\PHPMailer\Exception $exception){
        echo "Email sending failed: " . $exception->getMessage() . PHP_EOL;
    }