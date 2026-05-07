<?php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/GroupService.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';

$user   = $_SESSION['user'];
$userId = $user['id'];
$groupId = (int)($_GET['id'] ?? 0);

if (!$groupId) {
    header('Location: /groups.php');
    exit();
}

$group    = GroupService::getGroupById($conn, $groupId);
$expenses = ExpenseService::getExpensesByGroup($conn, $groupId);
$balances = ExpenseService::getBalances($conn, $groupId, $userId);
$members  = GroupService::getMembersWithRole($conn, $groupId);
$isAdmin  = GroupService::isAdmin($conn, $groupId, $userId);

if (!$group) {
    header('Location: /groups.php');
    exit();
}

$pageTitle   = htmlspecialchars($group['name']);
$currentPage = 'groups';
$pageCSS     = ['groups.css', 'expenses.css'];

ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title" style="display:flex; align-items:center; gap:10px;">
            <span class="material-symbols-outlined" style="font-size:28px; color:var(--text-secondary); font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;"><?= htmlspecialchars($group['icon'] ?? 'group') ?></span>
            <?= htmlspecialchars($group['name']) ?>
        </h1>
        <p class="page-subtitle"><?= count($members) ?> members · <?= count($expenses) ?> expenses</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="openModal('add-member-modal')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            Add Member
        </button>
        <button class="btn btn-primary" onclick="openModal('add-expense-modal')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Expense
        </button>
    </div>
</div>

