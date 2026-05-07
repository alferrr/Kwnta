<?php
// public/settings.php
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';
AuthMiddleware::handle();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/UserService.php';
require_once __DIR__ . '/../src/services/GroupService.php';
require_once __DIR__ . '/../src/services/ExpenseService.php';

$user     = $_SESSION['user'];
$userId   = $user['id'];
$profile  = UserService::getById($conn, $userId);
$groups   = GroupService::getGroupsByUser($conn, $userId);
$expenses = ExpenseService::getAllExpensesByUser($conn, $userId);

$totalGroups   = count($groups);
$totalExpenses = count($expenses);
$totalSpent    = array_sum(array_column($expenses, 'amount'));

$activeTab = $_GET['tab'] ?? 'profile';
$success   = $_GET['success'] ?? '';
$error     = $_GET['error'] ?? '';

$initials  = strtoupper(
    substr($profile['firstname'] ?? '', 0, 1) .
    substr($profile['lastname'] ?? '', 0, 1)
);

$successMessages = [
    'profile_updated' => 'Your profile has been updated.',
    'password_changed' => 'Password changed successfully.',
];

$pageTitle   = 'Settings';
$currentPage = 'settings';
$pageCSS     = ['groups.css', 'settings.css'];

ob_start();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your account and preferences</p>
    </div>
</div>

