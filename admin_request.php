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

$isEditMode = ($_GET["mode"] ?? "view") === "edit";
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$isEditMode) {
    header("Allow: GET");
    http_response_code(405);
    exit("Use the Edit page to save changes.");
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

    if ($isEditMode && empty($_SESSION["profile_token"])) {
        $_SESSION["profile_token"] = bin2hex(random_bytes(32));
    }

    $allowedStatuses = ["Submitted", "In Progress", "Completed"];
    $allowedPriorities = ["Low", "Medium", "High"];

    $error = "";

    $request = null;
    $comments = [];

    
    
     try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require_once __DIR__ . "/config/database.php";
        $conn->set_charset("utf8mb4");

        $check= $conn->prepare(
              "SELECT r.request_id, r.title, r.description,
                r.priority, r.status, r.resolution_notes,
                r.date_submitted, r.date_updated,
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
        $resolutionNotes = $request["resolution_notes"] ?? "";

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $token = $_POST["token"] ?? "";
            $status = $_POST["status"] ?? "";
            $priority = $_POST["priority"] ?? "";
            $rawNotes = $_POST["resolution_notes"] ?? "";
            $resolutionNotes = is_string($rawNotes) ? trim($rawNotes) : "";
            

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
            } elseif (!is_string($rawNotes) || mb_strlen($resolutionNotes, "UTF-8") > 5000) {
                http_response_code(422);
                $error = "Resolution notes must be text of no more than 5,000 characters.";
            } else {
                $update = $conn->prepare(
                    "UPDATE maintenance_requests
                     SET status = ?, priority = ?, resolution_notes = ?, date_updated = UTC_TIMESTAMP()
                     WHERE request_id = ?"
                );
                $update->bind_param("sssi", $status, $priority, $resolutionNotes, $requestId);
                $update->execute();
                $update->close();

                $_SESSION["updated_request_id"] = $requestId;

                header(
                    "Location: admin_dashboard.php",
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
    <title><?php echo $isEditMode ? "Edit" : "View"; ?> Maintenance Request</title>

    
<link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="request-list">
<?php require __DIR__ . "/includes/site_header.php"; ?>
<main id="main-content">
    <h1><?php echo $isEditMode ? "Edit" : "View"; ?> Maintenance Request</h1>
    <a href="admin_dashboard.php">Back to Dashboard</a>

    <?php if ($error !== ""): ?>
        <p role="alert"><?php echo escapeHtml($error); ?></p>
    <?php endif; ?>

    <?php if ($request): ?>
        

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

            <?php if (!$isEditMode): ?>
            <dl>
                <dt>Status</dt>
                <dd><?php echo escapeHtml($request["status"]); ?></dd>
                <dt>Priority</dt>
                <dd><?php echo escapeHtml($request["priority"]); ?></dd>
                <dt>Resolution Notes</dt>
                <dd class="multiline"><?php echo escapeHtml($resolutionNotes !== "" ? $resolutionNotes : "No resolution notes yet."); ?></dd>
            </dl>
            <a class="button" href="admin_request.php?id=<?php echo $requestId; ?>&amp;mode=edit">Edit Request</a>
            <?php else: ?>
            <form id="update-request" novalidate
                action="admin_request.php?id=<?php echo $requestId; ?>&amp;mode=edit"
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

                <label for="resolution_notes">Resolution Notes (optional)</label>
                    <textarea
                       id="resolution_notes"
                       name="resolution_notes"
                       rows="5"
                       maxlength="5000"
                      ><?php echo escapeHtml($resolutionNotes); ?></textarea>

                <button type="submit">Save Changes</button>
            </form>

            <button type="button" id="delete-request"
                data-id="<?= (int) $requestId ?>"
                data-token="<?= escapeHtml($_SESSION["profile_token"]) ?>">Delete Request</button>
            <p id="delete-error" role="alert"></p>
            <script>
            document.getElementById("delete-request").addEventListener("click", async function () {
                const title = document.getElementById("request-heading").textContent.trim();
                if (!window.confirm("Permanently delete request #" + this.dataset.id + " (" + title + ") and its comments?")) return;

                this.disabled = true;
                const error = document.getElementById("delete-error");
                error.textContent = "";
                try {
                    const response = await fetch("api/delete_request.php?id=" + this.dataset.id, {
                        method: "DELETE",
                        headers: {"X-CSRF-Token": this.dataset.token}
                    });
                    const result = await response.json();
                    if (!response.ok || result.request_id !== Number(this.dataset.id)) {
                        throw new Error(result.error || "Deletion could not be confirmed. Refresh the page to check.");
                    }
                    window.location.assign("admin_dashboard.php");
                } catch (problem) {
                    error.textContent = problem.message;
                    this.disabled = false;
                }
            });
            </script>
            <?php endif; ?>

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
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
</html>



