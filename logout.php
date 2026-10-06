<?php 
session_start();
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        header("Allow: POST");
        exit("Please use the Logout button.");
    }

    $token = $_POST["token"] ?? '';

    if(
        !is_string($token) ||
        empty($_SESSION["logout_token"]) ||
        !hash_equals($_SESSION["logout_token"], $token)
    ){
        http_response_code(403);
        exit("Invalid logout request.");
    
    }

    // Clear all session data
    $_SESSION = [];

    // Destroy the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", [
            'expires' => time() - 3600,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => $params["samesite"] ,

        ]
        );
    }

    //Delete the session on the server 
    session_destroy();

    header("Location: login.php");
    exit;