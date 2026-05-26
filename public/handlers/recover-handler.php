<?php

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/services/UserService.php';

if (!isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit();
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
$dbUser = UserService::getById($conn, $userId);

if (!$dbUser || !UserService::isInRecovery($dbUser)) {
    header('Location: /index.php');
    exit();
}

UserService::recoverAccount($conn, $userId);

// Clear deleted_at from session so middleware stops redirecting
$_SESSION['user']['deleted_at'] = null;

header('Location: /settings.php?success=account_recovered');
exit();
