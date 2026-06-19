<?php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/GroupService.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';

$user    = $_SESSION['user'];
$userId  = $user['id'];
$groupId = (int)($_GET['id'] ?? 0);

if (!$groupId) {
    header('Location: /groups.php');
    exit();
}

$group   = GroupService::getGroupById($conn, $groupId);
if (!$group) {
    header('Location: /groups.php');
    exit();
}

$balances = ExpenseService::getBalances($conn, $groupId, $userId);
$members  = GroupService::getMembersWithRole($conn, $groupId);
$isAdmin  = GroupService::isAdmin($conn, $groupId, $userId);

// Pagination + search
$perPage     = 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$search      = trim($_GET['search'] ?? '');
$totalCount  = ExpenseService::countExpensesByGroup($conn, $groupId, $search);
$totalPages  = max(1, (int)ceil($totalCount / $perPage));
$page        = min($page, $totalPages);
$expenses    = ExpenseService::getExpensesByGroupPaginated($conn, $groupId, $page, $perPage, $search);

// Available users for add member
$stmt = $conn->prepare("
    SELECT id, COALESCE(firstname,'') AS firstname, COALESCE(lastname,'') AS lastname, email
    FROM users
    WHERE id NOT IN (SELECT user_id FROM group_members WHERE group_id = ?)
    AND deleted_at IS NULL
    ORDER BY firstname, lastname
");
$stmt->execute([$groupId]);
$availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <p class="page-subtitle"><?= count($members) ?> members · <?= $totalCount ?> expenses</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="openModal('add-member-modal')">
            <span class="material-symbols-outlined icon-sm">person_add</span>
            Add Member
        </button>
        <button class="btn btn-primary" onclick="openModal('add-expense-modal')">
            <span class="material-symbols-outlined icon-sm">add</span>
            Add Expense
        </button>
    </div>
</div>

<div class="page-body">
    <div class="expense-layout">

        <!-- Expenses Column -->
        <div>
            <div class="expense-list-header" style="margin-bottom:14px;">
                <span class="expense-section-title">Expenses</span>
                <!-- Search -->
                <form method="GET" style="display:flex; align-items:center; gap:8px;">
                    <input type="hidden" name="id" value="<?= $groupId ?>">
                    <div style="position:relative;">
                        <span class="material-symbols-outlined" style="position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--text-muted); pointer-events:none;">search</span>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Search expenses…"
                               class="form-input" style="padding-left:32px; padding-top:7px; padding-bottom:7px; width:200px; font-size:0.82rem;">
                    </div>
                    <?php if ($search): ?>
                    <a href="group.php?id=<?= $groupId ?>" class="btn btn-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($expenses)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <span class="material-symbols-outlined icon-xl" style="color:var(--border-strong); margin-bottom:12px;">receipt_long</span>
                        <div class="empty-title"><?= $search ? 'No expenses found' : 'No expenses yet' ?></div>
                        <div class="empty-desc"><?= $search ? 'Try a different search term' : 'Add the first expense to start tracking' ?></div>
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
                $allPaid = (int)$e['split_count'] > 0 && (int)$e['paid_count'] === (int)$e['split_count'];
                ?>
            <div class="expense-item" style="cursor:pointer;" onclick="openExpenseDetail(<?= $e['id'] ?>)">
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
                    <?php if ($isAdmin): ?>
                    <form action="/handlers/expense-handler.php" method="POST" style="display:inline;" onclick="event.stopPropagation()">
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
            <?php endforeach; ?>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="group.php?id=<?= $groupId ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                    <span class="material-symbols-outlined" style="font-size:16px;">chevron_left</span>
                </a>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a href="group.php?id=<?= $groupId ?>&page=<?= $p ?>&search=<?= urlencode($search) ?>"
                   class="pagination-btn <?= $p === $page ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="group.php?id=<?= $groupId ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="pagination-btn">
                    <span class="material-symbols-outlined" style="font-size:16px;">chevron_right</span>
                </a>
                <?php endif; ?>

                <span class="pagination-info">
                    <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalCount) ?> of <?= $totalCount ?>
                </span>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div>
            <div class="balance-panel" style="margin-bottom:16px;">
                <div class="balance-panel-header">Balances</div>
                <?php if (empty($balances)): ?>
                <div style="padding:20px; text-align:center; font-size:0.82rem; color:var(--text-muted);">All settled up ✓</div>
                <?php else: ?>
                <?php foreach ($balances as $b):
                    $isOwe  = $b['amount'] < 0;
                    $absAmt = abs($b['amount']);
                    ?>
                <div class="balance-entry">
                    <div class="balance-names">
                        <?php if ($isOwe): ?>You owe <strong><?= htmlspecialchars($b['name']) ?></strong>
                        <?php else: ?><strong><?= htmlspecialchars($b['name']) ?></strong> owes you<?php endif; ?>
                    </div>
                    <div class="balance-amount-row">
                        <span class="balance-amount <?= $isOwe ? 'owe' : 'owed' ?>">₱<?= number_format($absAmt, 2) ?></span>
                        <span class="balance-tag <?= $isOwe ? 'owe' : 'owed' ?>"><?= $isOwe ? 'You owe' : 'Owed to you' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="balance-panel">
                <div class="balance-panel-header">Members</div>
                <div class="members-list">
                    <?php foreach ($members as $m):
                        $initials = strtoupper(substr($m['firstname'] ?? '', 0, 1) . substr($m['lastname'] ?? '', 0, 1));
                        $isYou    = $m['id'] == $userId;
                        ?>
                    <div class="member-row">
                        <div class="member-avatar"><?= $initials ?></div>
                        <div class="member-info">
                            <div class="member-name"><?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']) ?></div>
                            <div class="member-role-badge <?= $m['role'] === 'admin' ? 'member-role-badge--admin' : '' ?>">
                                <?= $m['role'] === 'admin' ? 'Admin' : 'Member' ?>
                            </div>
                        </div>
                        <?php if ($isYou): ?>
                        <span class="member-you">You</span>
                        <?php elseif ($isAdmin && $m['role'] !== 'admin'): ?>
                        <form action="/handlers/split-handler.php" method="POST">
                            <input type="hidden" name="action" value="promote_admin">
                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">Make Admin</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Expense Detail Modal -->
