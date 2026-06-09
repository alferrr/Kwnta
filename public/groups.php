<?php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/GroupService.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';

$user            = $_SESSION['user'];
$userId          = $user['id'];
$groups          = GroupService::getActiveGroupsByUser($conn, $userId);
$archivedGroups  = GroupService::getArchivedGroupsByUser($conn, $userId);
$pendingRequests = GroupService::getAllLeaveRequestsForAdmin($conn, $userId);

$pageTitle   = 'Groups';
$currentPage = 'groups';
$pageCSS     = ['groups.css'];

ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Groups</h1>
        <p class="page-subtitle">
            <?= count($groups) ?> active<?= count($archivedGroups) ? ' · ' . count($archivedGroups) . ' archived' : '' ?>
        </p>
    </div>
    <div class="page-actions">
        <div style="position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:9px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--text-muted); pointer-events:none;">search</span>
            <input type="text" id="group-search" class="form-input" placeholder="Search groups…"
                   style="padding-left:32px; padding-top:7px; padding-bottom:7px; width:180px; font-size:0.82rem;"
                   oninput="filterGroups(this.value)">
        </div>
        <button class="btn btn-primary" onclick="openModal('create-group-modal')">
            <span class="material-symbols-outlined icon-sm">add</span>
            New Group
        </button>
    </div>
</div>

