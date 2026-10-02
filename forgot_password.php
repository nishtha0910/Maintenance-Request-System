<?php 
session_start();

$error = "";
$message = "";

//protect the reset reuest form.

if (empty($_SESSION["reset_request_token"])) {
    $_SESSION["reset_request_token"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"] === "POST"){
    $formToken = $_POST["token"] ?? "";
    $email = $_POST["email"] ?? "";
    
    if(
        !is_string($formToken) ||
        !hash_equals($_SESSION["reset_request_token"], $formToken)

    )
    {
        http_response_code(403);
       $error = "Invalid security token. Please refresh the page and try again.";
    }else if(!is_string($email)){
        $error = "Invalid email address.";
    } else{
        $email = trim($email);
        if ( $email === ""||
        mb_strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        ){
            $error = "Please enter a valid email address.";
        } else{
            $message = "If an account with that email exists, a password reset link has been sent.";
            $transactionStarted = false;

            try{
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                require_once __DIR__ . "/config/database.php";
                require_once __DIR__ . "/includes/mailer.php";

                $conn->set_charset("utf8mb4");
                $conn->begin_transaction();
                $transactionStarted = true;


                $check = $conn->prepare(
                    "SELECT user_id FROM users WHERE email = ? FOR UPDATE"
                );
                $check->bind_param("s", $email);
                $check->execute();

                $user = $check->get_result()->fetch_assoc();
                $check->close();

                $sendLink = false;

                if($user){
                    $user_id = $user["user_id"];

                    $recent= $conn->prepare(
                        "SELECT request_time FROM password_reset_requests
                        WHERE user_id = ? AND expires_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 14 MINUTE)"
                    );
                    $recent->bind_param("i", $user_id);
                    $recent->execute();
                    $recent-> store_result();

                    $requestCount = $recent->num_rows >0;
                    $recent->close();

                    
                         if (!$requestedrecently)  {
                        $resetToken = bin2hex(random_bytes(32));
                        $tokenHash = hash("sha256", $resetToken);

                        // Replace any previous reset link for this account.
                        $delete = $conn->prepare(
                            "DELETE FROM password_reset_tokens
                             WHERE user_id = ?"
                        );
                        $delete->bind_param("i", $user_id);
                        $delete->execute();
                        $delete->close();

                        $insert = $conn->prepare(
                            "INSERT INTO password_reset_tokens
                             (user_id, token_hash, expires_at)
                             VALUES (
                                 ?, ?,
                                 DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                             )"
                        );
                        $insert->bind_param("is", $user_id, $tokenHash);
                        $insert->execute();
                        $insert->close();

                        $sendLink = true;
                    }
                }

                $conn->commit();
                $transactionStarted = false;

                if ($sendLink) {
                    $resetLink =
                        "http://localhost/maintenance-request-system/reset_password.php"
                        . "?token=" . urlencode($resetToken);

                    $emailMessage =
                        "A password reset was requested for your account.\n\n"
                        . "Open this link to choose a new password:\n"
                        . $resetLink . "\n\n"
                        . "This link expires in 15 minutes.\n"
                        . "If you did not request this, ignore this email.";

                    sendEmail(
                        $user["email"],
                        "Reset Your Maintenance System Password",
                        $emailMessage
                    );
                }
            } catch (Throwable $exception) {
                if ($transactionStarted) {
                    $conn->rollback();
                }

                // Avoid logging passwords or reset links.
                error_log(
                    "Password reset request failed: "
                    . get_class($exception)
                    . " code " . $exception->getCode()
                );
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
        <title> Forgot Password </title>

        <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .reset-box {
            max-width: 420px;
            margin: 40px auto;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
        }

        h1 {
            font-size: 24px;
            text-align: center;
        }

        p {
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #64748b;
            border-radius: 6px;
            font: inherit;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            background-color: #1d4ed8;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        a {
            color: #1d4ed8;
        }

        input:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
    </style>
    </head>

    <body>
        <main class="reset-box">
            <h1>Forgot Password</h1>
            <p>Enter your email address below and we'll send you a link to reset your password.</p>

            <form action="forgot_password.php" method="POST">
                <input type="hidden" name="token"
                 value="<?php echo htmlspecialchars($_SESSION["reset_request_token"], ENT_QUOTES, 'UTF-8'); ?>">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" 
                placeholder="Enter your email" 
                autocomplete="email" 
                maxlength="150"
                required>
                
                <button type="submit">Send Reset Link</button>
            </form>

            <p><a href="login.php">Back to Login</a></p>
        </main>
    </body>
</html>