<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../includes/login_security.php";

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

if (array_diff(array_keys($fields), ["email", "password"])) {
    respond(400, ["error" => "Only email and password are accepted."]);
}

$email = $fields["email"] ?? null;
$password = $fields["password"] ?? null;

if (!is_string($email) || !is_string($password)) {
    respond(422, ["error" => "Email and password must be strings."]);
}

$email = trim($email);

if (
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($email) > 150 ||
    $password === ""
) {
    respond(422, ["error" => "Enter a valid email and a password."]);
}

// Clear any earlier authentication before attempting a new login.
$_SESSION = [];

$transactionStarted = false;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();
    $transactionStarted = true;

    $check = $conn->prepare(
        "SELECT user_id, name, email, password, role, locked_until,
                (locked_until > UTC_TIMESTAMP()) AS is_locked
         FROM users
         WHERE email = ?
         FOR UPDATE"
    );
    $check->bind_param("s", $email);
    $check->execute();
    $user = $check->get_result()->fetch_assoc();
    $check->close();

    $authenticated = false;
    $status = 401;
    $error = "Invalid email or password.";

    if ($user && (int) $user["is_locked"] === 1) {
        $status = 429;
        $error = "Account temporarily locked. Please try again later.";
    } elseif ($user) {
        $userId = (int) $user["user_id"];

        // Start fresh after a previous lock expires.
        if ($user["locked_until"] !== null) {
            $clear = $conn->prepare(
                "DELETE FROM login_attempts WHERE user_id = ?"
            );
            $clear->bind_param("i", $userId);
            $clear->execute();
            $clear->close();

            $unlock = $conn->prepare(
                "UPDATE users SET locked_until = NULL WHERE user_id = ?"
            );
            $unlock->bind_param("i", $userId);
            $unlock->execute();
            $unlock->close();
        }

        if (password_verify($password, $user["password"])) {
            $clear = $conn->prepare(
                "DELETE FROM login_attempts WHERE user_id = ?"
            );
            $clear->bind_param("i", $userId);
            $clear->execute();
            $clear->close();

            $authenticated = true;
        } else {
            $cleanup = $conn->prepare(
                "DELETE FROM login_attempts
                 WHERE user_id = ?
                 AND attempted_at <=
                     DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)"
            );
            $cleanup->bind_param("i", $userId);
            $cleanup->execute();
            $cleanup->close();

            $record = $conn->prepare(
                "INSERT INTO login_attempts (user_id, attempted_at)
                 VALUES (?, UTC_TIMESTAMP())"
            );
            $record->bind_param("i", $userId);
            $record->execute();
            $record->close();

            $attempts = $conn->prepare(
                "SELECT attempted_at, UTC_TIMESTAMP() AS checked_at
                 FROM login_attempts WHERE user_id = ?"
            );
            $attempts->bind_param("i", $userId);
            $attempts->execute();
            $attemptRows = $attempts->get_result()->fetch_all(MYSQLI_ASSOC);
            $attempts->close();

            $failedAttemptTimes = [];

            foreach ($attemptRows as $attemptRow) {
                $failedAttemptTimes[] = strtotime(
                    $attemptRow["attempted_at"] . " UTC"
                );
            }

            $checkedAt = strtotime(
                $attemptRows[0]["checked_at"] . " UTC"
            );

            if (shouldLockAccount($failedAttemptTimes, $checkedAt)) {
                $lock = $conn->prepare(
                    "UPDATE users
                     SET locked_until =
                         DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
                     WHERE user_id = ?"
                );
                $lock->bind_param("i", $userId);
                $lock->execute();
                $lock->close();

                $status = 429;
                $error = "Account locked for 10 minutes after 5 failed login attempts.";
            }
        }
    }

    // Commit failures too, so attempts and account locks are saved.
    $conn->commit();
    $transactionStarted = false;

    if (!$authenticated) {
        respond($status, ["error" => $error]);
    }

    if (!in_array($user["role"], ["admin", "tenant"], true)) {
        respond(403, ["error" => "Access denied. Invalid user role."]);
    }

    session_regenerate_id(true);

    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["name"] = $user["name"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["profile_token"] = bin2hex(random_bytes(32));

    respond(200, [
        "message" => "Login successful.",
        "user" => [
            "user_id" => (int) $user["user_id"],
            "name" => $user["name"],
            "email" => $user["email"],
            "role" => $user["role"]
        ],
        "csrf_token" => $_SESSION["profile_token"]
    ]);
} catch (mysqli_sql_exception $exception) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    error_log("Login API database error: " . $exception->getCode());

    respond(500, ["error" => "Unable to process your login. Please try again later."]);
}