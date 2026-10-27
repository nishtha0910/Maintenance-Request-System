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

        
        <link rel="stylesheet" href="assets/css/app.css?v=1">
</head>

        <body class="profile-page">
<?php require __DIR__ . "/includes/site_header.php"; ?>

        <main id="main-content" class="profile-box">
            <h1>My Profile</h1>
            <?php if ($error !== ""): ?>
                <p role="alert" >
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <?php elseif ($profile): ?>
                    <div class="profile-avatar" aria-label="Profile avatar">
                        <svg aria-hidden="true" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><circle cx="32" cy="23" r="10"/><path d="M13 54c0-11 8-18 19-18s19 7 19 18"/></svg>
                    </div>
                    <dl class="profile-details">
                        <dt>Name:</dt>
                        <dd><?php echo htmlspecialchars($profile["name"], ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt>Email:</dt>
                        <dd><?php echo htmlspecialchars($profile["email"], ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt>Role:</dt>
                        <dd><?php echo htmlspecialchars($profile["role"], ENT_QUOTES, 'UTF-8'); ?></dd>
                    </dl>

                    <div class="profile-actions">
                        <a class="button" href="EditProfile.php">Edit Profile</a>
                        <a class="button button-outline" href="change_password.php">Change Password</a>
                        <form action="logout.php" method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['logout_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="button-outline" type="submit">Logout</button>
                        </form>
                    </div>

                     <?php endif; ?>

        </main>
         <?php require __DIR__ . "/includes/site_footer.php"; ?>
</body>
     </html>