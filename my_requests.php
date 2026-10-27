<?php
session_start();

require_once __DIR__ . "/includes/auth.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!canAccessRole($_SESSION, "tenant")) {
    http_response_code(403);
    exit("Access denied.");
}

$error = "";
$requests = [];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/config/database.php";

    $conn->set_charset("utf8mb4");

    // Only retrieve requests belonging to the logged-in tenant.
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
} catch (mysqli_sql_exception $exception) {
    error_log("Request history database error: " . $exception->getCode());

    http_response_code(500);
    $error = "Unable to load your requests. Please try again later.";
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Maintenance Requests</title>

    
<link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="request-list">
<?php require __DIR__ . "/includes/site_header.php"; ?>
<main id="main-content">
    <h1>My Maintenance Requests</h1>

    <nav aria-label="Request navigation">
        <a href="create_request.php">Create Maintenance Request</a>
        <a href="tenant_dashboard.php">Back to Dashboard</a>
    </nav>

    <?php if ($error !== ""): ?>
        <p role="alert"><?php echo escapeHtml($error); ?></p>

    <?php elseif (!$requests): ?>
        <p>You have not submitted any maintenance requests yet.</p>

    <?php else: ?>
        <?php foreach ($requests as $request): ?>
            <article>
                <h2><?php echo escapeHtml($request["title"]); ?></h2>

                <dl>
                    <dt>Request ID</dt>
                    <dd><?php echo (int) $request["request_id"]; ?></dd>

                    <dt>Description</dt>
                    <dd class="description"><?php
                        echo escapeHtml($request["description"]);
                    ?></dd>

                    <dt>Priority</dt>
                    <dd><span class="badge badge-<?php echo escapeHtml(strtolower(str_replace(" ", "-", $request["priority"]))); ?>"><?php echo escapeHtml($request["priority"]); ?></span></dd>

                    <dt>Status</dt>
                    <dd><span class="badge badge-<?php echo escapeHtml(strtolower(str_replace(" ", "-", $request["status"]))); ?>"><?php echo escapeHtml($request["status"]); ?></span></dd>

                    <dt>Submitted (UTC)</dt>
                    <dd><?php echo escapeHtml($request["date_submitted"]); ?></dd>

                    <dt>Last Updated (UTC)</dt>
                    <dd><?php echo escapeHtml($request["date_updated"]); ?></dd>
                </dl>
                <p>
                <a href="request_details.php?id=<?php echo (int) $request["request_id"]; ?>">
                     View Details and Comments
                    </a>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
</html>