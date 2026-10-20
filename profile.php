<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
    $notifySms = isset($_POST['notify_sms']) ? 1 : 0;
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+]{9,15}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }

    $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
    $dup->execute([$email, $user['id']]);
    if ($dup->fetch()) {
        $errors[] = 'That email is already in use.';
    }

    if ($new !== '') {
        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $new)) {
            $errors[] = 'Password must contain at least one number.';
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new)) {
            $errors[] = 'Password must contain at least one special character (!@#$%^&*(),.?":{}|<>).';
        }
    }

    if (!$errors) {
        if ($new !== '') {
            db()->prepare('UPDATE users SET email = ?, phone = ?, notify_email = ?, notify_sms = ?, password_hash = ? WHERE id = ?')
                ->execute([$email, $phone, $notifyEmail, $notifySms, password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        } else {
            db()->prepare('UPDATE users SET email = ?, phone = ?, notify_email = ?, notify_sms = ? WHERE id = ?')
                ->execute([$email, $phone, $notifyEmail, $notifySms, $user['id']]);
        }
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }
}

$title = page_title('Profile');
require __DIR__ . '/includes/header.php';
?>
<h1>My profile</h1>
<form class="card" method="post" style="max-width:520px">
    <?= csrf_field() ?>
    <?php foreach ($errors as $error): ?><div class="alert bad"><?= e($error) ?></div><?php endforeach; ?>
    <label>National ID</label>
    <input value="<?= e($user['national_id']) ?>" disabled>
    <label>Full name</label>
    <input value="<?= e($user['full_name']) ?>" disabled>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? $user['email']) ?>" required>
    <label for="phone">Phone</label>
    <input id="phone" name="phone" value="<?= e($_POST['phone'] ?? $user['phone']) ?>" required>
    <p class="help">Progress on your requests is sent to this email and mobile number.</p>
    <label>
        <input type="checkbox" name="notify_email" value="1" <?= !empty($user['notify_email']) || !isset($user['notify_email']) ? 'checked' : '' ?>>
        Email me when a request moves forward
    </label>
    <label>
        <input type="checkbox" name="notify_sms" value="1" <?= !empty($user['notify_sms']) || !isset($user['notify_sms']) ? 'checked' : '' ?>>
        Text me when a request moves forward
    </label>
    <label for="current_password">Current password (only if changing password)</label>
    <input id="current_password" name="current_password" type="password">
    <label for="new_password">New password</label>
    <input id="new_password" name="new_password" type="password">
    <p class="help">Must be at least 8 characters with uppercase letter, number, and special character (!@#$%^&*(),.?":{}|<>)</p>
    <p><button class="btn primary" type="submit">Save</button></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
