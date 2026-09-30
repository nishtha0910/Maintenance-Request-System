<?php
session_start();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Please log in first."]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method !== "GET" && $method !== "PUT") {
    http_response_code(405);
    header("Allow: GET, PUT");
    echo json_encode(["error" => "Method not allowed."]);
    exit;
}

// Used to protect profile updates.
if (empty($_SESSION["profile_token"])) {
    $_SESSION["profile_token"] = bin2hex(random_bytes(32));
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

    if ($method === "GET") {
        echo json_encode([
            "profile" => $profile,
            "csrf_token" => $_SESSION["profile_token"]
        ]);
        exit;
    }

    // Everything below handles PUT requests.
    $token = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";

    if (!hash_equals($_SESSION["profile_token"], $token)) {
        http_response_code(403);
        echo json_encode(["error" => "Invalid security token."]);
        exit;
    }

    $contentType = strtolower(trim(
        explode(";", $_SERVER["CONTENT_TYPE"] ?? "")[0]
    ));

    if ($contentType !== "application/json") {
        http_response_code(415);
        echo json_encode(["error" => "Send the body as application/json."]);
        exit;
    }

    // PHP does not put JSON PUT data into $_POST.
    $data = json_decode(file_get_contents("php://input"));

    if (
        !is_object($data) ||
        !isset($data->name, $data->email) ||
        !is_string($data->name) ||
        !is_string($data->email)
    ) {
        http_response_code(400);
        echo json_encode(["error" => "Send a JSON object with name and email."]);
        exit;
    }

    // Reject fields this endpoint cannot change.
    if (array_diff(array_keys(get_object_vars($data)), ["name", "email"])) {
        http_response_code(400);
        echo json_encode(["error" => "Only name and email can be updated."]);
        exit;
    }

    $name = trim($data->name);
    $email = trim($data->email);

    if (
        $name === "" ||
        $email === "" ||
        mb_strlen($name) > 100 ||
        mb_strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        http_response_code(422);
        echo json_encode([
            "error" => "Enter a name of 1–100 characters and a valid email of at most 150 characters."
        ]);
        exit;
    }

    $duplicate = $conn->prepare(
        "SELECT user_id FROM users WHERE email = ? AND user_id <> ?"
    );
    $duplicate->bind_param("si", $email, $user_id);
    $duplicate->execute();
    $duplicate->store_result();

    $emailTaken = $duplicate->num_rows > 0;
    $duplicate->close();

    if ($emailTaken) {
        http_response_code(409);
        echo json_encode(["error" => "This email is already registered."]);
        exit;
    }

    $update = $conn->prepare(
        "UPDATE users SET name = ?, email = ? WHERE user_id = ?"
    );
    $update->bind_param("ssi", $name, $email, $user_id);
    $update->execute();
    $update->close();

    $_SESSION["name"] = $name;

    $profile["name"] = $name;
    $profile["email"] = $email;

    echo json_encode([
        "message" => "Profile updated successfully.",
        "profile" => $profile
    ]);

} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() === 1062) {
        http_response_code(409);
        echo json_encode(["error" => "This email is already registered."]);
    } else {
        error_log("Profile API database error: " . $exception->getCode());

        http_response_code(500);
        echo json_encode(["error" => "Unable to process profile."]);
    }
}