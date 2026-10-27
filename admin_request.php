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

$rawId = $_GET["id"] ?? "";

$requestId = is_string($rawId)
    ? filter_var(
        $rawId,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    )
    : false;
    if ($requestId === false) {
    http_response_code(400);
    exit("Invalid request ID.");
    }

    if (empty($_SESSION["admin_request_token"])) {
        $_SESSION["admin_request_token"] = bin2hex(random_bytes(32));
    }

    $allowedStatuses = ["Submitted", "In Progress", "Completed"];
    $allowedPriorities = ["Low", "Medium", "High"];

    $error = "";
    $success = "";
    $request = null;
    $comments = [];

    if(($_SESSION["updated_request_id"] ?? null) === $requestId) {
    $success = "Request updated successfully.";
    unset($_SESSION["updated_request_id"]);
     }
    
     try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require_once __DIR__ . "/config/database.php";
        $conn->set_charset("utf8mb4");

        $check= $conn->prepare(
              "SELECT r.request_id, r.title, r.description,
                r.priority, r.status, r.date_submitted, r.date_updated,
                u.name AS tenant_name
         FROM maintenance_requests AS r
         INNER JOIN users AS u ON u.user_id = r.user_id
         WHERE r.request_id = ?"
    );

    $check->bind_param("i", $requestId);
    $check->execute();
    $request = $check->get_result()->fetch_assoc();
    $check->close();


    if (!$request) {
        http_response_code(404);
        $error = "Request not found.";
    } else {
        $selectedStatus = $request["status"];
        $selectedPriority = $request["priority"];

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $token = $_POST["token"] ?? "";
            $status = $_POST["status"] ?? "";
            $priority = $_POST["priority"] ?? "";

            if (
                !is_string($token) ||
                !hash_equals($_SESSION["admin_request_token"], $token)
            ) {
                http_response_code(403);
                $error = "Invalid security token. Refresh the page and try again.";
            } elseif (
                !is_string($status) ||
                !in_array($status, $allowedStatuses, true) ||
                !is_string($priority) ||
                !in_array($priority, $allowedPriorities, true)
            ) {
                http_response_code(422);
                $error = "Select a valid status and priority.";
            } else {
                $update = $conn->prepare(
                    "UPDATE maintenance_requests
                     SET status = ?, priority = ?, date_updated = UTC_TIMESTAMP()
                     WHERE request_id = ?"
                );
                $update->bind_param("ssi", $status, $priority, $requestId);
                $update->execute();
                $update->close();

                $_SESSION["updated_request_id"] = $requestId;

                header(
                    "Location: admin_request.php?id=" . $requestId,
                    true,
                    303
                );
                exit;
            }
        }

         $list = $conn->prepare(
            "SELECT c.comment_text, c.date_created, u.name
             FROM comments AS c
             INNER JOIN users AS u ON u.user_id = c.user_id
             WHERE c.request_id = ?
             ORDER BY c.date_created ASC, c.comment_id ASC"
        );
        $list->bind_param("i", $requestId);
        $list->execute();
        $comments = $list->get_result()->fetch_all(MYSQLI_ASSOC);
        $list->close();
    }
} catch (mysqli_sql_exception $exception) {
    error_log("Admin request database error: " . $exception->getCode());

    http_response_code(500);
    $error = "Unable to load or update the request. Please try again later.";
    $request = null;
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
    <title>Manage Maintenance Request</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        main {
            max-width: 750px;
            margin: 30px auto;
        }

        section {
            margin-top: 20px;
            padding: 24px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow-wrap: anywhere;
        }

        dt, label {
            display: block;
            margin-top: 16px;
            font-weight: bold;
        }

        dd { margin: 4px 0 0; }

        .multiline { white-space: pre-wrap; }

        select {
            width: 100%;
            margin-top: 8px;
            padding: 12px;
            border: 1px solid #64748b;
            border-radius: 6px;
            font: inherit;
        }

        button {
            margin-top: 24px;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            background: #1d4ed8;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        .comment {
            padding: 12px 0;
            border-bottom: 1px solid #cbd5e1;
        }

        a { color: #1d4ed8; }
        [role="alert"] { color: #991b1b; }
        [role="status"] { color: #166534; }

        a:focus-visible,
        select:focus-visible,
        button:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
    </style>
</head>
<body>
<main>
    <h1>Manage Maintenance Request</h1>
    <a href="admin_dashboard.php">Back to Dashboard</a>

    <?php if ($error !== ""): ?>
        <p role="alert"><?php echo escapeHtml($error); ?></p>
    <?php endif; ?>

    <?php if ($request): ?>
        <?php if ($success !== ""): ?>
            <p role="status"><?php echo escapeHtml($success); ?></p>
        <?php endif; ?>

        <section aria-labelledby="request-heading">
            <h2 id="request-heading">
                <?php echo escapeHtml($request["title"]); ?>
            </h2>

            <dl>
                <dt>Request ID</dt>
                <dd><?php echo (int) $request["request_id"]; ?></dd>

                <dt>Tenant</dt>
                <dd><?php echo escapeHtml($request["tenant_name"]); ?></dd>

                <dt>Description</dt>
                <dd class="multiline"><?php
                    echo escapeHtml($request["description"]);
                ?></dd>

                <dt>Submitted (UTC)</dt>
                <dd><?php echo escapeHtml($request["date_submitted"]); ?></dd>

                <dt>Last Updated (UTC)</dt>
                <dd><?php echo escapeHtml($request["date_updated"]); ?></dd>
            </dl>

            <form novalidate
                action="admin_request.php?id=<?php echo $requestId; ?>"
                method="POST"
            >
                <input
                    type="hidden"
                    name="token"
                    value="<?php echo escapeHtml($_SESSION["admin_request_token"]); ?>"
                >

                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <?php foreach ($allowedStatuses as $option): ?>
                        <option
                            value="<?php echo escapeHtml($option); ?>"
                            <?php echo $selectedStatus === $option ? "selected" : ""; ?>
                        >
                            <?php echo escapeHtml($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="priority">Priority</label>
                <select id="priority" name="priority" required>
                    <?php foreach ($allowedPriorities as $option): ?>
                        <option
                            value="<?php echo escapeHtml($option); ?>"
                            <?php echo $selectedPriority === $option ? "selected" : ""; ?>
                        >
                            <?php echo escapeHtml($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Save Changes</button>
            </form>
        </section>

        <section aria-labelledby="comments-heading">
            <h2 id="comments-heading">Tenant Comments</h2>

            <?php if (!$comments): ?>
                <p>No comments yet.</p>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <article class="comment">
                        <p>
                            <strong><?php echo escapeHtml($comment["name"]); ?></strong>
                            — <?php echo escapeHtml($comment["date_created"]); ?> UTC
                        </p>
                        <p class="multiline"><?php
                            echo escapeHtml($comment["comment_text"]);
                        ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>



