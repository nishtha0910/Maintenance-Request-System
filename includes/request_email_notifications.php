<?php

require_once __DIR__ . "/mailer.php";

function emailAdminsAboutNewRequest(mysqli $conn, int $requestId, string $title, string $priority, ?callable $send = null): void
{
    $send ??= "sendEmail";
    $result = $conn->query("SELECT user_id, email FROM users WHERE role = 'admin'");
    $subject = "New maintenance request #" . $requestId;
    $message = "<p>A tenant submitted maintenance request #" . $requestId . ".</p>"
        . "<p>Title: " . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")
        . "<br>Priority: " . htmlspecialchars($priority, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</p>"
        . "<p>Open your admin dashboard to view the request.</p>";

    while ($admin = $result->fetch_assoc()) {
        try {
            $send($admin["email"], $subject, $message);
        } catch (Throwable $exception) {
            error_log("Notification email failed for admin #" . (int) $admin["user_id"]
                . " on request #" . $requestId);
        }
    }
    $result->free();
}
