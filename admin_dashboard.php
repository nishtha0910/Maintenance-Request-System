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

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        main {
            max-width: 950px;
            margin: 30px auto;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        a {
            color: #1d4ed8;
        }

        button {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            background: #1d4ed8;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        article {
            margin-bottom: 20px;
            padding: 24px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: white;
            overflow-wrap: anywhere;
        }

        h3 {
            margin-top: 0;
            font-size: 21px;
        }

        dt {
            margin-top: 12px;
            font-weight: bold;
        }

        dd {
            margin: 4px 0 0;
        }

        .description {
            white-space: pre-wrap;
        }

        [role="alert"] {
            color: #991b1b;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
    </style>
</head>
<body>
<main>
    <h1>Admin Dashboard</h1>

    <p>
        Welcome,
        <?php echo escapeHtml($_SESSION["name"] ?? "Administrator"); ?>!
    </p>

    <nav aria-label="Account navigation">
        <a href="profile.php">My Profile</a>

        <form action="logout.php" method="POST">
            <input
                type="hidden"
                name="token"
                value="<?php echo escapeHtml($_SESSION["logout_token"]); ?>"
            >
            <button type="submit">Logout</button>
        </form>
    </nav>

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
                    <dd><?php echo escapeHtml($request["priority"]); ?></dd>

                    <dt>Status</dt>
                    <dd><?php echo escapeHtml($request["status"]); ?></dd>

                    <dt>Submitted (UTC)</dt>
                    <dd><?php echo escapeHtml($request["date_submitted"]); ?></dd>

                    <dt>Last Updated (UTC)</dt>
                    <dd><?php echo escapeHtml($request["date_updated"]); ?></dd>
                </dl>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>