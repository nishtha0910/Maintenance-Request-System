<?php 
session_start();

//protect the reset reuest form.

if (empty($_SESSION["reset_request_token"])) {
    $_SESSION["reset_request_token"] = bin2hex(random_bytes(32));
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