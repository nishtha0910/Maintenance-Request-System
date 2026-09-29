<?php
session_start();
require_once __DIR__ . "/includes/auth.php";

//only loogrd-in users can access this page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$error="";
$profile= null;

try{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . "/config/database.php";
    $conn->set_charset("utf8mb4");

    $user_id= $_SESSION["user_id"];

    $check= $conn->prepare(
        "SELECT user_id, name, email, role FROM users WHERE user_id = ?"
    );
    $check->bind_param("i", $user_id);
    $check->execute();

    $result= $check->get_result();
    $profile= $result->fetch_assoc();

    $check->close();

    if( !$profile){
        http_response_code(404);
        $error= "Profile not found.";
    }

} 
     catch (mysqli_sql_exception $exception){
        error_log("Profile database error: " . $exception->getCode());

        http_response_code(500);
        $error= "Unable to retrieve profile information. Please try again later.";
        
     }
     ?>

     <!DOCTYPE html>
     <html lang="en">
     <head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>User Profile- Maintenance Request System</title>

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
        </style>
        </head>

        <body>

        <main class="profile-box">
            <h1>My Profile</h1>
            <?php if ($error !== ""): ?>
                <p role="alert" >
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <?php elseif ($profile): ?>
                    <dl>
                        <dt>Name:</dt>
                        <dd><?php echo htmlspecialchars($profile["name"], ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt>Email:</dt>
                        <dd><?php echo htmlspecialchars($profile["email"], ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt>Role:</dt>
                        <dd><?php echo htmlspecialchars($profile["role"], ENT_QUOTES, 'UTF-8'); ?></dd>
                    </dl>

                    <p><a href="EditProfile.php">Edit Profile</a></p>

                     <?php endif; ?>

        </main>
         </body>
     </html>