<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../includes/mailer.php";

function sendEmail(
    string $recipient,
    string $subject,
    string $message
): void{
    $settings = require __DIR__ . "/../config/mail.local.php";
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    $mail->Username = $settings["email"];
    $mail->Password = $settings["app_password"];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = "UTF-8";
    $mail->setFrom($settings["email"], "Maintenance Request System");
    $mail->addAddress($recipient);
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->send();
}