<div class="page-body">
    <div class="settings-layout">

        <!-- Side Nav -->
        <nav class="settings-nav">
            <a href="?tab=profile"  class="settings-nav-item <?= $activeTab === 'profile' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">person</span>
                Profile
            </a>
            <a href="?tab=security" class="settings-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">lock</span>
                Security
            </a>
            <a href="?tab=account"  class="settings-nav-item <?= $activeTab === 'account' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">manage_accounts</span>
                Account
            </a>
            <a href="?tab=danger"   class="settings-nav-item danger <?= $activeTab === 'danger' ? 'active' : '' ?>">
                <span class="material-symbols-outlined">warning</span>
                Danger Zone
            </a>
        </nav>

        <!-- Panels -->
        <div class="settings-panels">

            <?php if ($success && isset($successMessages[$success])): ?>
            <div class="alert alert-success">
                <span class="material-symbols-outlined">check_circle</span>
                <?= htmlspecialchars($successMessages[$success]) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="material-symbols-outlined">error</span>
                <?= htmlspecialchars(urldecode($error)) ?>
            </div>
            <?php endif; ?>

            <!-- ── Profile ── -->
            <div class="settings-panel <?= $activeTab === 'profile' ? 'active' : '' ?>">

                <div class="settings-section">
                    <div class="profile-avatar-block">
                        <div class="profile-avatar-large"><?= $initials ?: '?' ?></div>
                        <div class="profile-avatar-info">
                            <div class="profile-avatar-name">
                                <?= htmlspecialchars(trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? ''))) ?: 'No name set' ?>
                            </div>
                            <div class="profile-avatar-email"><?= htmlspecialchars($profile['email'] ?? '') ?></div>
                            <div class="profile-avatar-joined">
                                <span class="material-symbols-outlined" style="font-size:13px;">calendar_today</span>
                                Joined <?= date('F Y', strtotime($profile['created_at'] ?? 'now')) ?>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-body">
                        <form action="/handlers/settings-handler.php" method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="form-grid-2" style="margin-bottom: 16px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="firstname" class="form-input"
                                           value="<?= htmlspecialchars($profile['firstname'] ?? '') ?>"
                                           placeholder="First name" required>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="lastname" class="form-input"
                                           value="<?= htmlspecialchars($profile['lastname'] ?? '') ?>"
                                           placeholder="Last name">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input"
                                       value="<?= htmlspecialchars($profile['email'] ?? '') ?>"
                                       placeholder="you@example.com" required>
                            </div>

                            <div style="display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-symbols-outlined icon-sm">save</span>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- ── Security ── -->
            <div class="settings-panel <?= $activeTab === 'security' ? 'active' : '' ?>">

                <div class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <span class="material-symbols-outlined icon-sm">lock</span>
                        </div>
                        <div>
                            <div class="settings-section-title">Change Password</div>
                            <div class="settings-section-desc">Use a strong password you don't use elsewhere</div>
                        </div>
                    </div>
                    <div class="settings-section-body">
                        <form action="/handlers/settings-handler.php" method="POST">
                            <input type="hidden" name="action" value="update_password">

                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-input"
                                       placeholder="Enter current password" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="new-password" class="form-input"
                                       placeholder="At least 8 characters" required
                                       oninput="checkStrength(this.value)">
                                <div class="password-strength">
                                    <div class="strength-bars">
                                        <div class="strength-bar" id="bar-1"></div>
                                        <div class="strength-bar" id="bar-2"></div>
                                        <div class="strength-bar" id="bar-3"></div>
                                        <div class="strength-bar" id="bar-4"></div>
                                    </div>
                                    <span class="strength-label" id="strength-label" style="color:var(--text-muted);"></span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input"
                                       placeholder="Repeat new password" required>
                            </div>

                            <div style="display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-symbols-outlined icon-sm">lock_reset</span>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- ── Account ── -->
            <div class="settings-panel <?= $activeTab === 'account' ? 'active' : '' ?>">

                <div class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <span class="material-symbols-outlined icon-sm">bar_chart</span>
                        </div>
                        <div>
                            <div class="settings-section-title">Account Overview</div>
                            <div class="settings-section-desc">Your activity summary on kwnta</div>
                        </div>
                    </div>
                    <div class="settings-section-body" style="padding: 0;">
                        <div class="account-stats">
                            <div class="account-stat">
                                <div class="account-stat-value"><?= $totalGroups ?></div>
                                <div class="account-stat-label">Groups</div>
                            </div>
                            <div class="account-stat">
                                <div class="account-stat-value"><?= $totalExpenses ?></div>
                                <div class="account-stat-label">Expenses</div>
                            </div>
                            <div class="account-stat">
                                <div class="account-stat-value">₱<?= number_format($totalSpent, 0) ?></div>
                                <div class="account-stat-label">Total Tracked</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <span class="material-symbols-outlined icon-sm">info</span>
                        </div>
                        <div>
                            <div class="settings-section-title">Account Details</div>
                            <div class="settings-section-desc">Read-only account information</div>
                        </div>
                    </div>
                    <div class="settings-section-body">
                        <div class="form-group">
                            <label class="form-label">User ID</label>
                            <input type="text" class="form-input" value="#<?= $userId ?>" disabled
                                   style="background:var(--surface-2); color:var(--text-muted); cursor:default;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-input"
                                   value="<?= date('F j, Y', strtotime($profile['created_at'] ?? 'now')) ?>"
                                   disabled style="background:var(--surface-2); color:var(--text-muted); cursor:default;">
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── Danger Zone ── -->
            <div class="settings-panel <?= $activeTab === 'danger' ? 'active' : '' ?>">

                <div class="settings-section danger-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <span class="material-symbols-outlined icon-sm">delete_forever</span>
                        </div>
                        <div>
                            <div class="settings-section-title">Delete Account</div>
                            <div class="settings-section-desc">Permanently delete your account and all data</div>
                        </div>
                    </div>
                    <div class="settings-section-body">
                        <p style="font-size:0.855rem; color:var(--text-secondary); margin-bottom:16px; line-height:1.6;">
                            This will permanently delete your account, all your groups, expenses, and split records.
                            <strong>This action cannot be undone.</strong>
                        </p>

                        <div style="background:var(--accent-red-light); border:1px solid #fecaca; border-radius:var(--radius-md); padding:12px 16px; margin-bottom:20px;">
                            <div style="display:flex; gap:8px; align-items:flex-start;">
                                <span class="material-symbols-outlined" style="font-size:16px; color:var(--accent-red); margin-top:1px; flex-shrink:0;">warning</span>
                                <div style="font-size:0.78rem; color:#7f1d1d; line-height:1.5;">
                                    Groups where you are the only member will be deleted.
                                    Members in shared groups will remain unaffected.
                                </div>
                            </div>
                        </div>

                        <form action="/handlers/settings-handler.php" method="POST"
                              onsubmit="return confirm('Are you absolutely sure? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete_account">
                            <div class="form-group danger-confirm-input">
                                <label class="form-label">Confirm your password to delete</label>
                                <input type="password" name="confirm_delete_password" class="form-input"
                                       placeholder="Enter your password" required
                                       style="border-color: #fecaca;">
                            </div>
                            <div style="display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn btn-danger">
                                    <span class="material-symbols-outlined icon-sm">delete_forever</span>
                                    Delete My Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

        </div><!-- /.settings-panels -->
    </div><!-- /.settings-layout -->
</div>

<script>
function checkStrength(val) {
    const bars  = [1,2,3,4].map(i => document.getElementById('bar-' + i));
    const label = document.getElementById('strength-label');
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    score = Math.min(score, 4);

    const cls = score <= 1 ? 'active-weak' : score <= 2 ? 'active-medium' : 'active-strong';
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const colors = ['', 'var(--accent-red)', 'var(--accent-amber)', 'var(--accent-green)', 'var(--accent-green)'];

    bars.forEach((bar, i) => {
        bar.className = 'strength-bar' + (i < score ? ' ' + cls : '');
    });
    label.textContent = val.length ? labels[score] : '';
    label.style.color = val.length ? colors[score] : 'var(--text-muted)';
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../src/views/layout.php';
?>