<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../includes/auth.php";

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    respond(405, ["error" => "Method not allowed."]);
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

if (array_diff(array_keys($fields), ["name", "email", "password"])) {
    respond(400, ["error" => "Only name, email, and password are accepted."]);
}

$name = $fields["name"] ?? null;
$email = $fields["email"] ?? null;
$password = $fields["password"] ?? null;

if (
    !is_string($name) ||
    !is_string($email) ||
    !is_string($password)
) {
    respond(422, ["error" => "Name, email, and password must be strings."]);
}

$name = trim($name);
$email = trim($email);

if ($name === "" || mb_strlen($name) > 100) {
    respond(422, ["error" => "Enter a name of 1–100 characters."]);
}

if (
    $email === "" ||
    mb_strlen($email) > 150 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond(422, ["error" => "Enter a valid email of at most 150 characters."]);
}

if (
    $password === "" ||
    strlen($password) > 72 ||
    str_contains($password, "\0")
) {
    respond(422, ["error" => "Enter a non-empty password of at most 72 bytes without null characters."]);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $conn->set_charset("utf8mb4");

    $hashedPassword = hashUserPassword($password);
    $role = "tenant";

    // The unique email constraint prevents duplicate accounts.
    $insert = $conn->prepare(
        "INSERT INTO users (name, email, password, role)
         VALUES (?, ?, ?, ?)"
    );
    $insert->bind_param(
        "ssss",
        $name,
        $email,
        $hashedPassword,
        $role
    );
    $insert->execute();

    $userId = $conn->insert_id;
    $insert->close();

    respond(201, [
        "message" => "Account created successfully. You can now log in.",
        "user" => [
            "user_id" => $userId,
            "name" => $name,
            "email" => $email,
            "role" => $role
        ]
    ]);
} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() === 1062) {
        respond(409, ["error" => "This email is already registered."]);
    }

    error_log("Registration API database error: " . $exception->getCode());

    respond(500, ["error" => "Unable to create your account. Please try again later."]);
}