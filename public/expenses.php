<?php
// public/expenses.php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';
require_once __DIR__ . '/../src/services/GroupService.php';

$user     = $_SESSION['user'];
$userId   = $user['id'];
$expenses = ExpenseService::getAllExpensesByUser($conn, $userId);
$groups   = GroupService::getGroupsByUser($conn, $userId);

// Filter by group if requested
$filterGroup = (int)($_GET['group'] ?? 0);
if ($filterGroup) {
    $expenses = array_filter($expenses, fn ($e) => (int)$e['group_id'] === $filterGroup);
    $expenses = array_values($expenses);
}

// Group expenses by date
$grouped = [];
foreach ($expenses as $e) {
    $day = date('Y-m-d', strtotime($e['created_at']));
    $grouped[$day][] = $e;
}

$totalAmount = array_sum(array_column($expenses, 'amount'));

$pageTitle   = 'Expenses';
$currentPage = 'expenses';
$pageCSS     = ['groups.css', 'expenses.css'];

ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Expenses</h1>
        <p class="page-subtitle"><?= count($expenses) ?> expense<?= count($expenses) !== 1 ? 's' : '' ?> · ₱<?= number_format($totalAmount, 2) ?> total</p>
    </div>
    <div class="page-actions">
        <!-- Group filter -->
        <div class="filter-select-wrap">
            <span class="material-symbols-outlined icon-sm" style="color:var(--text-muted);">filter_list</span>
            <select class="filter-select" onchange="window.location.href='/expenses.php' + (this.value ? '?group=' + this.value : '')">
                <option value="">All Groups</option>
                <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $filterGroup === (int)$g['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="page-body">

    <?php if (empty($expenses)): ?>
    <div class="expenses-empty">
        <span class="material-symbols-outlined icon-xl" style="color:var(--border-strong);">receipt_long</span>
        <div class="empty-title">No expenses yet</div>
        <div class="empty-desc">Expenses you add to any group will appear here</div>
    </div>

    <?php else: ?>

    <!-- Summary bar -->
    <div class="expense-summary-bar">
        <div class="expense-summary-item">
            <span class="material-symbols-outlined icon-sm">payments</span>
            <div>
                <div class="expense-summary-value">₱<?= number_format($totalAmount, 2) ?></div>
                <div class="expense-summary-label">Total Spent</div>
            </div>
        </div>
        <div class="expense-summary-divider"></div>
        <div class="expense-summary-item">
            <span class="material-symbols-outlined icon-sm">receipt_long</span>
            <div>
                <div class="expense-summary-value"><?= count($expenses) ?></div>
                <div class="expense-summary-label">Transactions</div>
            </div>
        </div>
        <div class="expense-summary-divider"></div>
        <div class="expense-summary-item">
            <span class="material-symbols-outlined icon-sm">group</span>
            <div>
                <div class="expense-summary-value"><?= count($groups) ?></div>
                <div class="expense-summary-label">Groups</div>
            </div>
        </div>
    </div>

    <!-- Expense list -->
    <div class="expense-full-list">
        <?php foreach ($grouped as $day => $dayExpenses): ?>

        <div class="date-divider">
            <span class="date-divider-label"><?= date('F j, Y', strtotime($day)) ?></span>
            <div class="date-divider-line"></div>
            <span class="date-divider-count"><?= count($dayExpenses) ?></span>
        </div>

        <?php foreach ($dayExpenses as $e):
            $myShare   = $e['amount'] / max(1, $e['split_count']);
            $allPaid   = (int)$e['split_count'] > 0 && (int)$e['paid_count'] === (int)$e['split_count'];
            $iPaid     = $e['paid_by'] == $userId;
            $splits    = ExpenseService::getSplitsForExpense($conn, $e['id']);
            ?>
        <div class="expense-full-item">
            <div class="expense-full-icon">
                <span class="material-symbols-outlined icon-md">receipt</span>
            </div>

            <div class="expense-full-info">
                <div class="expense-full-top">
                    <span class="expense-full-desc"><?= htmlspecialchars($e['description']) ?></span>
                    <span class="expense-full-amount">₱<?= number_format($e['amount'], 2) ?></span>
                </div>
                <div class="expense-full-meta">
                    <span class="expense-meta-chip">
                        <span class="material-symbols-outlined icon-sm">group</span>
                        <?= htmlspecialchars($e['group_name']) ?>
                    </span>
                    <span class="expense-meta-chip">
                        <span class="material-symbols-outlined icon-sm"><?= $iPaid ? 'arrow_upward' : 'arrow_downward' ?></span>
                        <?= $iPaid ? 'You paid' : 'Paid by ' . htmlspecialchars($e['paid_by_name']) ?>
                    </span>
                    <span class="expense-meta-chip">
                        <span class="material-symbols-outlined icon-sm">splitscreen</span>
                        ₱<?= number_format($myShare, 2) ?>/person
                    </span>
                </div>
            </div>

            <div class="expense-full-right">
                <span class="paid-badge <?= $allPaid ? 'paid-badge--paid' : 'paid-badge--unpaid' ?>">
                    <?= $allPaid ? 'Settled' : 'Unpaid' ?>
                </span>
                <button class="expense-expand-btn" onclick="toggleSplits('exp-splits-<?= $e['id'] ?>', this)" title="View splits">
                    <span class="material-symbols-outlined icon-sm">expand_more</span>
                </button>
            </div>
        </div>

        <!-- Splits -->
        <div class="expense-splits" id="exp-splits-<?= $e['id'] ?>" style="display:none;">
            <?php foreach ($splits as $s): ?>
            <div class="split-row">
                <div class="split-row-avatar"><?= strtoupper(substr($s['member_name'], 0, 1)) ?></div>
                <span class="split-row-name"><?= htmlspecialchars($s['member_name']) ?></span>
                <span class="split-row-amount">₱<?= number_format($s['share'], 2) ?></span>
                <span class="split-paid-status <?= $s['paid'] ? 'split-paid-status--paid' : 'split-paid-status--unpaid' ?>" style="margin-left:auto;">
                    <?= $s['paid'] ? 'Paid' : 'Unpaid' ?>
                </span>
            </div>
            <?php endforeach; ?>
            <div class="splits-footer">
                <a href="/group.php?id=<?= $e['group_id'] ?>" class="splits-group-link">
                    <span class="material-symbols-outlined icon-sm">open_in_new</span>
                    View in <?= htmlspecialchars($e['group_name']) ?>
                </a>
            </div>
        </div>

        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<script>
function toggleSplits(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? 'block' : 'none';
    const icon = btn.querySelector('.material-symbols-outlined');
    icon.textContent = isHidden ? 'expand_less' : 'expand_more';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>