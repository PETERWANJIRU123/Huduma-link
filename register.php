<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nationalId = preg_replace('/\s+/', '', $_POST['national_id'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[0-9]{6,12}$/', $nationalId)) {
        $errors[] = 'National ID should be 6 to 12 digits.';
    }
    if (strlen($fullName) < 5) {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+]{9,15}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&*(),.?":{}|<>).';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE national_id = ? OR email = ?');
        $check->execute([$nationalId, $email]);
        if ($check->fetch()) {
            $errors[] = 'That national ID or email is already registered.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO users (national_id, full_name, email, phone, password_hash, role)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nationalId, $fullName, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'citizen']);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            flash('success', 'Account created. You can now apply for services.');
            redirect('dashboard.php');
        }
    }
}

$title = page_title('Create account');
require __DIR__ . '/includes/header.php';
?>
<div class="split">
    <section>
        <p class="kicker">Register once</p>
        <h1>Create your HudumaLink profile</h1>
        <p>Your national ID is the key for requests, payments, and tracking. Progress updates go to your email and phone.</p>
    </section>
    <form class="card" method="post">
        <?= csrf_field() ?>
        <?php foreach ($errors as $error): ?><div class="alert bad"><?= e($error) ?></div><?php endforeach; ?>
        <label for="national_id">National ID</label>
        <input id="national_id" name="national_id" required value="<?= e($_POST['national_id'] ?? '') ?>">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" required value="<?= e($_POST['phone'] ?? '') ?>">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <p class="help">Must be at least 8 characters with uppercase letter, number, and special character (!@#$%^&*(),.?":{}|<>)</p>
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" required>
        <p><button class="btn primary" type="submit">Create account</button></p>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
