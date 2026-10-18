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

if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    header("Allow: PUT");
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

$contentType = strtolower(
    trim(explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0])
);

if ($contentType !== "application/json") {
    respond(415, ["error" => "Use application/json for the request body."]);
}

try {
    $data = json_decode(
        file_get_contents("php://input"),
        false,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $exception) {
    respond(400, ["error" => "Invalid JSON."]);
}

if (!$data instanceof stdClass) {
    respond(400, ["error" => "The request body must be a JSON object."]);
}

$fields = get_object_vars($data);

if (array_diff(array_keys($fields), ["status", "priority"])) {
    respond(400, ["error" => "Only status and priority are accepted."]);
}

$status = $fields["status"] ?? "";
$priority = $fields["priority"] ?? "";

if (
    !is_string($status) ||
    !in_array($status, ["Submitted", "In Progress", "Completed"], true) ||
    !is_string($priority) ||
    !in_array($priority, ["Low", "Medium", "High"], true)
) {
    respond(422, [
        "error" => "Provide a valid status and priority."
    ]);
}

$transactionStarted = false;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();
    $transactionStarted = true;

    // Lock the request while checking and updating it.
    $check = $conn->prepare(
        "SELECT request_id
         FROM maintenance_requests
         WHERE request_id = ?
         FOR UPDATE"
    );
    $check->bind_param("i", $requestId);
    $check->execute();
    $existingRequest = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$existingRequest) {
        $conn->rollback();
        $transactionStarted = false;

        respond(404, ["error" => "Request not found."]);
    }
     // Completed requests can also be updated by administrators.
    $update = $conn->prepare(
        "UPDATE maintenance_requests
         SET status = ?, priority = ?, date_updated = UTC_TIMESTAMP()
         WHERE request_id = ?"
    );
    $update->bind_param("ssi", $status, $priority, $requestId);
    $update->execute();
    $update->close();

    $read = $conn->prepare(
        "SELECT request_id, title, description, priority, status,
                date_submitted, date_updated
         FROM maintenance_requests
         WHERE request_id = ?"
    );
    $read->bind_param("i", $requestId);
    $read->execute();
    $request = $read->get_result()->fetch_assoc();
    $read->close();

    $conn->commit();
    $transactionStarted = false;

    $request["request_id"] = (int) $request["request_id"];

    respond(200, [
        "message" => "Request updated successfully.",
        "request" => $request
    ]);
} catch (mysqli_sql_exception $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    error_log(
        "Admin request update API database error: "
        . $exception->getCode()
    );

    respond(500, [
        "error" => "Unable to update the request. Please try again later."
    ]);
}