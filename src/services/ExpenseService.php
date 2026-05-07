<?php


class ExpenseService
{
    public static function getExpensesByGroup(PDO $conn, int $groupId): array
    {
        $stmt = $conn->prepare("
            SELECT e.*,
                   CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS paid_by_name,
                   COUNT(es.user_id) AS split_count,
                   SUM(es.paid) AS paid_count
            FROM expenses e
            JOIN users u ON u.id = e.paid_by
            LEFT JOIN expense_splits es ON es.expense_id = e.id
            WHERE e.group_id = ?
            GROUP BY e.id
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getSplitsForExpense(PDO $conn, int $expenseId): array
    {
        $stmt = $conn->prepare("
            SELECT es.*,
                   CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS member_name
            FROM expense_splits es
            JOIN users u ON u.id = es.user_id
            WHERE es.expense_id = ?
        ");
        $stmt->execute([$expenseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function togglePaid(PDO $conn, int $expenseId, int $memberId): bool
    {
        $stmt = $conn->prepare("
            UPDATE expense_splits
            SET paid = IF(paid = 1, 0, 1)
            WHERE expense_id = ? AND user_id = ?
        ");
        return $stmt->execute([$expenseId, $memberId]);
    }

    public static function setPaid(PDO $conn, int $expenseId, int $memberId, int $paid): bool
    {
        $stmt = $conn->prepare("
            UPDATE expense_splits SET paid = ? WHERE expense_id = ? AND user_id = ?
        ");
        return $stmt->execute([$paid, $expenseId, $memberId]);
    }

    public static function createExpense(
        PDO $conn,
        int $groupId,
        int $paidBy,
        string $description,
        float $amount,
        array $memberIds
    ): bool {
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO expenses (group_id, paid_by, description, amount)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$groupId, $paidBy, $description, $amount]);
            $expenseId = (int)$conn->lastInsertId();

            $share = $amount / count($memberIds);
            $split = $conn->prepare("
                INSERT INTO expense_splits (expense_id, user_id, share) VALUES (?, ?, ?)
            ");
            foreach ($memberIds as $memberId) {
                $split->execute([$expenseId, (int)$memberId, $share]);
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            return false;
        }
    }

    public static function deleteExpense(PDO $conn, int $expenseId): bool
    {
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        return $stmt->execute([$expenseId]);
    }

    /**
     * Get net balance for the current user in a group.
     * Positive = others owe you. Negative = you owe others.
     */
    public static function getMyBalance(PDO $conn, int $groupId, int $userId): float
    {
        // Amount paid by user
        $paid = $conn->prepare("
            SELECT COALESCE(SUM(e.amount), 0)
            FROM expenses e
            WHERE e.group_id = ? AND e.paid_by = ?
        ");
        $paid->execute([$groupId, $userId]);
        $totalPaid = (float)$paid->fetchColumn();

        // User's share
        $owed = $conn->prepare("
            SELECT COALESCE(SUM(es.share), 0)
            FROM expense_splits es
            JOIN expenses e ON e.id = es.expense_id
            WHERE e.group_id = ? AND es.user_id = ?
        ");
        $owed->execute([$groupId, $userId]);
        $totalOwed = (float)$owed->fetchColumn();

        return $totalPaid - $totalOwed;
    }

    /**
     * Returns per-person balances relative to current user.
     * Positive amount = that person owes you. Negative = you owe them.
     */
    public static function getBalances(PDO $conn, int $groupId, int $userId): array
    {
        $members = $conn->prepare("
            SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) AS name
            FROM users u
            JOIN group_members gm ON gm.user_id = u.id
            WHERE gm.group_id = ? AND u.id != ?
        ");
        $members->execute([$groupId, $userId]);
        $others = $members->fetchAll(PDO::FETCH_ASSOC);

        $balances = [];
        foreach ($others as $other) {
            $otherId = $other['id'];

            // What current user paid that includes other
            $userPaidForOther = $conn->prepare("
                SELECT COALESCE(SUM(es.share), 0)
                FROM expenses e
                JOIN expense_splits es ON es.expense_id = e.id
                WHERE e.group_id = ? AND e.paid_by = ? AND es.user_id = ?
            ");
            $userPaidForOther->execute([$groupId, $userId, $otherId]);
            $upfo = (float)$userPaidForOther->fetchColumn();

            // What other paid that includes current user
            $otherPaidForUser = $conn->prepare("
                SELECT COALESCE(SUM(es.share), 0)
                FROM expenses e
                JOIN expense_splits es ON es.expense_id = e.id
                WHERE e.group_id = ? AND e.paid_by = ? AND es.user_id = ?
            ");
            $otherPaidForUser->execute([$groupId, $otherId, $userId]);
            $opfu = (float)$otherPaidForUser->fetchColumn();

            $net = $upfo - $opfu;
            if (abs($net) > 0.001) {
                $balances[] = [
                    'id'     => $otherId,
                    'name'   => $other['name'],
                    'amount' => $net,   // positive = they owe you, negative = you owe them
                ];
            }
        }

        return $balances;
    }

    public static function getRecentExpenses(PDO $conn, int $userId, int $limit = 6): array
    {
        $limit = (int)$limit;
        $stmt = $conn->prepare("
            SELECT e.*,
                   CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS paid_by_name,
                   g.name AS group_name
            FROM expenses e
            JOIN users u ON u.id = e.paid_by
            JOIN `groups` g ON g.id = e.group_id
            WHERE e.group_id IN (
                SELECT group_id FROM group_members WHERE user_id = ?
            )
            ORDER BY e.created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllExpensesByUser(PDO $conn, int $userId): array
    {
        $stmt = $conn->prepare("
            SELECT e.*,
                   CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, '')) AS paid_by_name,
                   g.name AS group_name,
                   g.id AS group_id,
                   COUNT(es.user_id) AS split_count,
                   SUM(es.paid) AS paid_count
            FROM expenses e
            JOIN users u ON u.id = e.paid_by
            JOIN `groups` g ON g.id = e.group_id
            LEFT JOIN expense_splits es ON es.expense_id = e.id
            WHERE e.group_id IN (
                SELECT group_id FROM group_members WHERE user_id = ?
            )
            GROUP BY e.id
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllBalancesByUser(PDO $conn, int $userId): array
    {
        // Get all groups the user belongs to
        $groupStmt = $conn->prepare("
            SELECT g.id, g.name
            FROM `groups` g
            JOIN group_members gm ON gm.group_id = g.id
            WHERE gm.user_id = ?
        ");
        $groupStmt->execute([$userId]);
        $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

        $allBalances = [];
        foreach ($groups as $group) {
            $balances = self::getBalances($conn, $group['id'], $userId);
            foreach ($balances as $b) {
                $key = min($userId, $b['id']) . '_' . max($userId, $b['id']);
                if (!isset($allBalances[$key])) {
                    $allBalances[$key] = [
                        'id'         => $b['id'],
                        'name'       => $b['name'],
                        'amount'     => 0,
                        'groups'     => [],
                    ];
                }
                $allBalances[$key]['amount'] += $b['amount'];
                $allBalances[$key]['groups'][] = $group['name'];
            }
        }

        // Sort: owed to you first, then you owe
        usort($allBalances, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        return array_values($allBalances);
    }
}