<div class="page-body">

    <!--Admin: Pending Leave Requests Banner-->
    <?php if (!empty($pendingRequests)): ?>
    <div class="leave-requests-banner">
        <div class="leave-requests-banner-left">
            <span class="material-symbols-outlined" style="color:var(--accent-amber);">notifications</span>
            <div>
                <div class="leave-requests-banner-title">
                    <?= count($pendingRequests) ?> pending leave request<?= count($pendingRequests) !== 1 ? 's' : '' ?>
                </div>
                <div class="leave-requests-banner-desc">Members have requested to leave groups you admin</div>
            </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="openModal('leave-requests-modal')">Review</button>
    </div>
    <?php endif; ?>

    <!-- ── Active Groups ── -->
    <?php if (empty($groups)): ?>
    <div class="card" style="max-width:480px; margin:60px auto;">
        <div class="card-body">
            <div class="empty-state">
                <span class="material-symbols-outlined icon-xl" style="color:var(--border-strong); margin-bottom:12px;">group</span>
                <div class="empty-title">Create your first group</div>
                <div class="empty-desc">Groups let you track shared expenses with friends, family, or roommates.</div>
                <button class="btn btn-primary" style="margin-top:20px;" onclick="openModal('create-group-modal')">
                    Get Started
                </button>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="groups-grid">
        <?php
        $accentColors = ['#dbeafe','#dcfce7','#fef9c3','#fce7f3','#ede9fe','#ffedd5'];
        foreach ($groups as $i => $g):
            $bal      = ExpenseService::getMyBalance($conn, $g['id'], $userId);
            $balClass = $bal > 0 ? 'owed' : ($bal < 0 ? 'owe' : 'settled');
            $balLabel = $bal > 0
                ? '+₱' . number_format($bal, 2)
                : ($bal < 0 ? '-₱' . number_format(abs($bal), 2) : 'Settled');
            $accent   = $accentColors[$i % count($accentColors)];
            $iconName = $g['icon'] ?? 'group';
            $isAdmin  = GroupService::isAdmin($conn, $g['id'], $userId);
            $myLeave  = GroupService::getMyLeaveRequest($conn, $g['id'], $userId);
            $menuId   = 'menu-' . $g['id'];
            ?>
        <!-- Wrapper is position:relative so dropdown can escape the card -->
        <div class="group-card-wrapper" data-name="<?= strtolower(htmlspecialchars($g['name'])) ?>">

            <!-- The card is just a link — no interactive children inside -->
            <a href="group.php?id=<?= $g['id'] ?>" class="group-card" style="--card-accent:<?= $accent ?>;">
                <div class="group-card-top">
                    <div class="group-card-icon" style="background:<?= $accent ?>;">
                        <span class="material-symbols-outlined icon-md"><?= htmlspecialchars($iconName) ?></span>
                    </div>
                    <!-- Spacer so name doesn't bleed under the menu button -->
                    <div style="width:28px; flex-shrink:0;"></div>
                </div>
                <div class="group-card-name"><?= htmlspecialchars($g['name']) ?></div>
                <div class="group-card-members">
                    <span class="material-symbols-outlined" style="font-size:13px;">person</span>
                    <?= $g['member_count'] ?? 0 ?> member<?= ($g['member_count'] ?? 0) !== 1 ? 's' : '' ?>
                    <?php if ($myLeave): ?>
                    <span class="leave-pending-chip">Leave Pending</span>
                    <?php endif; ?>
                </div>
                <div class="group-card-divider"></div>
                <div class="group-card-footer">
                    <span class="group-balance-label">Your balance</span>
                    <span class="group-balance-value <?= $balClass ?>"><?= $balLabel ?></span>
                </div>
            </a>

            <!-- Menu button sits on top of the card via absolute positioning -->
            <div class="group-card-menu-wrap">
                <button class="group-card-menu" onclick="toggleGroupMenu(event, '<?= $menuId ?>')">
                    <span class="material-symbols-outlined" style="font-size:18px;">more_vert</span>
                </button>
                <div class="group-dropdown" id="<?= $menuId ?>">
                    <a href="group.php?id=<?= $g['id'] ?>" class="group-dropdown-item">
                        <span class="material-symbols-outlined icon-sm">open_in_new</span>
                        View Group
                    </a>
                    <?php if ($isAdmin): ?>
                    <button class="group-dropdown-item"
                            onclick="confirmArchive(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['name'])) ?>')">
                        <span class="material-symbols-outlined icon-sm">archive</span>
                        Archive Group
                    </button>
                    <?php elseif ($myLeave): ?>
                    <button class="group-dropdown-item group-dropdown-item--warning"
                            onclick="cancelLeave(<?= $g['id'] ?>)">
                        <span class="material-symbols-outlined icon-sm">cancel</span>
                        Cancel Leave Request
                    </button>
                    <?php else: ?>
                    <button class="group-dropdown-item group-dropdown-item--danger"
                            onclick="openLeaveModal(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['name'])) ?>')">
                        <span class="material-symbols-outlined icon-sm">exit_to_app</span>
                        Request to Leave
                    </button>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Archived Groups ── -->
    <?php if (!empty($archivedGroups)): ?>
    <div class="archived-section">
        <button class="archived-toggle" onclick="toggleArchived(this)">
            <span class="material-symbols-outlined icon-sm">archive</span>
            Archived Groups
            <span class="archived-count"><?= count($archivedGroups) ?></span>
            <span class="material-symbols-outlined icon-sm archived-chevron">expand_more</span>
        </button>

        <div class="archived-grid" style="display:none;">
            <?php foreach ($archivedGroups as $g):
                $iconName = $g['icon'] ?? 'group';
                $isAdmin  = GroupService::isAdmin($conn, $g['id'], $userId);
                $menuId   = 'menu-arc-' . $g['id'];
                ?>
            <div class="group-card-wrapper">
                <a href="group.php?id=<?= $g['id'] ?>" class="group-card group-card--archived">
                    <div class="group-card-top">
                        <div class="group-card-icon" style="background:var(--surface-2);">
                            <span class="material-symbols-outlined icon-md"><?= htmlspecialchars($iconName) ?></span>
                        </div>
                        <?php if ($isAdmin): ?>
                        <div style="width:28px; flex-shrink:0;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="group-card-name"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="group-card-members">
                        <span class="material-symbols-outlined" style="font-size:13px;">person</span>
                        <?= $g['member_count'] ?? 0 ?> member<?= ($g['member_count'] ?? 0) !== 1 ? 's' : '' ?>
                    </div>
                    <div class="group-card-divider"></div>
                    <div class="group-card-footer">
                        <span class="archived-badge">
                            <span class="material-symbols-outlined" style="font-size:12px;">archive</span>
                            Archived <?= $g['archived_at'] ? date('M j, Y', strtotime($g['archived_at'])) : '' ?>
                        </span>
                    </div>
                </a>

                <?php if ($isAdmin): ?>
                <div class="group-card-menu-wrap">
                    <button class="group-card-menu" onclick="toggleGroupMenu(event, '<?= $menuId ?>')">
                        <span class="material-symbols-outlined" style="font-size:18px;">more_vert</span>
                    </button>
                    <div class="group-dropdown" id="<?= $menuId ?>">
                        <button class="group-dropdown-item"
                                onclick="unarchiveGroup(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['name'])) ?>')">
                            <span class="material-symbols-outlined icon-sm">unarchive</span>
                            Restore Group
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.page-body -->

