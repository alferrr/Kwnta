<?php
$error = $_GET['error'] ?? '';
$errorMessages = [
    'required' => 'Please fill in all required fields.',
    'exists'   => 'An account with that email already exists.',
];
$errorText = $errorMessages[$error] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="./assets/css/login.css">    <link rel="shortcut icon" href="./assets/images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="./assets/images/logo.png" type="image/x-icon">
    <title>Kwnta | Register</title>
</head>
<body>
<form action="./handlers/register-handler.php" method="POST">
    <div class="container">

        <div class="head">
            <h2>Welcome to kwnta</h2>
            <p>Start your experience with Kwnta by signing in or signing up</p>
        </div>
        
        <div class="wrapper">
            <button type="button" onclick="window.location.href='index.php'">Sign In</button>
            <button style="background-color: #00000012; color: #000" disabled>Sign Up</button>
        </div>

        <div class="names">
            <div class="input">
                <label for="firstname">First name</label>
                <input type="text" name="firstname" placeholder="John">
            </div>

            <div class="input">
                <label for="lastname">Last Name</label>
                <input type="text" name="lastname" placeholder="Doe">
            </div>
        </div>
        <div class="input">
            <label for="email">Enter your email</label>
            <input type="text" placeholder="Enter your email" name="email">
        </div>

        <div class="input">
            <label for="password">Enter your password</label>
            <input type="password" placeholder="Password" name="password">
        </div>

        <button class="login-btn" type="submit">Register</button>
    </div>
</form>
<aside>
    <div class="container"></div>
</aside>
</body>
</html>