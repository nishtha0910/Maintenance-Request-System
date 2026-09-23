<?php 
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;

}

if (($_SESSION["role"]?? "")!== "tenant") {
    http_response_code(403);
    exit ("Access denied");
}

if (empty($_SESSION["logout_token"])) {
    $_SESSION["logout_token"] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tenant Dashboard- Maintenance Request System</title>
    </head>

    <body>
        <h1> Tenant Dashboard</h1>

        <p>
            Welcome, 
            <?php echo htmlspecialchars($_SESSION["name"], ENT_QUOTES, 'UTF-8'); ?>! </p>
            <p> Your Maintenance Request will appear here.</p>

            <form action="logout.php" method="POST">
            <input type="hidden" name="logout_token" value="
            <?php echo htmlspecialchars($_SESSION["logout_token"], ENT_QUOTES, 'UTF-8'); ?>">

            <button type="submit">Logout</button>
                 
            </form>
    </body>

</html>