<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// Require a logged-in user.
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Please log in first."]);
    exit;
}

// Only allow profile viewing for now.
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once __DIR__ . "/../config/database.php";
    $conn->set_charset("utf8mb4");

    $user_id = $_SESSION["user_id"];

    $check = $conn->prepare(
        "SELECT user_id, name, email, role
         FROM users WHERE user_id = ?"
    );

    $check->bind_param("i", $user_id);
    $check->execute();

    $profile = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$profile) {
        http_response_code(404);
        echo json_encode(["error" => "Profile not found."]);
        exit;
    }

    echo json_encode(["profile" => $profile]);

} catch (mysqli_sql_exception $exception) {
    error_log("Profile API database error: " . $exception->getCode());

    http_response_code(500);
    echo json_encode(["error" => "Unable to load profile."]);
}