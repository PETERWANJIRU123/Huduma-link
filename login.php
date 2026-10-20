<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    $role = current_user()['role'];
    redirect(is_staff_role($role) ? 'admin/index.php' : 'dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = trim($_POST['national_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE national_id = ? AND is_active = 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect(is_staff_role($user['role']) ? 'admin/index.php' : 'dashboard.php');
    }
    $error = 'Those credentials did not match an active account.';
}

$title = page_title('Sign in');
$nav = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="split">
    <section>
        <p class="kicker">Citizen & staff access</p>
        <h1>Sign in with your national ID</h1>
        <p class="muted">Use the ID number you registered with. Staff use the demo IDs created during installation.</p>
    </section>
    <form class="card" method="post">
        <?= csrf_field() ?>
        <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
        <label for="national_id">National ID</label>
        <input id="national_id" name="national_id" required value="<?= e($_POST['national_id'] ?? '') ?>">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <p><button class="btn primary" type="submit">Sign in</button></p>
        <p>New here? <a href="<?= e(url('register.php')) ?>">Create a citizen account</a></p>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
