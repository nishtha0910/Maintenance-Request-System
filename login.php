<?php
session_start();

require_once __DIR__ . "/includes/login_security.php";

$error = "";
$success = "";
$transactionStarted = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    if (!is_string($email) || !is_string($password)) {
        $error = "Invalid login details.";
    } else {
        $email = trim($email);

        if ($email === "" || $password === "") {
            $error = "Please fill in all the required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                require_once __DIR__ . "/config/database.php";

                $conn->set_charset("utf8mb4");
                $conn->begin_transaction();
                $transactionStarted = true;

                // Serialize login attempts for this account.
                $check = $conn->prepare(
                    "SELECT user_id, name, password, role, locked_until,
                            (locked_until > UTC_TIMESTAMP()) AS is_locked
                     FROM users
                     WHERE email = ?
                     FOR UPDATE"
                );
                $check->bind_param("s", $email);
                $check->execute();
                $user = $check->get_result()->fetch_assoc();
                $check->close();

                $authenticated = false;

                if (!$user) {
                    $error = "Invalid email or password.";
                } elseif ((int) $user["is_locked"] === 1) {
                    $error = "Account temporarily locked. Please try again later.";
                } else {
                    $userId = (int) $user["user_id"];

                    // Clear the previous lock once it has expired.
                    if ($user["locked_until"] !== null) {
                        $clear = $conn->prepare(
                            "DELETE FROM login_attempts WHERE user_id = ?"
                        );
                        $clear->bind_param("i", $userId);
                        $clear->execute();
                        $clear->close();

                        $unlock = $conn->prepare(
                            "UPDATE users
                             SET locked_until = NULL
                             WHERE user_id = ?"
                        );
                        $unlock->bind_param("i", $userId);
                        $unlock->execute();
                        $unlock->close();
                    }

                    if (password_verify($password, $user["password"])) {
                        // Successful login clears earlier failures.
                        $clear = $conn->prepare(
                            "DELETE FROM login_attempts WHERE user_id = ?"
                        );
                        $clear->bind_param("i", $userId);
                        $clear->execute();
                        $clear->close();

                        $authenticated = true;
                    } else {
                        // Remove attempts outside the ten-minute window.
                        $cleanup = $conn->prepare(
                            "DELETE FROM login_attempts
                             WHERE user_id = ?
                             AND attempted_at <=
                                 DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)"
                        );
                        $cleanup->bind_param("i", $userId);
                        $cleanup->execute();
                        $cleanup->close();

                        // Record this failed attempt.
                        $record = $conn->prepare(
                            "INSERT INTO login_attempts (user_id, attempted_at)
                             VALUES (?, UTC_TIMESTAMP())"
                        );
                        $record->bind_param("i", $userId);
                        $record->execute();
                        $record->close();

                        // Read failure times using the database's UTC clock.
                        $attempts = $conn->prepare(
                            "SELECT attempted_at, UTC_TIMESTAMP() AS checked_at
                             FROM login_attempts
                             WHERE user_id = ?"
                        );
                        $attempts->bind_param("i", $userId);
                        $attempts->execute();
                        $attemptRows = $attempts->get_result()
                            ->fetch_all(MYSQLI_ASSOC);
                        $attempts->close();

                        $failedAttemptTimes = [];

                        foreach ($attemptRows as $attemptRow) {
                            $failedAttemptTimes[] = strtotime(
                                $attemptRow["attempted_at"] . " UTC"
                            );
                        }

                        // The attempt inserted above guarantees a row exists.
                        $checkedAt = strtotime(
                            $attemptRows[0]["checked_at"] . " UTC"
                        );

                        // Use the same rule that our PHPUnit tests exercise.
                        if (shouldLockAccount($failedAttemptTimes, $checkedAt)) {
                            $lock = $conn->prepare(
                                "UPDATE users
                                 SET locked_until =
                                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
                                 WHERE user_id = ?"
                            );
                            $lock->bind_param("i", $userId);
                            $lock->execute();
                            $lock->close();

                            $error = "Account locked for 10 minutes after 5 failed login attempts.";
                        } else {
                            $error = "Invalid email or password.";
                        }
                    }
                }

                $conn->commit();
                $transactionStarted = false;

                if ($authenticated) {
                    if (!in_array($user["role"], ["admin", "tenant"], true)) {
                        $_SESSION = [];
                        session_destroy();

                        http_response_code(403);
                        exit("Access denied. Invalid user role.");
                    }

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["user_id"];
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["role"] = $user["role"];

                    $destination = $user["role"] === "admin"
                        ? "admin_dashboard.php"
                        : "tenant_dashboard.php";

                    header("Location: " . $destination);
                    exit;
                }
            } catch (mysqli_sql_exception $exception) {
                if ($transactionStarted) {
                    $conn->rollback();
                }

                error_log(
                    "Login database error: " . $exception->getCode()
                );

                http_response_code(500);
                $error = "Unable to process your login. Please try again later.";
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
    <title>Login - Maintenance Request System</title>

    

<link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="auth-page">
<?php require __DIR__ . "/includes/site_header.php"; ?>
    <main id="main-content">
        <h1>Maintenance Request System</h1>
        <h2>Login</h2>
        <p> Enter your credentials to log in.</p>

        <?php if ($error): ?>
            <p role="alert">
                <?php echo htmlspecialchars($error , ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <?php endif; ?>
                <?php if ($success !==""): ?>
            <p role="alert">
                <?php echo htmlspecialchars($success , ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php endif; ?>


      <form action="login.php" method="post" novalidate>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email"   placeholder="Enter your email" autocomplete="username"  required>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter your password"  autocomplete="current-password"  required>
            <button type="submit">Login</button>
        </form>
        <p class="login-link">
        <a href="forgot_password.php">Forgot Password?</a></p>
        <div class="login-link">
            <p>Don't have an account ? <a href="register.php">Register here</a></p>
        </div>
    </main>
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
</html>