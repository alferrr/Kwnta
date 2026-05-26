<?php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/GroupService.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';
$user = $_SESSION['user'];
$userId = $user['id'];

$groups       = GroupService::getGroupsByUser($conn, $userId);
$totalGroups  = count($groups);
$recentGroups = array_slice($groups, 0, 5);

$totalOwed = 0;
$totalOwe  = 0;
foreach ($groups as $g) {
    $balances = ExpenseService::getBalances($conn, $g['id'], $userId);
    foreach ($balances as $b) {
        if ($b['amount'] > 0) {
            $totalOwed += $b['amount'];
        } else {
            $totalOwe += abs($b['amount']);
        }
    }
}

$recentExpenses = ExpenseService::getRecentExpenses($conn, $userId, 6);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';
$pageCSS     = ['dashboard.css', 'groups.css'];

ob_start();
?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($user['firstname'] ?? 'there') ?></h1>
            <p class="page-subtitle">Here's what's happening across your groups</p>
        </div>
        <div class="page-actions">
            <a href="groups.php" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Group
            </a>
        </div>
    </div>

    <div class="page-body">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">groups_3</span></div>
                <div class="stat-value"><?= $totalGroups ?></div>
                <div class="stat-label">Active Groups</div>
            </div>
            <div class="stat-card">
                <div class="stat-ico"><span class="material-symbols-outlined">credit_card_clock</span></div>
                <div class="stat-value">₱<?= number_format($totalOwe, 2) ?></div>
                <div class="stat-label">You Owe</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">credit_card</span></div>
                <div class="stat-value">₱<?= number_format($totalOwed, 2) ?></div>
                <div class="stat-label">You're Owed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><span class="material-symbols-outlined">account_balance</span></div>
                <div class="stat-value">₱<?= number_format($totalOwed - $totalOwe, 2) ?></div>
                <div class="stat-label">Net Balance</div>
            </div>
        </div>

        <div class="dashboard-grid">

            <!-- Recent Groups -->
            <div class="card grp">
                <div class="card-header">
                    <span class="card-title">Recent Groups</span>
                    <a href="groups.php" class="card-link">View all →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentGroups)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🫂</div>
                        <div class="empty-title">No groups yet</div>
                        <div class="empty-desc">Create a group to start tracking shared expenses</div>
                    </div>
                    <?php else: ?>
                    <div class="group-list">
                        <?php foreach ($recentGroups as $g):
                            $bal = ExpenseService::getMyBalance($conn, $g['id'], $userId);
                            $balClass = $bal > 0 ? 'owed' : ($bal < 0 ? 'owe' : 'settled');
                            $balLabel = $bal > 0 ? '+₱' . number_format($bal, 2) : ($bal < 0 ? '-₱' . number_format(abs($bal), 2) : 'Settled');
                            ?>
                        <a href="group.php?id=<?= $g['id'] ?>" class="group-row">
                            <!-- <div class="group-emoji"><?= htmlspecialchars($g['emoji'] ?? '👥') ?></div> -->
                            <div class="group-info">
                                <div class="group-name"><?= htmlspecialchars($g['name']) ?></div>
                                <div class="group-meta"><?= $g['member_count'] ?? 0 ?> members</div>
                            </div>
                            <div class="group-amount <?= $balClass ?>"><?= $balLabel ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card act">
                <div class="card-header">
                    <span class="card-title">Recent Activity</span>
                </div>
                <div class="card-body">
                    <?php if (empty($recentExpenses)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🧾</div>
                        <div class="empty-title">No expenses yet</div>
                        <div class="empty-desc">Add an expense to a group to get started</div>
                    </div>
                    <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recentExpenses as $e): ?>
                        <div class="activity-item">
                            <div class="activity-dot <?= $e['paid_by'] == $userId ? 'green' : 'red' ?>"></div>
                            <div>
                                <div class="activity-text">
                                    <strong><?= htmlspecialchars($e['paid_by_name']) ?></strong>
                                    paid for <strong><?= htmlspecialchars($e['description']) ?></strong>
                                    — ₱<?= number_format($e['amount'], 2) ?>
                                </div>
                                <div class="activity-time"><?= htmlspecialchars($e['group_name']) ?> · <?= date('M j', strtotime($e['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <?php
    $content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>