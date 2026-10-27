<?php
session_start();

require_once __DIR__ . "/includes/auth.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!canAccessRole($_SESSION, "admin")) {
    http_response_code(403);
    exit("Access denied.");
}

if (empty($_SESSION["logout_token"])) {
    $_SESSION["logout_token"] = bin2hex(random_bytes(32));
}

$error = "";
$requests = [];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/config/database.php";

    $conn->set_charset("utf8mb4");

    // Administrators can view requests from every tenant.
    $check = $conn->prepare(
        "SELECT r.request_id, r.title, r.description,
                r.priority, r.status,
                r.date_submitted, r.date_updated,
                u.name AS tenant_name
         FROM maintenance_requests AS r
         INNER JOIN users AS u ON u.user_id = r.user_id
         ORDER BY r.date_submitted DESC, r.request_id DESC"
    );

    $check->execute();
    $requests = $check->get_result()->fetch_all(MYSQLI_ASSOC);
    $check->close();
} catch (mysqli_sql_exception $exception) {
    error_log("Admin dashboard database error: " . $exception->getCode());

    http_response_code(500);
    $error = "Unable to load maintenance requests. Please try again later.";
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
    <title>Admin Dashboard - Maintenance Request System</title>

    
<link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="request-list">
<?php require __DIR__ . "/includes/site_header.php"; ?>
<main id="main-content">
    <h1>Admin Dashboard</h1>

    <p>
        Welcome,
        <?php echo escapeHtml($_SESSION["name"] ?? "Administrator"); ?>!
    </p>

    

    <h2>All Maintenance Requests</h2>

    <?php if ($error !== ""): ?>
        <p role="alert"><?php echo escapeHtml($error); ?></p>

    <?php elseif (!$requests): ?>
        <p>No maintenance requests have been submitted yet.</p>

    <?php else: ?>
        <?php foreach ($requests as $request): ?>
            <article>
                <h3><?php echo escapeHtml($request["title"]); ?></h3>

                <dl>
                    <dt>Request ID</dt>
                    <dd><?php echo (int) $request["request_id"]; ?></dd>

                    <dt>Submitted By</dt>
                    <dd><?php echo escapeHtml($request["tenant_name"]); ?></dd>

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
    <a href="admin_request.php?id=<?php echo (int) $request["request_id"]; ?>">
        Manage Request
    </a>
</p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
</html>