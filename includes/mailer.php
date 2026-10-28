<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . "/../vendor/autoload.php";


function sendEmail(
    string $recipient,
    string $subject,
    string $message
): void{
    if (getenv('VERCEL') || getenv('APP_ENV') === 'production') {
        $settings = ['email' => getenv('SMTP_USER'), 'app_password' => getenv('SMTP_PASSWORD')];
        if (!$settings['email'] || !$settings['app_password']) {
            throw new RuntimeException('Email configuration is missing.');
        }
    } else {
        $settings = require __DIR__ . "/../config/mail.local.php";
    }
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv("SMTP_HOST") ?: "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $settings["email"];
    $mail->Password = $settings["app_password"];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = "UTF-8";
    $mail->setFrom($settings["email"], "Maintenance Request System");
    $mail->addAddress($recipient);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->send();
}
