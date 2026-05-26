<?php

class AuthMiddleware
{
    public static function handle()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user'])) {
            header('Location: /index.php');
            exit();
        }

        // If account is in the deletion recovery window, show the recovery page
        // Allow account-recovery.php and recover-handler.php to load freely
        $current = $_SERVER['PHP_SELF'] ?? '';
        $allowed = ['/account-recovery.php', '/handlers/recover-handler.php', '/handlers/logout-handler.php'];

        $isAllowed = false;
        foreach ($allowed as $path) {
            if (str_ends_with($current, $path)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            $user = $_SESSION['user'];
            if (!empty($user['deleted_at'])) {
                $deletedAt = strtotime($user['deleted_at']);
                $daysLeft  = ceil(($deletedAt + (30 * 86400) - time()) / 86400);
                if ($daysLeft > 0) {
                    header('Location: /account-recovery.php');
                    exit();
                }
            }
        }
    }
}
