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

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    header("Allow: GET");
    respond(405, ["error" => "Method not allowed."]);
}

$status = $_GET["status"] ?? "";
if ($status !== "" && !in_array($status, ["Submitted", "In Progress", "Completed"], true)) {
    respond(400, ["error" => "Status must be Submitted, In Progress, or Completed."]);
}

$search = $_GET["search"] ?? "";
if (!is_string($search)) {
    respond(400, ["error" => "Search must be text."]);
}
$search = trim($search);

$sortOptions = [
    "newest" => "r.date_submitted DESC, r.request_id DESC",
    "oldest" => "r.date_submitted ASC, r.request_id ASC",
    "priority" => "FIELD(r.priority, 'High', 'Medium', 'Low'), r.date_submitted DESC, r.request_id DESC",
];
$sort = $_GET["sort"] ?? "newest";
if (!is_string($sort) || !isset($sortOptions[$sort])) {
    respond(400, ["error" => "Sort must be newest, oldest, or priority."]);
}
$orderBy = $sortOptions[$sort];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/../config/database.php";

    $conn->set_charset("utf8mb4");

    $conditions = [];
    $values = [];
    if ($status !== "") {
        $conditions[] = "r.status = ?";
        $values[] = $status;
    }
    if ($search !== "") {
        $conditions[] = "(LOCATE(?, r.title) > 0 OR LOCATE(?, r.description) > 0)";
        $values[] = $search;
        $values[] = $search;
    }
    $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
    $check = $conn->prepare(
        "SELECT r.request_id, r.user_id, r.title, r.description,
                r.priority, r.status,
                r.date_submitted, r.date_updated,
                u.name AS tenant_name
         FROM maintenance_requests AS r
         INNER JOIN users AS u ON u.user_id = r.user_id
         $where
         ORDER BY $orderBy"
    );

    $check->execute($values);
    $requests = $check->get_result()->fetch_all(MYSQLI_ASSOC);
    $check->close();

    foreach ($requests as &$request) {
        $request["request_id"] = (int) $request["request_id"];
        $request["user_id"] = (int) $request["user_id"];
    }
    unset($request);

    respond(200, [
        "requests" => $requests,
        "count" => count($requests)
    ]);
} catch (mysqli_sql_exception $exception) {
    error_log(
        "Admin request list API database error: "
        . $exception->getCode()
    );

    respond(500, [
        "error" => "Unable to load maintenance requests. Please try again later."
    ]);
}