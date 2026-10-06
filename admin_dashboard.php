<?php
session_start();
require_once __DIR__ . "/includes/auth.php";

//require login 
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
if (!canAccessRole($_SESSION, "admin")) {
    http_response_code(403);
    exit("Access denied.");
}
// Create a token for logout.
if (empty($_SESSION["logout_token"])) {
    $randomBytes = random_bytes(32);
    $token = bin2hex($randomBytes);

    $_SESSION["logout_token"] = $token;
}  
?>
<DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard- Maintenance Request System</title>
    </head>
    <body>
        <h1> Admin Dashboard</h1>
         <p>Welcome, 
            <?php echo htmlspecialchars($_SESSION["name"], ENT_QUOTES, 'UTF-8'); ?>! 
        </p>
        <p>
            Maintenance Requests will appear here.
        </p>

        <p><a href="profile.php">My Profile</a></p>
        <form action="logout.php" method="POST">
            <input type="hidden" name="token"
             value="
            <?php echo htmlspecialchars($_SESSION["logout_token"], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Logout</button>

        </form>
            
    </body>
    </html>
