<?php
$uiPage = basename($_SERVER['SCRIPT_NAME']);
$uiDashboard = ($_SESSION['role'] ?? '') === 'admin' ? 'admin_dashboard.php' : 'tenant_dashboard.php';
$uiPublic = in_array($uiPage, ['login.php','register.php','forgot_password.php','reset_password.php'], true);
?>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header"><div class="header-inner">
<a class="brand" href="<?php echo $uiPublic ? 'login.php' : $uiDashboard; ?>">
<span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 27V11L16 4l11 7v16H5Z"/><path d="M12 27v-8h8v8M11 12h2m6 0h2"/></svg></span>
<span>Maintenance Request System<small>A better place to call home</small></span></a>
<nav class="site-nav" aria-label="Main navigation">
<?php if ($uiPublic): ?>
<a href="login.php" <?php if ($uiPage === 'login.php') echo 'aria-current="page"'; ?>>Login</a>
<a href="register.php" <?php if ($uiPage === 'register.php') echo 'aria-current="page"'; ?>>Create account</a>
<?php else: ?>
<a href="<?php echo $uiDashboard; ?>" <?php if ($uiPage === $uiDashboard) echo 'aria-current="page"'; ?>>Dashboard</a>
<?php if (($_SESSION['role'] ?? '') === 'tenant'): ?><a href="my_requests.php" <?php if ($uiPage === 'my_requests.php') echo 'aria-current="page"'; ?>>My Requests</a><?php endif; ?>
<a href="profile.php" <?php if ($uiPage === 'profile.php') echo 'aria-current="page"'; ?>>Profile</a>
<?php if (empty($_SESSION['logout_token'])) $_SESSION['logout_token'] = bin2hex(random_bytes(32)); ?>
<form action="logout.php" method="POST"><input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['logout_token'], ENT_QUOTES, 'UTF-8'); ?>"><button type="submit">Logout</button></form>
<?php endif; ?>
</nav></div></header>
