<?php


include(__DIR__ . "/../../config/db.php");
class AuthService
{
    public static function login($conn, $email, $password)
    {
        $stmt = $conn->prepare("SELECT * FROM users where email=?");
        $stmt -> execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (password_verify($password, $user["password"])) {
            return $user;
        }

        return false;
    }

    public static function register($conn, $email, $password, $firstname, $lastname)
    {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check -> execute([$email]);

        if ($check->rowCount() > 0) {
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
        INSERT INTO users (
        firstname, lastname, email, password) 
        VALUES (?, ?, ?, ?)");

        return $stmt->execute([$firstname, $lastname, $email, $hashedPassword]);

    }

}
