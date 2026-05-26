<?php

include(__DIR__ . "/../services/AuthService.php"); // __DIR__ = .../src/controllers

class AuthController
{
    public function login($conn)
    {
        $email    = trim($_POST["email"] ?? '');
        $password = trim($_POST["password"] ?? '');

        if (empty($email) || empty($password)) {
            header("Location: /index.php?error=required");
            exit();
        }

        $user = AuthService::login($conn, $email, $password);

        if ($user) {
            // Block if past the 30day recovery window
            if (!empty($user['deleted_at'])) {
                $deletedAt = strtotime($user['deleted_at']);
                if (time() > $deletedAt + (30 * 86400)) {
                    header("Location: /index.php?error=account_deleted");
                    exit();
                }
            }

            session_start();
            $_SESSION['user'] = $user;
            header("Location: /dashboard.php");
            exit();
        }

        header("Location: /index.php?error=invalid");
        exit();
    }

    public function register($conn)
    {
        $email     = trim($_POST["email"] ?? '');
        $password  = trim($_POST["password"] ?? '');
        $firstname = trim($_POST["firstname"] ?? '');
        $lastname  = trim($_POST["lastname"] ?? '');

        if (empty($email) || empty($password) || empty($firstname)) {
            header("Location: /register.php?error=required");
            exit();
        }

        $result = AuthService::register($conn, $email, $password, $firstname, $lastname);

        if ($result) {
            $user = AuthService::login($conn, $email, $password);
            $_SESSION["user"] = $user;
            header("Location: /dashboard.php");
            exit();
        }

        header("Location: /register.php?error=exists");
        exit();
    }
}
