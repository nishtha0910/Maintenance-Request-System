<?php
function submitMaintenanceRequest(mysqli $conn, int $userId, string $title, string $description, string $priority): int
{
    $conn->begin_transaction();
    try {
        $insert = $conn->prepare(
            "INSERT INTO maintenance_requests
             (user_id, title, description, priority, status, date_submitted, date_updated)
             VALUES (?, ?, ?, ?, 'Submitted', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $insert->bind_param("isss", $userId, $title, $description, $priority);
        $insert->execute();
        $requestId = (int) $insert->insert_id;
        $insert->close();

        $notify = $conn->prepare("INSERT INTO admin_request_notifications (request_id) VALUES (?)");
        $notify->bind_param("i", $requestId);
        $notify->execute();
        $notify->close();

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    // The request and dashboard notification are already committed.
    try {
        require_once __DIR__ . "/request_email_notifications.php";
        emailAdminsAboutNewRequest($conn, $requestId, $title, $priority);
    } catch (Throwable $exception) {
        error_log("Could not prepare admin notification emails for request #" . $requestId);
    }

    return $requestId;
}
