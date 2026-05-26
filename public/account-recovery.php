<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Scheduled for Deletion | kwnta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/root.css">
    <link rel="stylesheet" href="/assets/css/recovery.css">
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/services/UserService.php';

if (!isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit();
}

$user   = $_SESSION['user'];
$userId = $user['id'];
$dbUser = UserService::getById($conn, $userId);

if (!$dbUser || !UserService::isInRecovery($dbUser)) {
    header('Location: /dashboard.php');
    exit();
}

$daysLeft = UserService::daysUntilPermanentDelete($dbUser);
?>

<div class="recovery-page">

    <div class="recovery-logo">kwn<span>ta</span></div>

    <div class="recovery-icon-wrap">
        <span class="material-symbols-outlined">person_off</span>
    </div>

    <h1 class="recovery-title">Your account is scheduled<br>for deletion</h1>

    <p class="recovery-desc">
        You recently requested to delete your kwnta account.
        Your account and all associated data will be permanently
        and irreversibly deleted after the recovery period ends.
    </p>

    <div class="recovery-countdown">
        <div class="recovery-countdown-left">
            <span class="material-symbols-outlined">schedule</span>
            <div>
                <div class="recovery-countdown-label">Time remaining to recover</div>
                <div class="recovery-countdown-days">
                    <?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> left
                </div>
            </div>
        </div>
        <span class="material-symbols-outlined" style="color:var(--border-strong); font-size:16px;">chevron_right</span>
    </div>

    <div class="recovery-actions">
        <form action="/handlers/recover-handler.php" method="POST">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <button type="submit" class="btn-recovery btn-recovery-primary">
                <span class="material-symbols-outlined">person</span>
                Recover My Account
            </button>
        </form>

        <div class="recovery-divider">
            <div class="recovery-divider-line"></div>
            <span class="recovery-divider-text">or</span>
            <div class="recovery-divider-line"></div>
        </div>

        <a href="/handlers/logout-handler.php" class="btn-recovery btn-recovery-ghost">
            Continue with deletion — log out
        </a>
    </div>

</div>
</body>
</html>