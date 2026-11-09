
<?php
require_once 'config.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $result = supabase_request(
            'POST',
            '/auth/v1/token?grant_type=password',
            [
                'email' => $username,
                'password' => $password
            ],
            false
        );

        if (!empty($result['access_token'])) {
            session_regenerate_id(true);

            $_SESSION['access_token'] = $result['access_token'];
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['username'] = $result['user']['email'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Incorrect username or password.';
        }
    }
}

$pageTitle = 'Login';
require 'includes/header.php';
?>

<section class="auth panel">
    <h1>User Login</h1>
    <p class="muted">Sign in to your account.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token"
               value="<?= e(csrf_token()) ?>">

        <label>Email</label>
        <input name="username" type="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button class="button" type="submit">Login</button>
    </form>

    <p class="muted">
        No account yet?
        <a href="register.php">Register here</a>.
    </p>
</section>

<?php require 'includes/footer.php'; ?>