<!-- ── Create Group Modal ── -->
<div class="modal-overlay" id="create-group-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">New Group</span>
            <button class="modal-close" onclick="closeModal('create-group-modal')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>
        <div class="modal-body">
            <form action="/handlers/group-handler.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label class="form-label">Group Name</label>
                    <input type="text" name="name" class="form-input"
                           placeholder="e.g. Cebu Trip, Apartment Bills…" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Pick an Icon</label>
                    <div class="icon-picker-grid">
                        <?php
                            $pickerIcons = [
                                'beach_access' => 'Beach',   'home'          => 'Home',
                                'celebration'  => 'Party',   'restaurant'    => 'Food',
                                'flight'       => 'Travel',  'school'        => 'School',
                                'fitness_center' => 'Gym',   'sports_esports' => 'Gaming',
                                'directions_car' => 'Car',   'shopping_cart' => 'Shopping',
                                'work'         => 'Work',    'music_note'    => 'Music',
                            ];
$first = true;
foreach ($pickerIcons as $iconKey => $iconLabel): ?>
                        <div class="icon-picker-option <?= $first ? 'selected' : '' ?>"
                             onclick="selectIcon(this, '<?= $iconKey ?>')" title="<?= $iconLabel ?>">
                            <span class="material-symbols-outlined icon-md"><?= $iconKey ?></span>
                        </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                    <input type="hidden" name="icon" id="selected-icon" value="beach_access">
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-group-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Leave Request Modal ── -->
<div class="modal-overlay" id="leave-request-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Request to Leave</span>
            <button class="modal-close" onclick="closeModal('leave-request-modal')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>
        <div class="modal-body">
            <p style="font-size:0.855rem; color:var(--text-secondary); margin-bottom:18px; line-height:1.6;">
                Your request to leave <strong id="leave-group-name"></strong> will be sent to the group admin for approval.
                You will remain in the group until approved.
            </p>
            <form action="/handlers/group-handler.php" method="POST">
                <input type="hidden" name="action" value="request_leave">
                <input type="hidden" name="group_id" id="leave-group-id">
                <div class="form-group">
                    <label class="form-label">
                        Message <span style="color:var(--text-muted); font-weight:400;">(optional)</span>
                    </label>
                    <textarea name="message" class="form-input" rows="3"
                              placeholder="Let the admin know why you're leaving…"
                              style="resize:vertical; min-height:80px;"></textarea>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('leave-request-modal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Admin: Review Leave Requests Modal ── -->
<?php if (!empty($pendingRequests)): ?>
<div class="modal-overlay" id="leave-requests-modal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <span class="modal-title">Leave Requests</span>
            <button class="modal-close" onclick="closeModal('leave-requests-modal')">
                <span class="material-symbols-outlined" style="font-size:18px;">close</span>
            </button>
        </div>
        <div class="modal-body" style="padding-top:16px;">
            <?php foreach ($pendingRequests as $req): ?>
            <div class="leave-request-item">
                <div class="leave-request-avatar">
                    <?= strtoupper(substr($req['user_name'], 0, 1)) ?>
                </div>
                <div class="leave-request-info">
                    <div class="leave-request-name"><?= htmlspecialchars(trim($req['user_name'])) ?></div>
                    <div class="leave-request-meta">
                        <span class="material-symbols-outlined" style="font-size:12px;">group</span>
                        <?= htmlspecialchars($req['group_name']) ?>
                        · <?= date('M j', strtotime($req['created_at'])) ?>
                    </div>
                    <?php if ($req['message']): ?>
                    <div class="leave-request-message">"<?= htmlspecialchars($req['message']) ?>"</div>
                    <?php endif; ?>
                </div>
                <div class="leave-request-actions">
                    <form action="/handlers/group-handler.php" method="POST">
                        <input type="hidden" name="action" value="resolve_leave">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="btn btn-sm"
                                style="background:var(--accent-green-light); color:var(--accent-green);">
                            <span class="material-symbols-outlined icon-sm">check</span>
                            Approve
                        </button>
                    </form>
                    <form action="/handlers/group-handler.php" method="POST">
                        <input type="hidden" name="action" value="resolve_leave">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <input type="hidden" name="decision" value="reject">
                        <button type="submit" class="btn btn-sm btn-secondary">
                            <span class="material-symbols-outlined icon-sm">close</span>
                            Reject
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Hidden action forms -->
<form id="archive-form" action="/handlers/group-handler.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="archive_group">
    <input type="hidden" name="group_id" id="archive-group-id">
</form>
<form id="unarchive-form" action="/handlers/group-handler.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="unarchive_group">
    <input type="hidden" name="group_id" id="unarchive-group-id">
</form>
<form id="cancel-leave-form" action="/handlers/group-handler.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="cancel_leave">
    <input type="hidden" name="group_id" id="cancel-leave-group-id">
</form>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function selectIcon(el, icon) {
    document.querySelectorAll('.icon-picker-option').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selected-icon').value = icon;
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

function toggleGroupMenu(e, id) {
    e.preventDefault();
    e.stopPropagation();
    const target = document.getElementById(id);
    const isOpen = target.classList.contains('open');
    document.querySelectorAll('.group-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!isOpen) target.classList.add('open');
}

function filterGroups(query) {
    const q = query.toLowerCase().trim();
    let visible = 0;
    document.querySelectorAll('.groups-grid .group-card-wrapper').forEach(card => {
        const name = card.dataset.name || '';
        const match = !q || name.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const grid = document.querySelector('.groups-grid');
    let noResult = document.getElementById('groups-no-result');
    if (visible === 0 && grid) {
        if (!noResult) {
            noResult = document.createElement('div');
            noResult.id = 'groups-no-result';
            noResult.style.cssText = 'padding:40px; text-align:center; font-size:0.85rem; color:var(--text-muted); grid-column:1/-1;';
            noResult.textContent = 'No groups match your search.';
            grid.appendChild(noResult);
        }
        noResult.style.display = '';
    } else if (noResult) {
        noResult.style.display = 'none';
    }
}

document.addEventListener('click', () => {
    document.querySelectorAll('.group-dropdown.open').forEach(d => d.classList.remove('open'));
});

function openLeaveModal(groupId, groupName) {
    document.getElementById('leave-group-id').value = groupId;
    document.getElementById('leave-group-name').textContent = groupName;
    openModal('leave-request-modal');
}

function cancelLeave(groupId) {
    if (!confirm('Cancel your leave request for this group?')) return;
    document.getElementById('cancel-leave-group-id').value = groupId;
    document.getElementById('cancel-leave-form').submit();
}

function confirmArchive(groupId, groupName) {
    if (!confirm('Archive "' + groupName + '"? It will be hidden from active groups but all data is preserved.')) return;
    document.getElementById('archive-group-id').value = groupId;
    document.getElementById('archive-form').submit();
}

function unarchiveGroup(groupId, groupName) {
    if (!confirm('Restore "' + groupName + '" to active groups?')) return;
    document.getElementById('unarchive-group-id').value = groupId;
    document.getElementById('unarchive-form').submit();
}

function toggleArchived(btn) {
    const grid    = btn.nextElementSibling;
    const chevron = btn.querySelector('.archived-chevron');
    const isHidden = grid.style.display === 'none';
    grid.style.display = isHidden ? 'grid' : 'none';
    chevron.textContent = isHidden ? 'expand_less' : 'expand_more';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>