<div class="modal-overlay" id="expense-detail-modal">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <span class="modal-title">Expense Details</span>
            <button class="modal-close" onclick="closeModal('expense-detail-modal')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>
        <div class="modal-body" id="expense-detail-body">
            <div style="text-align:center; padding:24px; color:var(--text-muted); font-size:0.85rem;">Loading…</div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal-overlay" id="add-expense-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Expense</span>
            <button class="modal-close" onclick="closeModal('add-expense-modal')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>
        <div class="modal-body">
            <form action="/handlers/expense-handler.php" method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-input" placeholder="e.g. Dinner at Ayala, Grab ride…" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (₱)</label>
                    <input type="number" name="amount" class="form-input" placeholder="0.00" step="0.01" min="0.01" required oninput="updateShares(this.value)">
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
                        <?php foreach ($members as $m): ?>
                        <div class="split-member-row selected" onclick="toggleMember(this)">
                            <input type="checkbox" name="members[]" value="<?= $m['id'] ?>" checked hidden>
                            <div class="split-checkbox"><span class="split-checkmark">✓</span></div>
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
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <span class="modal-title">Add Member</span>
            <button class="modal-close" onclick="closeModal('add-member-modal')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:14px;">
                <div style="position:relative;">
                    <span class="material-symbols-outlined" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:17px; color:var(--text-muted); pointer-events:none;">search</span>
                    <input type="text" id="member-search" class="form-input" placeholder="Search by name or email…"
                           style="padding-left:34px;" oninput="filterUsers(this.value)" autocomplete="off">
                </div>
            </div>
            <?php if (empty($availableUsers)): ?>
            <div style="text-align:center; padding:24px 0; font-size:0.82rem; color:var(--text-muted);">All registered users are already in this group.</div>
            <?php else: ?>
            <div id="user-list" style="max-height:300px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-md);">
                <?php foreach ($availableUsers as $u):
                    $uInitials = strtoupper(substr($u['firstname'], 0, 1) . substr($u['lastname'], 0, 1));
                    $uName     = trim($u['firstname'] . ' ' . $u['lastname']);
                    ?>
                <div class="user-search-row"
                     data-name="<?= strtolower(htmlspecialchars($uName)) ?>"
                     data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>">
                    <div class="user-search-avatar"><?= $uInitials ?: '?' ?></div>
                    <div class="user-search-info">
                        <div class="user-search-name"><?= htmlspecialchars($uName) ?: 'No name' ?></div>
                        <div class="user-search-email"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <form action="/handlers/group-handler.php" method="POST" style="flex-shrink:0;">
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="group_id" value="<?= $groupId ?>">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($u['email']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Add</button>
                    </form>
                </div>
                <?php endforeach; ?>
                <div id="user-list-empty" style="display:none; text-align:center; padding:20px; font-size:0.82rem; color:var(--text-muted);">No users found.</div>
            </div>
            <?php endif; ?>
            <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border); font-size:0.75rem; color:var(--text-muted);">
                Only registered kwnta users can be added to groups.
            </div>
        </div>
    </div>
