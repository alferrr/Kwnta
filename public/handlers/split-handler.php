<?php

session_start();

require_once __DIR__ . '/../../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/services/GroupService.php';
require_once __DIR__ . '/../../src/services/ExpenseService.php';

$user   = $_SESSION['user'];
$userId = $user['id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'toggle_paid':
        $expenseId = (int)($_POST['expense_id'] ?? 0);
        $memberId  = (int)($_POST['member_id'] ?? 0);
        $groupId   = (int)($_POST['group_id'] ?? 0);

        if (!$expenseId || !$memberId || !$groupId) {
            header("Location: /group.php?id={$groupId}&error=invalid");
            exit();
        }

        // Only admins can toggle
        if (!GroupService::isAdmin($conn, $groupId, $userId)) {
            header("Location: /group.php?id={$groupId}&error=unauthorized");
            exit();
        }

        ExpenseService::togglePaid($conn, $expenseId, $memberId);
        header("Location: /group.php?id={$groupId}");
        exit();

    case 'promote_admin':
        $groupId      = (int)($_POST['group_id'] ?? 0);
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if (!$groupId || !$targetUserId) {
            header("Location: /group.php?id={$groupId}&error=invalid");
            exit();
        }

        // Only admins can promote
        if (!GroupService::isAdmin($conn, $groupId, $userId)) {
            header("Location: /group.php?id={$groupId}&error=unauthorized");
            exit();
        }

        GroupService::promoteToAdmin($conn, $groupId, $targetUserId);
        header("Location: /group.php?id={$groupId}&status=promoted");
        exit();

    default:
        header('Location: /dashboard.php');
        exit();
}
