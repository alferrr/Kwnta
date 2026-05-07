<?php


class GroupService
{
    public static function getGroupsByUser(PDO $conn, int $userId): array
    {
        // Returns active groups only - use getArchivedGroupsByUser for archived
        return self::getActiveGroupsByUser($conn, $userId);
    }

    public static function getGroupById(PDO $conn, int $groupId): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM `groups` WHERE id = ?");
        $stmt->execute([$groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function createGroup(PDO $conn, int $userId, string $name, string $icon = 'group'): int
    {
        $stmt = $conn->prepare("INSERT INTO `groups` (name, icon, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$name, $icon, $userId]);
        $groupId = (int)$conn->lastInsertId();

        // Auto-add creator as admin
        $stmt2 = $conn->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
        $stmt2->execute([$groupId, $userId]);

        return $groupId;
    }

    public static function addMemberByEmail(PDO $conn, int $groupId, string $email): bool
    {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        // Check already a member
        $check = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $check->execute([$groupId, $user['id']]);
        if ($check->fetch()) {
            return false;
        }

        $stmt2 = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
        return $stmt2->execute([$groupId, $user['id']]);
    }

    public static function getMembers(PDO $conn, int $groupId): array
    {
        $stmt = $conn->prepare("
            SELECT u.id, COALESCE(u.firstname, '') AS firstname, COALESCE(u.lastname, '') AS lastname, u.email
            FROM users u
            INNER JOIN group_members gm ON gm.user_id = u.id
            WHERE gm.group_id = ?
            ORDER BY u.firstname
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function getUserRole(PDO $conn, int $groupId, int $userId): string
    {
        $stmt = $conn->prepare("
            SELECT role FROM group_members WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$groupId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['role'] : 'member';
    }

    public static function isAdmin(PDO $conn, int $groupId, int $userId): bool
    {
        return self::getUserRole($conn, $groupId, $userId) === 'admin';
    }

    public static function promoteToAdmin(PDO $conn, int $groupId, int $userId): bool
    {
        $stmt = $conn->prepare("
            UPDATE group_members SET role = 'admin' WHERE group_id = ? AND user_id = ?
        ");
        return $stmt->execute([$groupId, $userId]);
    }

    public static function getMembersWithRole(PDO $conn, int $groupId): array
    {
        $stmt = $conn->prepare("
            SELECT u.id,
                   COALESCE(u.firstname, '') AS firstname,
                   COALESCE(u.lastname, '') AS lastname,
                   u.email,
                   gm.role
            FROM users u
            INNER JOIN group_members gm ON gm.user_id = u.id
            WHERE gm.group_id = ?
            ORDER BY gm.role DESC, u.firstname
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Archive ───────────────────────────────────────────

    public static function archiveGroup(PDO $conn, int $groupId, int $userId): array
    {
        if (!self::isAdmin($conn, $groupId, $userId)) {
            return ['success' => false, 'message' => 'Only admins can archive groups.'];
        }
        $stmt = $conn->prepare("
            UPDATE `groups` SET status = 'archived', archived_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$groupId]);
        return ['success' => true];
    }

    public static function unarchiveGroup(PDO $conn, int $groupId, int $userId): array
    {
        if (!self::isAdmin($conn, $groupId, $userId)) {
            return ['success' => false, 'message' => 'Only admins can unarchive groups.'];
        }
        $stmt = $conn->prepare("
            UPDATE `groups` SET status = 'active', archived_at = NULL WHERE id = ?
        ");
        $stmt->execute([$groupId]);
        return ['success' => true];
    }

    public static function getArchivedGroupsByUser(PDO $conn, int $userId): array
    {
        $stmt = $conn->prepare("
            SELECT g.*, COUNT(DISTINCT gm.user_id) AS member_count
            FROM `groups` g
            INNER JOIN group_members gm ON gm.group_id = g.id
            WHERE g.status = 'archived'
              AND g.id IN (SELECT group_id FROM group_members WHERE user_id = ?)
            GROUP BY g.id
            ORDER BY g.archived_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Override getGroupsByUser to only return active groups
    public static function getActiveGroupsByUser(PDO $conn, int $userId): array
    {
        $stmt = $conn->prepare("
            SELECT g.*, COUNT(DISTINCT gm.user_id) AS member_count
            FROM `groups` g
            INNER JOIN group_members gm ON gm.group_id = g.id
            WHERE g.status = 'active'
              AND g.id IN (SELECT group_id FROM group_members WHERE user_id = ?)
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Leave Requests ────────────────────────────────────

    public static function requestLeave(PDO $conn, int $groupId, int $userId, string $message = ''): array
    {
        // Can't leave if you're the only admin
        $admins = $conn->prepare("
            SELECT COUNT(*) FROM group_members WHERE group_id = ? AND role = 'admin'
        ");
        $admins->execute([$groupId]);
        $adminCount = (int)$admins->fetchColumn();

        if ($adminCount === 1 && self::isAdmin($conn, $groupId, $userId)) {
            return ['success' => false, 'message' => 'You are the only admin. Promote another member before leaving.'];
        }

        // Check for existing pending request
        $check = $conn->prepare("
            SELECT id FROM leave_requests WHERE group_id = ? AND user_id = ? AND status = 'pending'
        ");
        $check->execute([$groupId, $userId]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'You already have a pending leave request for this group.'];
        }

        $stmt = $conn->prepare("
            INSERT INTO leave_requests (group_id, user_id, message) VALUES (?, ?, ?)
        ");
        $stmt->execute([$groupId, $userId, $message]);
        return ['success' => true];
    }

    public static function getPendingLeaveRequests(PDO $conn, int $groupId): array
    {
        $stmt = $conn->prepare("
            SELECT lr.*,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,'')) AS user_name,
                   u.email AS user_email
            FROM leave_requests lr
            JOIN users u ON u.id = lr.user_id
            WHERE lr.group_id = ? AND lr.status = 'pending'
            ORDER BY lr.created_at ASC
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllLeaveRequestsForAdmin(PDO $conn, int $adminUserId): array
    {
        $stmt = $conn->prepare("
            SELECT lr.*,
                   CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,'')) AS user_name,
                   u.email AS user_email,
                   g.name AS group_name,
                   g.icon AS group_icon
            FROM leave_requests lr
            JOIN users u ON u.id = lr.user_id
            JOIN `groups` g ON g.id = lr.group_id
            JOIN group_members gm ON gm.group_id = lr.group_id AND gm.user_id = ?
            WHERE lr.status = 'pending'
              AND gm.role = 'admin'
            ORDER BY lr.created_at ASC
        ");
        $stmt->execute([$adminUserId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getMyLeaveRequest(PDO $conn, int $groupId, int $userId): ?array
    {
        $stmt = $conn->prepare("
            SELECT * FROM leave_requests
            WHERE group_id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$groupId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function resolveLeaveRequest(PDO $conn, int $requestId, int $adminId, string $decision): array
    {
        // Fetch the request
        $stmt = $conn->prepare("SELECT * FROM leave_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            return ['success' => false, 'message' => 'Request not found or already resolved.'];
        }

        if (!self::isAdmin($conn, $request['group_id'], $adminId)) {
            return ['success' => false, 'message' => 'Only admins can resolve leave requests.'];
        }

        $status = $decision === 'approve' ? 'approved' : 'rejected';

        $update = $conn->prepare("
            UPDATE leave_requests
            SET status = ?, resolved_at = NOW(), resolved_by = ?
            WHERE id = ?
        ");
        $update->execute([$status, $adminId, $requestId]);

        if ($decision === 'approve') {
            $remove = $conn->prepare("
                DELETE FROM group_members WHERE group_id = ? AND user_id = ?
            ");
            $remove->execute([$request['group_id'], $request['user_id']]);
        }

        return ['success' => true, 'status' => $status];
    }

    public static function cancelLeaveRequest(PDO $conn, int $groupId, int $userId): bool
    {
        $stmt = $conn->prepare("
            DELETE FROM leave_requests WHERE group_id = ? AND user_id = ? AND status = 'pending'
        ");
        return $stmt->execute([$groupId, $userId]);
    }
}
