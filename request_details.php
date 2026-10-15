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

if (empty($_SESSION["comment_token"])) {
    $_SESSION["comment_token"] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";
$commentText = "";
$request = null;
$comments = [];

if (($_SESSION["comment_success_request"] ?? null) === $requestId) {
    $success = "Comment added successfully.";
    unset($_SESSION["comment_success_request"]);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/config/database.php";
    $conn->set_charset("utf8mb4");

    $userId = (int) $_SESSION["user_id"];

    // Check ownership before displaying or accepting comments.
    $check = $conn->prepare(
        "SELECT request_id, title, description, priority, status,
                date_submitted, date_updated
         FROM maintenance_requests
         WHERE request_id = ? AND user_id = ?"
    );
    $check->bind_param("ii", $requestId, $userId);
    $check->execute();
    $request = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$request) {
        http_response_code(404);
        $error = "Request not found.";
    } else {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $token = $_POST["token"] ?? "";
            $rawComment = $_POST["comment_text"] ?? "";

            $commentText = is_string($rawComment)
                ? trim($rawComment)
                : "";

            if (
                !is_string($token) ||
                !hash_equals($_SESSION["comment_token"], $token)
            ) {
                http_response_code(403);
                $error = "Invalid security token. Refresh the page and try again.";
            } elseif (
                !is_string($rawComment) ||
                $commentText === "" ||
                mb_strlen($commentText, "UTF-8") > 2000
            ) {
                http_response_code(422);
                $error = "Enter a comment of 1–2,000 characters.";
            } else {
                // Recheck ownership as part of the insert.
                $insert = $conn->prepare(
                    "INSERT INTO comments
                     (request_id, user_id, comment_text, date_created)
                     SELECT request_id, ?, ?, UTC_TIMESTAMP()
                     FROM maintenance_requests
                     WHERE request_id = ? AND user_id = ?"
                );
                $insert->bind_param(
                    "isii",
                    $userId,
                    $commentText,
                    $requestId,
                    $userId
                );
                $insert->execute();
                $saved = $insert->affected_rows === 1;
                $insert->close();

                if ($saved) {
                    $_SESSION["comment_success_request"] = $requestId;

                    header(
                        "Location: request_details.php?id=" . $requestId,
                        true,
                        303
                    );
                    exit;
                }

                http_response_code(404);
                $error = "Request not found.";
                $request = null;
            }
        }

        if ($request) {
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
    }
} catch (mysqli_sql_exception $exception) {
    error_log("Request details database error: " . $exception->getCode());

    http_response_code(500);
    $error = "Unable to load or update this request. Please try again later.";
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
    <title>Maintenance Request Details</title>

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

        dt {
            margin-top: 12px;
            font-weight: bold;
        }

        dd {
            margin: 4px 0 0;
        }

        .multiline {
            white-space: pre-wrap;
        }

        .comment {
            padding: 16px 0;
            border-bottom: 1px solid #cbd5e1;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: bold;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #64748b;
            border-radius: 6px;
            font: inherit;
            resize: vertical;
        }

        button {
            margin-top: 12px;
            padding: 12px 20px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 6px;
            font: inherit;
            cursor: pointer;
        }

        a {
            color: #1d4ed8;
        }

        [role="alert"] {
            color: #991b1b;
        }

        [role="status"] {
            color: #166534;
        }

        a:focus-visible,
        textarea:focus-visible,
        button:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
    </style>
</head>
<body>
<main>
    <h1>Maintenance Request Details</h1>
    <a href="my_requests.php">Back to My Requests</a>

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

                <dt>Description</dt>
                <dd class="multiline"><?php
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
        </section>

        <section aria-labelledby="comments-heading">
            <h2 id="comments-heading">Comments</h2>

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

            <form
                action="request_details.php?id=<?php echo $requestId; ?>"
                method="POST"
            >
                <input
                    type="hidden"
                    name="token"
                    value="<?php echo escapeHtml($_SESSION["comment_token"]); ?>"
                >

                <label for="comment_text">Add a Comment</label>
                <textarea
                    id="comment_text"
                    name="comment_text"
                    maxlength="2000"
                    required
                ><?php echo escapeHtml($commentText); ?></textarea>

                <button type="submit">Add Comment</button>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>