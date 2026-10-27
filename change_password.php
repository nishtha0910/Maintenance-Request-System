<?php
session_start();

require_once __DIR__ . "/includes/auth.php";


if (!isset ($_SESSION["user_id"])){
    header("Location: login.php");
    exit;
}

$error ="";
$success ="";


// Create a security token for the password-change form.

if(empty($_SESSION["password_token"])){
    $_SESSION["password_token"] = bin2hex(random_bytes(32));
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST"){
        $token = $_POST["token"] ?? "";
        $currentPassword =$_POST["current_password"] ??"";
        $newPassword = $_POST["new_password"] ??"";
        $confirmPassword = $_POST[ "confirm_password"]??"";

        if (
        !is_string($token) ||
        !hash_equals($_SESSION["password_token"], $token)
    ) {
        http_response_code(403);
        $error = "Invalid request. Please reload the page.";
    } elseif (
        !is_string($currentPassword) ||
        !is_string($newPassword) ||
        !is_string($confirmPassword)
    ) {
        $error = "Invalid form data.";
    } elseif (
        $currentPassword === "" ||
        $newPassword === "" ||
        $confirmPassword === ""
    ) {
        $error = "Please fill in all fields.";
    } elseif (strlen($newPassword) > 72) {
        $error = "The new password must be 72 bytes or fewer.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } else {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            require_once __DIR__ . "/config/database.php";
            $conn->set_charset("utf8mb4");

            $user_id = $_SESSION["user_id"];

            // Retrieve the current password hash.
            $check = $conn->prepare(
                "SELECT password FROM users WHERE user_id = ?"
            );

            $check->bind_param("i", $user_id);
            $check->execute();

            $user = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$user) {
                $error = "Your account could not be found.";
            } elseif (
                !password_verify($currentPassword, $user["password"])
            ) {
                $error = "Current password is incorrect.";
            } else {
                // Use the hashing function we already tested.
                $newHash = hashUserPassword($newPassword);

                $update = $conn->prepare(
                    "UPDATE users SET password = ? WHERE user_id = ?"
                );

                $update->bind_param("si", $newHash, $user_id);
                $update->execute();
                $update->close();

                // Refresh this session's ID and form token.
                session_regenerate_id(true);
                $_SESSION["password_token"] = bin2hex(random_bytes(32));

                $success = "Password changed successfully.";
            }
        } catch (mysqli_sql_exception $exception) {
            error_log(
                "Password change database error: " . $exception->getCode()
            );

            $error = "Unable to change your password. Please try again later.";
        }
    }
}
?>

    <!DOCTYPE html>
    <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, intial-scalke=1.0">
            <title> Change Password- Maintenance Request System</title>
            <style>
            body{
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
                background-color: #f1f5f9;
                color: #1e293b;
            }

            .profile-box{
                max-width: 420px;
                margin: 40px auto;
                padding: 30px;
                background-color: white;
                border-radius: 12px;
            
            }

            h1{
                text-align: center;
            }

            dd{
                margin: 6px 0 24px;
                overflow-wrap: anywhere;
            }

            dt{
                font-weight: bold;
            }

            * {
             box-sizing: border-box;
           }

           label {
             display: block;
             margin-bottom: 6px;
            font-weight: bold;
        }

           input {
           width: 100%;
           padding: 12px;
           margin-bottom: 18px;
           border: 1px solid #64748b;
           border-radius: 6px;
           font: inherit;
        }

         button {
         width: 100%;
         padding: 12px;
         background-color: #1d4ed8;
         color: white;
         border: none;
         border-radius: 6px;
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
            <main class="profile-box">
                <h1>Change Password</h1>
                <p>All Fields are required.</p>


                <?php if ($error !== ""):?>
                    <p role ="alert">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8");?>
                    </p>
                <?php endif; ?>
                <?php if($success !==""): ?>
                    <p role= "status">
                        <?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8");?>
                    </p>
                <?php endif; ?>


            



                <form novalidate action="change_password.php" method="post">
                    <input
                     type="hidden"
                name="token"
                value="<?php echo htmlspecialchars(
                    $_SESSION["password_token"], ENT_QUOTES, 'UTF-8'
                ); ?>"
            >
               <label for ="current_password"> Current Password </label>
               <input
                  type="password"
                id="current_password"
                name="current_password"
                placeholder="Enter your current password"
                autocomplete="current-password"
                required
            >

               <label for="new_password">New Password </label>
               <input
                type="password"
                id="new_password"
                name="new_password"
                placeholder="Enter your new password"
                autocomplete="new-password"
                required
            >
                <label for ="confirm_password"> Confirm New Password </label>
                 <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Re-enter your new password"
                autocomplete="new-password"
                required
            >  
              <button type="submit"> Change Password</button>
              </form>

              <p><a href="profile.php"> Back to My Profile </a></p>
            </main>
        </body>
    </html>