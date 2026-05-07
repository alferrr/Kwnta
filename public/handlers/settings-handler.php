<?php

session_start();

require_once __DIR__ . '/../../../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../src/services/UserService.php';

$user   = $_SESSION['user'];
$userId = $user['id'];
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'update_profile':
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if (empty($firstname) || empty($email)) {
            header('Location: /settings.php?error=fields_required');
            exit();
        }

        $result = UserService::updateProfile($conn, $userId, $firstname, $lastname, $email);

        if ($result['success']) {
            // Refresh session user data
            $updated = UserService::getById($conn, $userId);
            $_SESSION['user'] = array_merge($_SESSION['user'], $updated);
            header('Location: /settings.php?success=profile_updated');
        } else {
            header('Location: /settings.php?error=' . urlencode($result['message']));
        }
        exit();

    case 'update_password':
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            header('Location: /settings.php?tab=security&error=passwords_mismatch');
            exit();
        }

        $result = UserService::updatePassword($conn, $userId, $current, $new);

        if ($result['success']) {
            header('Location: /settings.php?tab=security&success=password_changed');
        } else {
            header('Location: /settings.php?tab=security&error=' . urlencode($result['message']));
        }
        exit();

    case 'delete_account':
        $password = $_POST['confirm_delete_password'] ?? '';
        $result   = UserService::deleteAccount($conn, $userId, $password);

        if ($result['success']) {
            session_destroy();
            header('Location: /index.php?deleted=1');
        } else {
            header('Location: /settings.php?tab=danger&error=' . urlencode($result['message']));
        }
        exit();

    default:
        header('Location: /settings.php');
        exit();
}
