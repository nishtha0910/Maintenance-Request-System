<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/request_validation.php"; 

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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    respond(405, ["error" => "Method not allowed."]);
}

// Use the token returned by API login or GET api/profile.php.
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

if (
    array_diff(
        array_keys($fields),
        ["title", "description", "priority"]
    )
) {
    respond(400, [
        "error" => "Only title, description, and priority are accepted."
    ]);
}

$title = $fields["title"] ?? "";
$description = $fields["description"] ?? "";
$priority = $fields["priority"] ?? "";

$errors = validateMaintenanceRequest($title, $description, $priority);

if ($errors) {
    respond(422, [
        "error" => "Please correct the request details.",
        "errors" => $errors
    ]);
}

$title = trim($title);
$description = trim($description);

// Never accept ownership or status from the request body.
$userId = (int) $_SESSION["user_id"];
$status = "Submitted";

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $conn->set_charset("utf8mb4");

    $insert = $conn->prepare(
        "INSERT INTO maintenance_requests
         (user_id, title, description, priority, status,
          date_submitted, date_updated)
         VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    );

    $insert->bind_param(
        "issss",
        $userId,
        $title,
        $description,
        $priority,
        $status
    );

    $insert->execute();
    $requestId = (int) $conn->insert_id;
    $insert->close();

    respond(201, [
        "message" => "Maintenance request submitted successfully.",
        "request" => [
            "request_id" => $requestId,
            "title" => $title,
            "description" => $description,
            "priority" => $priority,
            "status" => $status
        ]
    ]);
} catch (mysqli_sql_exception $exception) {
    error_log(
        "Request creation API database error: " . $exception->getCode()
    );

    respond(500, [
        "error" => "Unable to submit your request. Please try again later."
    ]);
}