</div>

<!-- Expense splits data for JS detail modal -->
<script>
const expenseSplits = <?= json_encode(
    array_combine(
        array_column($expenses, 'id'),
        array_map(function ($e) use ($conn, $isAdmin, $groupId) {
            return ExpenseService::getSplitsForExpense($conn, $e['id']);
        }, $expenses)
    )
) ?>;

const expenseData = <?= json_encode(
    array_combine(
        array_column($expenses, 'id'),
        $expenses
    )
) ?>;

const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
const currentGroupId = <?= $groupId ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

function openExpenseDetail(expenseId) {
    const e       = expenseData[expenseId];
    const splits  = expenseSplits[expenseId] || [];
    if (!e) return;

    const myShare = (e.amount / Math.max(1, e.split_count)).toFixed(2);
    const allPaid = splits.length > 0 && splits.every(s => parseInt(s.paid) === 1);

    let splitsHtml = splits.map(s => `
        <div class="split-row">
            <div class="split-row-avatar">${s.member_name.charAt(0).toUpperCase()}</div>
            <span class="split-row-name">${s.member_name}</span>
            <span class="split-row-amount">₱${parseFloat(s.share).toLocaleString('en-PH', {minimumFractionDigits:2})}</span>
            ${isAdmin ? `
            <form action="/handlers/split-handler.php" method="POST" style="margin-left:auto;">
                <input type="hidden" name="action" value="toggle_paid">
                <input type="hidden" name="expense_id" value="${e.id}">
                <input type="hidden" name="member_id" value="${s.user_id}">
                <input type="hidden" name="group_id" value="${currentGroupId}">
                <button type="submit" class="split-paid-btn ${parseInt(s.paid) ? 'split-paid-btn--paid' : ''}">
                    ${parseInt(s.paid) ? '✓ Paid' : 'Mark Paid'}
                </button>
            </form>` : `
            <span class="split-paid-status ${parseInt(s.paid) ? 'split-paid-status--paid' : 'split-paid-status--unpaid'}" style="margin-left:auto;">
                ${parseInt(s.paid) ? '✓ Paid' : 'Unpaid'}
            </span>`}
        </div>
    `).join('');

    document.getElementById('expense-detail-body').innerHTML = `
        <div style="margin-bottom:20px;">
            <div style="font-family:var(--headings); font-size:1.2rem; font-weight:700; letter-spacing:-0.02em; margin-bottom:6px;">
                ${e.description}
            </div>
            <div style="font-size:0.82rem; color:var(--text-muted);">
                ${new Date(e.created_at).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})}
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
            <div style="background:var(--surface-2); border-radius:var(--radius-md); padding:14px 16px;">
                <div style="font-size:0.72rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total</div>
                <div style="font-family:var(--headings); font-size:1.3rem; font-weight:700; letter-spacing:-0.03em;">₱${parseFloat(e.amount).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            </div>
            <div style="background:var(--surface-2); border-radius:var(--radius-md); padding:14px 16px;">
                <div style="font-size:0.72rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Per Person</div>
                <div style="font-family:var(--headings); font-size:1.3rem; font-weight:700; letter-spacing:-0.03em;">₱${parseFloat(myShare).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
            </div>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <div style="font-size:0.82rem; color:var(--text-secondary);">
                Paid by <strong>${e.paid_by_name}</strong> · split ${e.split_count} ways
            </div>
            <span class="paid-badge ${allPaid ? 'paid-badge--paid' : 'paid-badge--unpaid'}">
                ${allPaid ? '✓ Settled' : 'Unpaid'}
            </span>
        </div>

        <div style="font-size:0.78rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px;">Splits</div>
        <div style="border:1px solid var(--border); border-radius:var(--radius-md); overflow:hidden;">
            ${splitsHtml}
        </div>
    `;
    openModal('expense-detail-modal');
}

function toggleMember(row) {
    row.classList.toggle('selected');
    row.querySelector('input[type=checkbox]').checked = row.classList.contains('selected');
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

function filterUsers(query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('.user-search-row');
    let visible = 0;
    rows.forEach(row => {
        const match = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const empty = document.getElementById('user-list-empty');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}

const addMemberModal = document.getElementById('add-member-modal');
if (addMemberModal) {
    new MutationObserver(mutations => {
        mutations.forEach(m => {
            if (m.attributeName === 'class' && addMemberModal.classList.contains('open')) {
                const input = document.getElementById('member-search');
                if (input) { input.value = ''; filterUsers(''); }
            }
        });
    }).observe(addMemberModal, { attributes: true });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>