<div class="page-body">
    <div class="expense-layout">

        <!-- Expenses Column -->
        <div>
            <div class="expense-list-header">
                <span class="expense-section-title">Expenses</span>
            </div>

            <?php if (empty($expenses)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon">🧾</div>
                        <div class="empty-title">No expenses yet</div>
                        <div class="empty-desc">Add the first expense to start tracking</div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <?php
            $grouped = [];
                foreach ($expenses as $e) {
                    $day = date('Y-m-d', strtotime($e['created_at']));
                    $grouped[$day][] = $e;
                }
                foreach ($grouped as $day => $dayExpenses):
                    ?>
            <div class="date-divider">
                <span class="date-divider-label"><?= date('F j, Y', strtotime($day)) ?></span>
                <div class="date-divider-line"></div>
            </div>
            <?php foreach ($dayExpenses as $e):
                $myShare = $e['amount'] / max(1, $e['split_count']);
                $icons = ['🍕','☕','🛒','🚗','🎉','🏠','✈️','🎮','💊','🎓'];
                $icon = $icons[crc32($e['description']) % count($icons)];
                $splits = ExpenseService::getSplitsForExpense($conn, $e['id']);
                $allPaid = count($splits) > 0 && array_sum(array_column($splits, 'paid')) === count($splits);
                ?>
            <div class="expense-item expense-item--expandable">
                <!-- <div class="expense-icon"><?= $icon ?></div> -->
                <div class="expense-info">
                    <div class="expense-desc"><?= htmlspecialchars($e['description']) ?></div>
                    <div class="expense-meta">
                        Paid by <strong><?= htmlspecialchars($e['paid_by_name']) ?></strong>
                        · split <?= $e['split_count'] ?> ways
                    </div>
                </div>
                <div class="expense-right">
                    <div class="expense-amount">₱<?= number_format($e['amount'], 2) ?></div>
                    <div class="expense-share">₱<?= number_format($myShare, 2) ?>/person</div>
                </div>
                <div style="display:flex; align-items:center; gap:8px; margin-left:4px;">
                    <span class="paid-badge <?= $allPaid ? 'paid-badge--paid' : 'paid-badge--unpaid' ?>">
                        <?= $allPaid ? '✓ Settled' : 'Unpaid' ?>
                    </span>
                    <button class="icon-btn" onclick="toggleSplits('splits-<?= $e['id'] ?>')" title="View splits">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <?php if ($isAdmin): ?>
                    <form action="/handlers/expense-handler.php" method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                        <input type="hidden" name="group_id" value="<?= $groupId ?>">
                        <button type="submit" class="icon-btn danger" onclick="return confirm('Delete this expense?')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Splits breakdown -->
            <div class="expense-splits" id="splits-<?= $e['id'] ?>" style="display:none;">
                <?php foreach ($splits as $s): ?>
                <div class="split-row">
                    <div class="split-row-avatar"><?= strtoupper(substr($s['member_name'], 0, 1)) ?></div>
                    <span class="split-row-name"><?= htmlspecialchars($s['member_name']) ?></span>
                    <span class="split-row-amount">₱<?= number_format($s['share'], 2) ?></span>
                    <?php if ($isAdmin): ?>
                    <form action="/handlers/split-handler.php" method="POST" style="margin-left:auto;">
                        <input type="hidden" name="action" value="toggle_paid">
                        <input type="hidden" name="expense_id" value="<?= $e['id'] ?>">
                        <input type="hidden" name="member_id" value="<?= $s['user_id'] ?>">
                        <input type="hidden" name="group_id" value="<?= $groupId ?>">
                        <button type="submit" class="split-paid-btn <?= $s['paid'] ? 'split-paid-btn--paid' : '' ?>">
                            <?= $s['paid'] ? '✓ Paid' : 'Mark Paid' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="split-paid-status <?= $s['paid'] ? 'split-paid-status--paid' : 'split-paid-status--unpaid' ?>" style="margin-left:auto;">
                        <?= $s['paid'] ? '✓ Paid' : 'Unpaid' ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Balances -->
            <div class="balance-panel" style="margin-bottom: 16px;">
                <div class="balance-panel-header">Balances</div>
                <?php if (empty($balances)): ?>
                <div style="padding: 20px; text-align: center; font-size: 0.82rem; color: var(--text-muted);">All settled up ✓</div>
                <?php else: ?>
                <?php foreach ($balances as $b):
                    $isOwe = $b['amount'] < 0;
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
                        <span class="balance-amount <?= $isOwe ? 'owe' : 'owed' ?>">₱<?= number_format($absAmt, 2) ?></span>
                        <span class="balance-tag <?= $isOwe ? 'owe' : 'owed' ?>"><?= $isOwe ? 'You owe' : 'Owed to you' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Members -->
            <div class="balance-panel">
                <div class="balance-panel-header">Members</div>
                <div class="members-list">
                    <?php foreach ($members as $m):
                        $initials = strtoupper(substr($m['firstname'] ?? '', 0, 1) . substr($m['lastname'] ?? '', 0, 1));
                        $isYou = $m['id'] == $userId;
                        ?>
                    <div class="member-row">
                        <div class="member-avatar"><?= $initials ?></div>
                        <div class="member-info">
                            <div class="member-name"><?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?></div>
                            <div class="member-role-badge <?= $m['role'] === 'admin' ? 'member-role-badge--admin' : '' ?>">
                                <?= $m['role'] === 'admin' ? '⭐ Admin' : 'Member' ?>
                            </div>
                        </div>
                        <?php if ($isYou): ?>
                        <span class="member-you">You</span>
                        <?php elseif ($isAdmin && $m['role'] !== 'admin'): ?>
                        <form action="/handlers/split-handler.php" method="POST">
                            <input type="hidden" name="action" value="promote_admin">
                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" title="Make admin">Make Admin</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal-overlay" id="add-expense-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Expense</span>
            <button class="modal-close" onclick="closeModal('add-expense-modal')">✕</button>
        </div>
        <div class="modal-body">
            <form action="/public/handlers/expense-handler.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="group_id" value="<?= $groupId ?>">

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input" placeholder="e.g. Dinner at Ayala, Grab ride…" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount (₱)</label>
                    <input type="number" name="amount" class="form-input" placeholder="0.00" step="0.01" min="0.01" required
                           oninput="updateShares(this.value)">
                </div>

                <div class="form-group">
                    <label class="form-label">Paid by</label>
                    <select name="paid_by" class="form-input">
                        <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $m['id'] == $userId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?> <?= $m['id'] == $userId ? '(You)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Split between</label>
                    <div class="split-members" id="split-members">
                        <?php foreach ($members as $m):
                            $initials = strtoupper(substr($m['firstname'] ?? '', 0, 1) . substr($m['lastname'] ?? '', 0, 1));
                            ?>
                        <div class="split-member-row selected" onclick="toggleMember(this)">
                            <input type="checkbox" name="members[]" value="<?= $m['id'] ?>" checked hidden>
                            <div class="split-checkbox">
                                <span class="split-checkmark">✓</span>
                            </div>
                            <span class="split-member-name"><?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?></span>
                            <span class="split-per-person" data-share>—</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-hint">Tap to include/exclude members from the split</div>
                </div>

                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-expense-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal-overlay" id="add-member-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Member</span>
            <button class="modal-close" onclick="closeModal('add-member-modal')">✕</button>
        </div>
        <div class="modal-body">
            <form action="/public/handlers/group-handler.php" method="POST">
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                <div class="form-group">
                    <label class="form-label">Member Email</label>
                    <input type="email" name="email" class="form-input" placeholder="Enter email address" required>
                    <div class="form-hint">The person must already have a kwnta account</div>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-member-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

function toggleMember(row) {
    row.classList.toggle('selected');
    const cb = row.querySelector('input[type=checkbox]');
    cb.checked = row.classList.contains('selected');
    const amount = document.querySelector('input[name=amount]')?.value;
    if (amount) updateShares(amount);
}

function updateShares(amount) {
    const selected = document.querySelectorAll('.split-member-row.selected');
    const count = selected.length;
    const share = count > 0 ? (parseFloat(amount) / count).toFixed(2) : '—';
    document.querySelectorAll('[data-share]').forEach(el => {
        const row = el.closest('.split-member-row');
        el.textContent = row.classList.contains('selected') && amount ? '₱' + share : '—';
    });
}

function toggleSplits(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? 'block' : 'none';
    // rotate the chevron button
    const btn = el.previousElementSibling?.querySelector('.icon-btn[onclick]');
    if (btn) btn.style.transform = isHidden ? 'rotate(180deg)' : '';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>