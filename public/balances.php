<?php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';
require_once __DIR__ . '/../src/services/GroupService.php';

$user     = $_SESSION['user'];
$userId   = $user['id'];
$groups   = GroupService::getGroupsByUser($conn, $userId);
$balances = ExpenseService::getAllBalancesByUser($conn, $userId);

$totalOwed = array_sum(array_map(fn ($b) => $b['amount'] > 0 ? $b['amount'] : 0, $balances));
$totalOwe  = array_sum(array_map(fn ($b) => $b['amount'] < 0 ? abs($b['amount']) : 0, $balances));
$net       = $totalOwed - $totalOwe;

// Per-group balances
$groupBalances = [];
foreach ($groups as $g) {
    $gb = ExpenseService::getBalances($conn, $g['id'], $userId);
    if (!empty($gb)) {
        $groupBalances[] = [
            'group'    => $g,
            'balances' => $gb,
        ];
    }
}

$pageTitle   = 'Balances';
$currentPage = 'balances';
$pageCSS     = ['groups.css', 'expenses.css', 'balances.css'];

ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Balances</h1>
        <p class="page-subtitle">Your net position across all groups</p>
    </div>
</div>

<div class="page-body">

    <!-- Net summary cards -->
    <div class="balance-summary-grid">
        <div class="balance-summary-card balance-summary-card--owed">
            <div class="balance-summary-icon">
                <span class="material-symbols-outlined">credit_card_clock</span>
            </div>
            <div>
                <div class="balance-summary-value">₱<?= number_format($totalOwed, 2) ?></div>
                <div class="balance-summary-label">Owed to you</div>
            </div>
        </div>

        <div class="balance-summary-card balance-summary-card--net <?= $net >= 0 ? 'positive' : 'negative' ?>">
            <div class="balance-summary-icon">
                <span class="material-symbols-outlined icon-lg">account_balance </span>
            </div>
            <div>
                <div class="balance-summary-value">
                    <?= $net >= 0 ? '+' : '' ?>₱<?= number_format(abs($net), 2) ?>
                </div>
                <div class="balance-summary-label">Net balance</div>
            </div>
        </div>

        <div class="balance-summary-card balance-summary-card--owe">
            <div class="balance-summary-icon">
                <span class="material-symbols-outlined icon-lg">arrow_upward</span>
            </div>
            <div>
                <div class="balance-summary-value">₱<?= number_format($totalOwe, 2) ?></div>
                <div class="balance-summary-label">You owe</div>
            </div>
        </div>
    </div>

    <?php if (empty($balances)): ?>
    <div class="expenses-empty" style="margin-top: 48px;">
        <span class="material-symbols-outlined icon-xl" style="color:var(--border-strong);">check_circle</span>
        <div class="empty-title">All settled up</div>
        <div class="empty-desc">You have no outstanding balances across any group</div>
    </div>

    <?php else: ?>

    <div class="balances-layout">

        <!-- People you owe / owe you -->
        <div class="balances-main">
            <div class="balances-section-title">People</div>

            <?php foreach ($balances as $b):
                $isOwe   = $b['amount'] < 0;
                $absAmt  = abs($b['amount']);
                $initials = strtoupper(substr($b['name'], 0, 1));
                ?>
            <div class="balance-person-card <?= $isOwe ? 'balance-person-card--owe' : 'balance-person-card--owed' ?>">
                <div class="balance-person-avatar"><?= $initials ?></div>
                <div class="balance-person-info">
                    <div class="balance-person-name"><?= htmlspecialchars($b['name']) ?></div>
                    <div class="balance-person-groups">
                        <span class="material-symbols-outlined icon-sm">group</span>
                        <?= htmlspecialchars(implode(', ', array_unique($b['groups']))) ?>
                    </div>
                </div>
                <div class="balance-person-right">
                    <div class="balance-person-amount <?= $isOwe ? 'owe' : 'owed' ?>">
                        <?= $isOwe ? '-' : '+' ?>₱<?= number_format($absAmt, 2) ?>
                    </div>
                    <div class="balance-person-label">
                        <?= $isOwe ? 'You owe them' : 'They owe you' ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Per-group breakdown -->
        <div class="balances-sidebar">
            <div class="balances-section-title">By Group</div>

            <?php if (empty($groupBalances)): ?>
            <div class="balance-panel">
                <div style="padding: 20px; text-align:center; font-size:0.82rem; color:var(--text-muted);">
                    No group balances
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($groupBalances as $gb): ?>
            <div class="balance-panel" style="margin-bottom: 14px;">
                <div class="balance-panel-header" style="display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined icon-sm" style="color:var(--text-muted);">group</span>
                    <?= htmlspecialchars($gb['group']['name']) ?>
                </div>
                <?php foreach ($gb['balances'] as $b):
                    $isOwe  = $b['amount'] < 0;
                    $absAmt = abs($b['amount']);
                    ?>
                <div class="balance-entry">
                    <div class="balance-names">
                        <?php if ($isOwe): ?>
                        You owe <strong><?= htmlspecialchars($b['name']) ?></strong>
                        <?php else: ?>
                        <strong><?= htmlspecialchars($b['name']) ?></strong> owes you
                        <?php endif; ?>
                    </div>
                    <div class="balance-amount-row">
                        <span class="balance-amount <?= $isOwe ? 'owe' : 'owed' ?>">
                            ₱<?= number_format($absAmt, 2) ?>
                        </span>
                        <span class="balance-tag <?= $isOwe ? 'owe' : 'owed' ?>">
                            <?= $isOwe ? 'You owe' : 'Owed to you' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="padding: 10px 20px;">
                    <a href="/group.php?id=<?= $gb['group']['id'] ?>" class="splits-group-link">
                        <span class="material-symbols-outlined icon-sm">open_in_new</span>
                        Go to group
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>