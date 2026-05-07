<?php
// src/views/partials/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
$firstName = htmlspecialchars($user['firstname'] ?? '');
$lastName  = htmlspecialchars($user['lastname'] ?? '');
$email     = htmlspecialchars($user['email'] ?? '');
$initials  = strtoupper(substr($user['firstname'] ?? 'U', 0, 1) . substr($user['lastname'] ?? '', 0, 1));
$currentPage = $currentPage ?? '';
?>
<aside class="sidebar">

    <div class="sidebar-logo">
        <button class="sidebar-close" id="sidebar-close" aria-label="Close menu">
            <span class="material-symbols-outlined">close</span>
        </button>
        <div class="logo-mark">kwn<span>ta</span></div>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-section-label">Main</span>

        <a href="/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">space_dashboard</span>
            Overview
        </a>

        <a href="/groups.php" class="nav-item <?= $currentPage === 'groups' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">group</span>
            Groups
        </a>

        <a href="/expenses.php" class="nav-item <?= $currentPage === 'expenses' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">receipt_long</span>
            Expenses
        </a>

        <a href="/balances.php" class="nav-item <?= $currentPage === 'balances' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">balance</span>
            Balances
        </a>

        <span class="nav-section-label" style="margin-top: 8px;">Account</span>

        <a href="/settings.php" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <span class="material-symbols-outlined">settings</span>
            Settings
        </a>

        <a href="/handlers/logout-handler.php" class="nav-item">
            <span class="material-symbols-outlined">logout</span>
            Logout
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= $initials ?></div>
            <div class="user-info">
                <div class="user-name"><?= trim($firstName . ' ' . $lastName) ?: 'User' ?></div>
                <div class="user-email"><?= $email ?></div>
            </div>
        </div>
    </div>

</aside>