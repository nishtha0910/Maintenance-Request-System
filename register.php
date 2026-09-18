<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Register- Maintenance Request System</title>
    </head>
    <body>
        <main class="register-box">
            <h1>Maintenance Request System</h1>
            <h2>Create Account</h2>
            <p> All fields are required</p>

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


              </form>
                
        </main>
    </body>
</html>