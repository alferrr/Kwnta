<?php

// src/services/UserService.php

class UserService
{
    public static function getById(PDO $conn, int $userId): ?array
    {
        $stmt = $conn->prepare("SELECT id, firstname, lastname, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function updateProfile(PDO $conn, int $userId, string $firstname, string $lastname, string $email): array
    {
        // Check email taken by another user
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'That email is already in use by another account.'];
        }

        $stmt = $conn->prepare("
            UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?
        ");
        $stmt->execute([$firstname, $lastname, $email, $userId]);

        return ['success' => true, 'message' => 'Profile updated successfully.'];
    }

    public static function updatePassword(PDO $conn, int $userId, string $currentPassword, string $newPassword): array
    {
        // Fetch current hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($currentPassword, $row['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$hash, $userId]);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }

    public static function deleteAccount(PDO $conn, int $userId, string $password): array
    {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($password, $row['password'])) {
            return ['success' => false, 'message' => 'Incorrect password. Account not deleted.'];
        }

        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        return ['success' => true];
    }
}
