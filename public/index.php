<?php
$error = $_GET['error'] ?? '';
$errorMessages = [
    'required' => 'Please enter your email and password.',
    'invalid'  => 'Invalid email or password.',
];
$errorText = $errorMessages[$error] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/css/login.css">
    <link rel="shortcut icon" href="./assets/images/logo.png" type="image/x-icon">
    <title>Kwnta</title>
</head>
<body>
<form action="./handlers/login-handler.php" method="POST">
    <div class="container">

        <div class="head">
            <h2>Welcome to kwnta</h2>
            <p>Start your experience with Kwnta by signing in or signing up</p>
        </div>

        <div class="wrapper">
            <button style="background-color: #00000012; color: #000" disabled>Sign In</button>
            <button onclick="window.location.href='./register.php'" type="button">Sign Up</button>
        </div>

        <?php if ($errorText): ?>
        <div class="error-msg">
            <span><?= htmlspecialchars($errorText) ?></span>
        </div>
        <?php endif; ?>

        <div class="input">
            <label for="email">Enter your email</label>
            <input type="text" placeholder="Enter your email" name="email"
                   value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
        </div>

        <div class="input">
            <label for="password">Enter your password</label>
            <input type="password" placeholder="Password" name="password">
        </div>

        <button class="login-btn" type="submit">Login</button>
    </div>
</form>
<aside>
    <div class="container"></div>
</aside>
</body>
</html>