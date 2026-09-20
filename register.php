<?php
$error="";
$success="";

if($_SERVER["REQUEST_METHOD"]=="POST")
    {
        $name= trim($_POST["name"] ?? '');
        $email= trim($_POST["email"] ?? '');
        $password= $_POST["password"] ?? '';
        $confirm_password= $_POST["confirm_password"] ?? '';


        if(
            $name=="" || 
            $email=="" ||
            $password=="" ||
            $confirm_password==""
        ){
        $error = "Please fill in all the required fields.";

    } else if (
            mb_strlen($name) >100 || 
            mb_strlen($email) >150 
        ){
           $error="Name must be less than 100 characters and email must be less than 150 characters.";  }
           else if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error="Please enter a valid email address.";
           }
           else if ($password !== $confirm_password){
            $error="Passwords do not match.";
           }
           else {
            try{
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

                require_once __DIR__ . "/config/database.php";
                $conn-> set_charset("utf8mb4");
                
                //ckeck wether email is already registered
                $check = $conn->prepare(
                    "SELECT user_id FROM users WHERE email = ?"
                    );

                    $check->bind_param("s", $email);
                    $check->execute();
                    $check->store_result();
                    
            if ($check->num_rows > 0){
                $error="This email is already registered. Please use a different email or login.";
            } else if (strlen($password ) >72){
                $error=" Password must be less than 72 characters.";

            } else{
                // hash the password before storing it 
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // public registration always creates tenants 

                $role="tenant";
                $insert = $conn->prepare(
                    "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
                    );
                $insert->bind_param("ssss", $name, $email, $hashed_password, $role);
                $insert->execute();
                $insert->close();
                $success="Account created successfully. You can now log in.";
            }

               $check->close();
            }catch (mysqli_sql_exception $exception){
                if ($exception->getCode() === 1062){
                    $error="This email is already registered. Please use a different email or login.";
                } else{
                    error_log("Registration database error: " . $exception->getMessage());
                    $error="Unable to create your account . Please try again later.";
                  


            }
           }
            

    }}
    ?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Register- Maintenance Request System</title>

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
        <main class="register-box">
            <h1>Maintenance Request System</h1>
            <h2>Create Account</h2>
            <p> All fields are required</p>

            <?php if($error !=""):?>
                <p role="alert" >
                    <?php echo htmlspecialchars($error , ENT_QUOTES, 'UTF-8'); ?>
                </p> 

            <?php endif; ?>


            <?php if($success !=""):?>
                <p role="success" >
                    <?php echo htmlspecialchars($success , ENT_QUOTES, 'UTF-8'); ?>
                </p> 

                <?php endif; ?> 

            <form action="register.php" method="post">
                <label for="name">full Name</label>
                <input type="text" 
                name="name"
                 id="name" 
                 placeholder="Enter your full name" 
                 autocomplete="name"
                 required>

                 <label for="email">Email</label>
                 <input type="email" 
                 id="email"
                  name="email"
                   placeholder="Enter your email"
                    autocomplete="email"
                    required>

                <label for="password">Password</label>
                <input type="password" 
                id="password"
                 name="password"
                 autocomplete="new-password"
                  placeholder="Enter your password" 
                  required>

                <label for="confirm_password">Confirm Password</label>
                <input type="password" 
                id="confirm_password"
                 name="confirm_password" 
                 autocomplete="new-password"
                  placeholder="Confirm your password"
                   required>

        <button type="submit">Register</button>


              </form>

              <p class="login-link">
                Already have an account? <a href="login.php">Login</a>
            </p>
                
        </main>
    </body>
</html>