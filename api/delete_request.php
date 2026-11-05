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

if (!canAccessRole($_SESSION, "admin")) {
    respond(403, ["error" => "Access denied."]);
}

if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    header("Allow: DELETE");
    respond(405, ["error" => "Method not allowed."]);
}

$sessionToken = $_SESSION["profile_token"] ?? "";
$submittedToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

if (
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !is_string($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    respond(403, ["error" => "Invalid security token."]);
}

$rawId = $_GET["id"] ?? "";

$requestId = is_string($rawId)
    ? filter_var(
        $rawId,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    )
    : false;

if ($requestId === false) {
    respond(400, ["error" => "Invalid request ID."]);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $delete = $conn->prepare("DELETE FROM maintenance_requests WHERE request_id = ?");
    $delete->bind_param("i", $requestId);
    $delete->execute();
    $deleted = $delete->affected_rows;
    $delete->close();

    if ($deleted === 0) {
        respond(404, ["error" => "Request not found."]);
    }

    respond(200, ["message" => "Request deleted successfully.", "request_id" => $requestId]);
} catch (mysqli_sql_exception $exception) {
    error_log("Admin request deletion error: " . $exception->getCode());
    respond(500, ["error" => "Unable to delete the request. Please try again later."]);
}
