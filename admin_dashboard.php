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

$success = isset($_SESSION["updated_request_id"]) ? "Request updated successfully." : "";
unset($_SESSION["updated_request_id"]);

$error = "";
$requests = [];
$notifications = null;
$statuses = ["Submitted", "In Progress", "Completed"];
$priorities = ["Low", "Medium", "High"];
// The database priority enum is ordered Low, Medium, High.
$sortOptions = [
    "newest" => ["Newest first", "r.date_submitted DESC, r.request_id DESC"],
    "oldest" => ["Oldest first", "r.date_submitted ASC, r.request_id ASC"],
    "highest" => ["Highest priority first", "r.priority DESC, r.date_submitted DESC, r.request_id DESC"],
    "lowest" => ["Lowest priority first", "r.priority ASC, r.date_submitted DESC, r.request_id DESC"],
];
$sort = $_GET["sort"] ?? "newest";
if (!is_string($sort) || !isset($sortOptions[$sort])) {
    $sort = "newest";
}
[$sortLabel, $orderBy] = $sortOptions[$sort];
$rawSearch = $_GET["q"] ?? "";
$search = is_string($rawSearch) ? trim($rawSearch) : "";
$priority = $_GET["priority"] ?? "";
if (!in_array($priority, $priorities, true)) {
    $priority = "";
}
$status = $_GET["status"] ?? "";
if (!in_array($status, $statuses, true)) {
    $status = "";
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/config/database.php";

    $conn->set_charset("utf8mb4");

    // Administrators can view requests from every tenant.
    $conditions = [];
    $values = [];
    if ($status !== "") {
        $conditions[] = "r.status = ?";
        $values[] = $status;
    }
    if ($priority !== "") {
        $conditions[] = "r.priority = ?";
        $values[] = $priority;
    }
    if ($search !== "") {
        $conditions[] = "(LOCATE(?, r.title) > 0 OR LOCATE(?, r.description) > 0)";
        $values[] = $search;
        $values[] = $search;
    }
    $where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
    $perPage = 10;
    $count = $conn->prepare("SELECT COUNT(*) FROM maintenance_requests AS r $where");
    $count->execute($values);
    $total = (int) $count->get_result()->fetch_row()[0];
    $count->close();

    $pages = max(1, (int) ceil($total / $perPage));
    $requestedPage = filter_var($_GET["page"] ?? 1, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $page = min($requestedPage ?: 1, $pages);
    $offset = ($page - 1) * $perPage;
    $filters = ["q" => $search, "status" => $status, "priority" => $priority, "sort" => $sort];
    $check = $conn->prepare(
        "SELECT r.request_id, r.title, r.description,
                r.priority, r.status,
                r.date_submitted, r.date_updated,
                u.name AS tenant_name
         FROM maintenance_requests AS r
         INNER JOIN users AS u ON u.user_id = r.user_id
         $where
         ORDER BY $orderBy LIMIT $perPage OFFSET $offset"
    );

    $check->execute($values);
    $requests = $check->get_result()->fetch_all(MYSQLI_ASSOC);
    $check->close();

    try {
        $result = $conn->query(
            "SELECT n.request_id, r.title, u.name AS tenant_name
             FROM admin_request_notifications AS n
             JOIN maintenance_requests AS r ON r.request_id = n.request_id
             JOIN users AS u ON u.user_id = r.user_id
             ORDER BY n.created_at DESC, n.request_id DESC
             LIMIT 5"
        );
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    } catch (mysqli_sql_exception $exception) {
        error_log("Admin notification query failed: " . $exception->getCode());
    }
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
    <p>Manage all maintenance requests.</p>
    <?php if ($success !== ""): ?>
        <p role="status"><?php echo escapeHtml($success); ?></p>
    <?php endif; ?>
    <?php if ($error === ""): ?>
        <section aria-labelledby="recent-notifications">
            <h2 id="recent-notifications">Recent request notifications</h2>
            <?php if ($notifications === null): ?>
                <p>Notifications are temporarily unavailable.</p>
            <?php elseif (!$notifications): ?>
                <p>No new request notifications yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($notifications as $notice): ?>
                        <li>
                            <?= escapeHtml($notice["tenant_name"]) ?> submitted
                            <a href="admin_request.php?id=<?= (int) $notice["request_id"] ?>">#<?= (int) $notice["request_id"] ?>: <?= escapeHtml($notice["title"]) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <form class="admin-request-filters" method="get" action="admin_dashboard.php" aria-label="Filter maintenance requests">
            <div class="request-search">
                <label for="request-search">Search:</label>
                <input type="search" id="request-search" name="q" placeholder="Title or description" value="<?= escapeHtml($search) ?>">
                <button type="submit">Search</button>
            </div>
            <div class="request-filter-options">
                <div>
                    <label for="status-filter">Status:</label>
                    <select id="status-filter" name="status" onchange="this.form.requestSubmit()">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $option): ?>
                            <option value="<?= escapeHtml($option) ?>" <?= $status === $option ? "selected" : "" ?>><?= escapeHtml($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="priority-filter">Priority:</label>
                    <select id="priority-filter" name="priority" onchange="this.form.requestSubmit()">
                        <option value="">All priorities</option>
                        <?php foreach ($priorities as $option): ?>
                            <option value="<?= escapeHtml($option) ?>" <?= $priority === $option ? "selected" : "" ?>><?= escapeHtml($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="request-sort">Sort:</label>
                    <select id="request-sort" name="sort" onchange="this.form.requestSubmit()">
                        <?php foreach ($sortOptions as $key => $option): ?>
                            <option value="<?= escapeHtml($key) ?>" <?= $sort === $key ? "selected" : "" ?>><?= escapeHtml($option[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <p role="alert"><?php echo escapeHtml($error); ?></p>
    <?php elseif (!$requests): ?>
        <div class="empty-state"><?= $search === "" && $status === "" && $priority === "" ? "No maintenance requests have been submitted yet." : "No requests match your search or filters." ?></div>
    <?php else: ?>
        <div class="table-panel">
            <table class="admin-table">
                <caption>Maintenance Requests: <?= escapeHtml($sortLabel) ?></caption>
                <thead><tr>
                    <th scope="col">Title</th>
                    <th scope="col">Status</th>
                    <th scope="col">Priority</th>
                    <th scope="col">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td>
                            <strong><?php echo escapeHtml($request['title']); ?></strong>
                            <small class="request-owner">#<?php echo (int) $request['request_id']; ?> &middot; <?php echo escapeHtml($request['tenant_name']); ?></small>
                        </td>
                        <td><span class="request-card-label" aria-hidden="true">Status</span><span class="badge badge-<?php echo escapeHtml(strtolower(str_replace(' ', '-', $request['status']))); ?>"><?php echo escapeHtml($request['status']); ?></span></td>
                        <td><span class="request-card-label" aria-hidden="true">Priority</span><span class="badge badge-<?php echo escapeHtml(strtolower($request['priority'])); ?>"><?php echo escapeHtml($request['priority']); ?></span></td>
                        <td><div class="table-actions">
                            <a class="button button-outline" href="admin_request.php?id=<?php echo (int) $request['request_id']; ?>#request-heading" aria-label="View request <?php echo (int) $request['request_id']; ?>">View</a>
                            <a class="button" href="admin_request.php?id=<?php echo (int) $request['request_id']; ?>&amp;mode=edit#update-request" aria-label="Edit request <?php echo (int) $request['request_id']; ?>">Edit</a>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav aria-label="Request list pages">
                <?php if ($page > 1): ?>
                    <a rel="prev" href="?<?= escapeHtml(http_build_query($filters + ["page" => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a rel="next" href="?<?= escapeHtml(http_build_query($filters + ["page" => $page + 1])) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
</html>