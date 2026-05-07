<?php

session_start();

require_once __DIR__ . '/../../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/services/ExpenseService.php';

$user   = $_SESSION['user'];
$userId = $user['id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $groupId     = (int)($_POST['group_id'] ?? 0);
        $paidBy      = (int)($_POST['paid_by'] ?? $userId);
        $description = trim($_POST['description'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $memberIds   = $_POST['members'] ?? [];

        if (!$groupId || empty($description) || $amount <= 0 || empty($memberIds)) {
            header("Location: /group.php?id={$groupId}&error=invalid_expense");
            exit();
        }

        ExpenseService::createExpense($conn, $groupId, $paidBy, $description, $amount, $memberIds);
        header("Location: /group.php?id={$groupId}&status=expense_added");
        exit();

    case 'delete':
        $expenseId = (int)($_POST['expense_id'] ?? 0);
        $groupId   = (int)($_POST['group_id'] ?? 0);

        if ($expenseId) {
            ExpenseService::deleteExpense($conn, $expenseId);
        }

        header("Location: /group.php?id={$groupId}");
        exit();

    default:
        header('Location: /dashboard.php');
        exit();
}
