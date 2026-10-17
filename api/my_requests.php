<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../includes/auth.php";

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    respond(401, ["error" => "Please log in first."]);
}

if (!canAccessRole($_SESSION, "tenant")) {
    respond(403, ["error" => "Access denied."]);
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    respond(405, ["error" => "Method not allowed."]);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $conn->set_charset("utf8mb4");

    // Always use the logged-in tenant's ID.
    $userId = (int) $_SESSION["user_id"];

    $check = $conn->prepare(
        "SELECT request_id, title, description, priority, status,
                date_submitted, date_updated
         FROM maintenance_requests
         WHERE user_id = ?
         ORDER BY date_submitted DESC, request_id DESC"
    );

    $check->bind_param("i", $userId);
    $check->execute();

    $requests = $check->get_result()->fetch_all(MYSQLI_ASSOC);
    $check->close();

    foreach ($requests as &$request) {
        $request["request_id"] = (int) $request["request_id"];
    }
    unset($request);

    respond(200, [
        "requests" => $requests,
        "count" => count($requests)
    ]);
} catch (mysqli_sql_exception $exception) {
    error_log(
        "Request history API database error: " . $exception->getCode()
    );

    respond(500, [
        "error" => "Unable to load your requests. Please try again later."
    ]);
}