<?php

class UserService
{
    public static function getById(PDO $conn, int $userId): ?array
    {
        $stmt = $conn->prepare("SELECT id, firstname, lastname, email, created_at, deleted_at FROM users WHERE id = ?");
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

        $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?")->execute([$userId]);
        return ['success' => true];
    }

    public static function isInRecovery(array $user): bool
    {
        if (empty($user['deleted_at'])) {
            return false;
        }
        $deletedAt = strtotime($user['deleted_at']);
        $daysLeft  = ceil(($deletedAt + (30 * 86400) - time()) / 86400);
        return $daysLeft > 0;
    }

    public static function daysUntilPermanentDelete(array $user): int
    {
        if (empty($user['deleted_at'])) {
            return 0;
        }
        $deletedAt = strtotime($user['deleted_at']);
        return max(0, (int)ceil(($deletedAt + (30 * 86400) - time()) / 86400));
    }

    public static function recoverAccount(PDO $conn, int $userId): bool
    {
        $stmt = $conn->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public static function purgeExpiredAccounts(PDO $conn): int
    {
        $stmt = $conn->prepare("
            SELECT id FROM users
            WHERE deleted_at IS NOT NULL
            AND deleted_at < NOW() - INTERVAL 30 DAY
        ");
        $stmt->execute();
        $expired = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($expired)) {
            return 0;
        }

        foreach ($expired as $id) {
            $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
        return count($expired);
    }

}
