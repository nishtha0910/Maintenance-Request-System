<?php
session_start();

if (!isset ($_SESSION["user_id"])){
    header("Location: login.php");
    exit;
}

// Create a security token for the password-change form.

if(empty($_SESSION["password_token"])){
    $randomBytes = random_bytes(32);
    $_SESSION["password_token"] = bin2hex($randomBytes);
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

                <form action="change_password.php" method="post">
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