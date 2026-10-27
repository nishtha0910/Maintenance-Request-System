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

if (empty($_SESSION["logout_token"])) {
    $_SESSION["logout_token"] = bin2hex(random_bytes(32));
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
        <title>Tenant Dashboard- Maintenance Request System</title>
    <link rel="stylesheet" href="assets/css/app.css?v=1">
</head>

    <body class="request-list">
<?php require __DIR__ . "/includes/site_header.php"; ?>
        <main id="main-content">
<div class="dashboard-intro"><p class="eyebrow">Your resident workspace</p><h1>Tenant Dashboard</h1>
<p>Welcome, <?php echo escapeHtml($_SESSION['name'] ?? 'Resident'); ?>. Let's keep your home at its best.</p></div>
<div class="page-heading"><h2>Maintenance Requests</h2><a class="button" href="create_request.php">+ Create New Request</a></div>
<?php if ($error !== ''): ?><p role="alert"><?php echo escapeHtml($error); ?></p>
<?php elseif (!$requests): ?><div class="empty-state"><h3>A fresh start.</h3><p>No maintenance requests yet. When something needs attention, create your first request.</p></div>
<?php else: ?><div class="table-panel"><table><caption>Your requests, most recent first</caption><thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Priority</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?><tr><td><a href="request_details.php?id=<?php echo (int) $request['request_id']; ?>"><?php echo escapeHtml($request['title']); ?></a></td>
<td><span class="badge badge-<?php echo escapeHtml(strtolower(str_replace(' ', '-', $request['status']))); ?>"><?php echo escapeHtml($request['status']); ?></span></td>
<td><span class="badge badge-<?php echo escapeHtml(strtolower($request['priority'])); ?>"><?php echo escapeHtml($request['priority']); ?></span></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<p style="margin-top:24px"><a href="my_requests.php">View request history and details &rarr;</a></p>
</main>
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>

</html>