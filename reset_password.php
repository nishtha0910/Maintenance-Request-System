<?php
session_start();

header("Cache-Control: no-store");
header("Referrer-Policy: no-referrer");

require_once __DIR__ . "/includes/auth.php";

$error = "";
$success = false;
$validLink = false;
$transactionStarted = false;

if (empty($_SESSION["reset_password_token"])) {
    $_SESSION["reset_password_token"] = bin2hex(random_bytes(32));
}

$token = $_GET["token"] ?? "";
$tokenHash = "";

if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = "This reset link is invalid or has expired.";
} else {
    $tokenHash = hash("sha256", $token);

    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require_once __DIR__ . "/config/database.php";
        $conn->set_charset("utf8mb4");

        $check = $conn->prepare(
            "SELECT user_id FROM password_reset_tokens
             WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP()"
        );
        $check->bind_param("s", $tokenHash);
        $check->execute();
        $reset = $check->get_result()->fetch_assoc();
        $check->close();

        $validLink = (bool) $reset;

        if (!$validLink) {
            $error = "This reset link is invalid or has expired.";
        } elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
            $formToken = $_POST["form_token"] ?? "";
            $password = $_POST["new_password"] ?? "";
            $confirm = $_POST["confirm_password"] ?? "";

            if (
                !is_string($formToken) ||
                !hash_equals($_SESSION["reset_password_token"], $formToken)
            ) {
                http_response_code(403);
                $error = "Invalid security token. Refresh the page and try again.";
            } elseif (!is_string($password) || !is_string($confirm)) {
                $error = "Invalid password input.";
            } elseif (strlen($password) < 8 || strlen($password) > 72) {
                $error = "Password must be between 8 and 72 bytes.";
            } elseif ($password !== $confirm) {
                $error = "The passwords do not match.";
            } else {
                $passwordHash = hashUserPassword($password);
                $userId = (int) $reset["user_id"];

                $conn->begin_transaction();
                $transactionStarted = true;

                // Lock the account, matching the reset-request lock order.
                $lock = $conn->prepare(
                    "SELECT user_id FROM users WHERE user_id = ? FOR UPDATE"
                );
                $lock->bind_param("i", $userId);
                $lock->execute();
                $account = $lock->get_result()->fetch_assoc();
                $lock->close();

                // Recheck the token inside the transaction.
                $check = $conn->prepare(
                    "SELECT reset_id FROM password_reset_tokens
                     WHERE user_id = ? AND token_hash = ?
                     AND expires_at > UTC_TIMESTAMP()
                     FOR UPDATE"
                );
                $check->bind_param("is", $userId, $tokenHash);
                $check->execute();
                $activeToken = $check->get_result()->fetch_assoc();
                $check->close();

                if (!$account || !$activeToken) {
                    $conn->rollback();
                    $transactionStarted = false;
                    $validLink = false;
                    $error = "This reset link is invalid or has expired.";
                } else {
                    $update = $conn->prepare(
                        "UPDATE users SET password = ? WHERE user_id = ?"
                    );
                    $update->bind_param("si", $passwordHash, $userId);
                    $update->execute();
                    $update->close();

                    // Consume the reset link so it cannot be reused.
                    $delete = $conn->prepare(
                        "DELETE FROM password_reset_tokens WHERE user_id = ?"
                    );
                    $delete->bind_param("i", $userId);
                    $delete->execute();
                    $delete->close();

                    $conn->commit();
                    $transactionStarted = false;

                    unset($_SESSION["reset_password_token"]);
                    $success = true;
                    $validLink = false;
                }
            }
        }
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }

        error_log(
            "Password reset failed: " . get_class($exception)
            . " code " . $exception->getCode()
        );

        http_response_code(500);
        $error = "Unable to reset your password. Please try again later.";
        $validLink = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    
<link rel="stylesheet" href="assets/css/app.css?v=1">
</head>
<body class="auth-page">
<?php require __DIR__ . "/includes/site_header.php"; ?>
<main id="main-content">
    <h1>Reset Password</h1>

    <?php if ($error !== ""): ?>
        <p role="alert">
            <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p role="status">
            Password reset successfully. You can now log in with your new password.
        </p>
    <?php elseif ($validLink): ?>
        <form novalidate method="POST">
            <input type="hidden" name="form_token"
                   value="<?php echo htmlspecialchars(
                       $_SESSION["reset_password_token"],
                       ENT_QUOTES,
                       "UTF-8"
                   ); ?>">

            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password"
                   minlength="8" maxlength="72"
                   autocomplete="new-password" required>

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password"
                   name="confirm_password"
                   minlength="8" maxlength="72"
                   autocomplete="new-password" required>

            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>

    <p><a href="login.php">Back to Login</a></p>
    <p><a href="forgot_password.php">Request a new reset link</a></p>
</main>
<?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
</html>