<?php
session_start();
require_once __DIR__ . "/includes/auth.php";

//only loogrd-in users can access this page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$error="";
$success="";
$profile= null;

if (empty($_SESSION["profile_token"])) {
    $_SESSION["profile_token"] = bin2hex(random_bytes(32));
}

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

    
    $profile= $check->get_result()->fetch_assoc();

    $check->close();

    if( !$profile){
        http_response_code(404);
        $error= "Profile not found.";
    } elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
        $token = $_POST["token"] ?? '';
        $name= $_POST["name"] ?? '';
        $email= $_POST["email"] ?? '';

        if(
            !is_string($token) || 
            !hash_equals($_SESSION["profile_token"] ,$token)
        ){
            http_response_code(403);
            $error= "Invalid request token. Please try again.";
        } elseif ( !is_string($name) || !is_string($email)){
            $error= "Invalid form data. Please try again.";
        } else{

            $name= trim($name);
            $email= trim($email);

            $profile["name"]= $name;
            $profile["email"]= $email;

            if($name==="" || $email===""){
                $error= "Please fill in all the required fields.";
            } elseif (mb_strlen($name) >100 || mb_strlen($email) >150){
                $error= "Name must be less than 100 characters and email must be less than 150 characters.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $error= "Please enter a valid email address.";
            } else{
                  $duplicate = $conn->prepare(
                    "SELECT user_id FROM users WHERE email = ? AND user_id <> ?"
                  );
                  $duplicate->bind_param("si", $email, $user_id);
                    $duplicate->execute();
                    $duplicate->store_result();

                    $emailTaken= $duplicate->num_rows > 0;
                     $duplicate->close();

                     if ($emailTaken){
                        $error= "This email is already registered. Please use a different email.";
                     } else{
                        $update= $conn->prepare(
                            "UPDATE users SET name = ?, email = ? WHERE user_id = ?"
                        );
                        $update->bind_param("ssi", $name, $email, $user_id);
                        $update->execute();
                        $update->close();

                        $_SESSION["name"]= $name;
                     

                        $success= "Profile updated successfully.";
                     }}}}
                     
            } catch (mysqli_sql_exception $exception){
                if ($exception->getCode() === 1062) {
                    $error= "This email is already registered.";
                } else{
                    error_log("Profile databse error :" . $exception->getCode());
                    http_response_code(500);
                    $error =" Unable to process your profile. Please try again. ";

                }
     
     }
     ?>

     <!DOCTYPE html>
     <html lang="en">
     <head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Edit Profile- Maintenance Request System</title>

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
            <h1>Edit Profile</h1>
            <?php if ($error !== ""): ?>
                <p role="alert" >
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <?php endif; ?>

                <?php if ($success !==""): ?>
                    <p role="status">
                        <?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8");?>
                    </p>

                    <?php endif; ?>
                    <?php if ($profile): ?>
                   
                        <form action="EditProfile.php" method="post">

                        <input 
                        type="hidden"
                        name="token"
                        value="<?php echo htmlspecialchars(
                            $_SESSION[ "profile_token"], ENT_QUOTES ,'UTF-8'
                        );?>"
                        >
                    <label for="name">Full Name</label>
                       <input
                        type="text"
                         id="name"
                         name="name"
                         maxlength="100"
                         autocomplete="name"
                         value="<?php echo htmlspecialchars(
                           $profile["name"], ENT_QUOTES, 'UTF-8'
                         ); ?>"
                         required
                          >

                        <label for="email">Email Address</label>
                         <input
                         type="email"
                         id="email"
                         name="email"
                         maxlength="150"
                         autocomplete="email"
                         value="<?php echo htmlspecialchars(
                          $profile["email"], ENT_QUOTES, 'UTF-8'
                         ); ?>"
                          required
                     >

    <p>
        Role:
        <?php echo htmlspecialchars(
            $profile["role"], ENT_QUOTES, 'UTF-8'
        ); ?>
    </p>

    <button type="submit">Save Changes</button>
</form>

<p><a href="profile.php">Cancel</a></p>


<?php endif; ?>
 </main>
 </body>
 </html>