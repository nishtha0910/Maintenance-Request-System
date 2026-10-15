<?php
session_start();

require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/request_validation.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!canAccessRole($_SESSION, "tenant")) {
    http_response_code(403);
    exit("Access denied.");
}

if (empty($_SESSION["request_token"])) {
    $_SESSION["request_token"] = bin2hex(random_bytes(32));
}

$errors = [];
$title = "";
$description = "";
$priority = "Medium";

$success = $_SESSION["request_success"] ?? "";
unset($_SESSION["request_success"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["request_token"], $token)
    ) {
        http_response_code(403);
        $errors["security"] =
            "Invalid security token. Refresh the page and try again.";
    } else {
        $rawTitle = $_POST["title"] ?? "";
        $rawDescription = $_POST["description"] ?? "";
        $rawPriority = $_POST["priority"] ?? "";

        $errors = validateMaintenanceRequest(
            $rawTitle,
            $rawDescription,
            $rawPriority
        );

        // Preserve valid string input when displaying errors.
        $title = is_string($rawTitle) ? trim($rawTitle) : "";
        $description = is_string($rawDescription)
            ? trim($rawDescription)
            : "";
        $priority = is_string($rawPriority) ? $rawPriority : "";

        if ($errors) {
            http_response_code(422);
        } else {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                require_once __DIR__ . "/config/database.php";

                $conn->set_charset("utf8mb4");

                // Ownership and initial status come from the server.
                $userId = (int) $_SESSION["user_id"];
                $status = "Submitted";

                $insert = $conn->prepare(
                    "INSERT INTO maintenance_requests
                     (user_id, title, description, priority, status,
                      date_submitted, date_updated)
                     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
                );

                $insert->bind_param(
                    "issss",
                    $userId,
                    $title,
                    $description,
                    $priority,
                    $status
                );

                $insert->execute();
                $insert->close();

                $_SESSION["request_success"] =
                    "Maintenance request submitted successfully.";

                // Redirect so refreshing does not repeat the submission.
                header("Location: create_request.php", true, 303);
                exit;
            } catch (mysqli_sql_exception $exception) {
                error_log(
                    "Request creation database error: "
                    . $exception->getCode()
                );

                http_response_code(500);
                $errors["database"] =
                    "Unable to submit your request. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Maintenance Request</title>

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
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
        }

        h1 {
            font-size: 26px;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #64748b;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        button {
            margin-top: 24px;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background: #1d4ed8;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        a {
            color: #1d4ed8;
        }

        .errors {
            color: #991b1b;
        }

        [role="status"] {
            color: #166534;
        }

        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
    </style>
</head>
<body>
<main>
    <h1>Create Maintenance Request</h1>
    <p>Describe the issue and select its initial priority. All fields are required.</p>

    <?php if ($success !== ""): ?>
        <p role="status">
            <?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div role="alert" class="errors">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="create_request.php" method="POST">
        <input
            type="hidden"
            name="token"
            value="<?php echo htmlspecialchars(
                $_SESSION["request_token"],
                ENT_QUOTES,
                "UTF-8"
            ); ?>"
        >

        <label for="title">Request Title</label>
        <input
            type="text"
            id="title"
            name="title"
            maxlength="150"
            value="<?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?>"
            required
        >

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            maxlength="5000"
            required
        ><?php echo htmlspecialchars($description, ENT_QUOTES, "UTF-8"); ?></textarea>

        <label for="priority">Priority</label>
        <select id="priority" name="priority" required>
            <option value="">Select priority</option>
            <?php foreach (["Low", "Medium", "High"] as $option): ?>
                <option
                    value="<?php echo $option; ?>"
                    <?php echo $priority === $option ? "selected" : ""; ?>
                >
                    <?php echo $option; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Submit Request</button>
    </form>

    <p><a href="tenant_dashboard.php">Back to Dashboard</a></p>
</main>
</body>
</html>