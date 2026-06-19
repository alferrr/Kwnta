<?php

session_start();

require_once __DIR__ . '/../../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/services/GroupService.php';

$user   = $_SESSION['user'];
$userId = $user['id'];
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create':
        $name = trim($_POST['name'] ?? '');
        $icon = trim($_POST['icon'] ?? 'group');
        if (empty($name)) {
            header('Location: /groups.php?error=name_required');
            exit();
        }
        $groupId = GroupService::createGroup($conn, $userId, $name, $icon);
        header("Location: /group.php?id={$groupId}");
        exit();

    case 'add_member':
        $groupId = (int)($_POST['group_id'] ?? 0);
        $email   = trim($_POST['email'] ?? '');
        if (!$groupId || empty($email)) {
            header("Location: /group.php?id={$groupId}&error=invalid");
            exit();
        }
        $result = GroupService::addMemberByEmail($conn, $groupId, $email);
        $status = $result ? 'member_added' : 'member_not_found';
        header("Location: /group.php?id={$groupId}&status={$status}");
        exit();

    case 'archive_group':
        $groupId = (int)($_POST['group_id'] ?? 0);
        $result  = GroupService::archiveGroup($conn, $groupId, $userId);
        if ($result['success']) {
            header('Location: /groups.php?status=archived');
        } else {
            header('Location: /groups.php?error=something_went_wrong');
        }
        exit();

    case 'unarchive_group':
        $groupId = (int)($_POST['group_id'] ?? 0);
        $result  = GroupService::unarchiveGroup($conn, $groupId, $userId);
        if ($result['success']) {
            header('Location: /groups.php?status=restored');
        } else {
            header('Location: /groups.php?error=something_went_wrong');
        }
        exit();

    case 'request_leave':
        $groupId = (int)($_POST['group_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $result  = GroupService::requestLeave($conn, $groupId, $userId, $message);
        if ($result['success']) {
            header('Location: /groups.php?status=leave_requested');
        } else {
            header('Location: /groups.php?error=something_went_wrong');
        }
        exit();

    case 'cancel_leave':
        $groupId = (int)($_POST['group_id'] ?? 0);
        GroupService::cancelLeaveRequest($conn, $groupId, $userId);
        header('Location: /groups.php?status=leave_cancelled');
        exit();

    case 'resolve_leave':
        $requestId = (int)($_POST['request_id'] ?? 0);
        $decision  = $_POST['decision'] ?? '';
        if (!in_array($decision, ['approve', 'reject'])) {
            header('Location: /groups.php');
            exit();
        }
        $result = GroupService::resolveLeaveRequest($conn, $requestId, $userId, $decision);
        if ($result['success']) {
            header('Location: /groups.php?status=leave_' . $result['status']);
        } else {
            header('Location: /groups.php?error=something_went_wrong');
        }
        exit();

    default:
        header('Location: /groups.php');
        exit();
}
