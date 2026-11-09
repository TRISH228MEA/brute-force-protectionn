<?php require_once __DIR__ . '/functions.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Login Security Monitor') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">Login Security Monitor</a>
  <nav>
    <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="index.php">Home</a>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?><a href="admin.php">Admin Dashboard</a><?php endif; ?>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a><a href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main class="wrap">
<?php show_flash(); ?>
