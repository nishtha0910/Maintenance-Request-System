<?php
session_start();
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST"){
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if ($email === "" || $password === ""){
        $error = "Please fill in all the required fields.";
    } else if  (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Please enter a valid email address.";
    } else {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            require_once __DIR__ . "/config/database.php";
            $conn->set_charset("utf8mb4");

            $check = $conn->prepare(
                "SELECT user_id, name, email, password, role FROM users WHERE email = ?"
            );

            $check->bind_param("s", $email);
            $check->execute();

            $result = $check->get_result();
            $user = $result->fetch_assoc();

            //check entered password against the hashed password in the database

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

               $_SESSION["user_id"] = $user["user_id"];
               $_SESSION["name"] = $user["name"];
               $_SESSION["role"] = $user["role"];

               if($_SESSION["role"] === "admin"){
                header("Location: admin_dashboard.php");
                exit;
               } else if ($_SESSION["role"] === "tenant"){
                header("Location: tenant_dashboard.php");
                exit;
               } else {
                $_SESSION = [];
                session_destroy();

                http_response_code(403);
                exit("Access denied. Invalid user role.");
               }
            } else {
                $error = "Invalid email or password.";
            }
            $check->close();
        } catch (mysqli_sql_exception $exception) {
            error_log("Login database error: " . $exception->getMessage());
            $error = "Unable to process your login. Please try again later.";
        }
    }
       
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Maintenance Request System</title>

    <style>

            *{
                box-sizing: border-box;
                
            }

            body{
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
                background-color: #f1f5f9;
                color:#1e293b;

            }

            .register-box{
            max-width: 420px;
            margin: 40px auto;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.8);
            } 

            h1{
                text-align: center;
                font-size: 22px;
            
            }

            h2{
                text-align: center;
                font-size: 20px;
            }

            label{
                display: block;
                margin-bottom: 6px;
                font-weight: bold;
            }

            input{
                width:100%;
                padding:12px;
                margin-bottom: 18px;
                border:1px solid #64748b;
                border-radius: 6px;
                font:inherit;
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

           button:hover{
            background-color: #1e40af;
           }
            a{
                color:#1d4ed8;
            }

            input:focus-visible,
            button:focus-visible,
            a:focus-visible{
                outline: 3px solid #2563eb;
                outline-offset: 3px;
            }

            .login-link{
                text-align:center;
                margin-top: 20px;
            }   

        </style>

</head>
<body>
    <main>
        <h1>Maintenance Request System</h1>
        <h2>Login</h2>
        <p> Enter your credentials to log in.</p>

        <?php if ($error): ?>
            <p role="alert">
                <?php echo htmlspecialchars($error , ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <?php endif; ?>
                <?php if ($success !==""): ?>
            <p role="alert">
                <?php echo htmlspecialchars($success , ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php endif; ?>


        <form action="login.php" method="post">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email"   placeholder="Enter your email" autocomplete="username"  required>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter your password"  autocomplete="current-password"  required>
            <button type="submit">Login</button>
        </form>
        <div class="login-link">
            <p>Don't have an account ? <a href="register.php">Register here</a></p>
        </div>
    </main>
</body>
</html>