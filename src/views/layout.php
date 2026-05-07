<?php
// src/views/layout.php
// Usage: set $pageTitle, $pageCSS (array of css filenames) before including
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
$pageTitle = $pageTitle ?? 'kwnta';
$pageCSS = $pageCSS ?? [];
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | kwnta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/root.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
    <link rel="shortcut icon" href="/assets/images/logo.png" type="image/x-icon">

    <?php foreach ($pageCSS as $css): ?>
    <link rel="stylesheet" href="/assets/css/<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-layout">

    <!-- Mobile hamburger -->
    <button class="sidebar-hamburger" id="sidebar-toggle" aria-label="Open menu">
        <span class="material-symbols-outlined">menu</span>
    </button>

    <!-- Mobile overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main-content">
        <?= $content ?? '' ?>
    </main>

</div>

<script>
(function() {
    const sidebar  = document.querySelector('.sidebar');
    const toggle   = document.getElementById('sidebar-toggle');
    const close    = document.getElementById('sidebar-close');
    const overlay  = document.getElementById('sidebar-overlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', openSidebar);
    close.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Close on nav item click (for mobile)
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', closeSidebar);
    });
})();
</script>
</body>
</html>