<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/catalogue.php';
$user = require_admin();
$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$errors = [];
$levels = access_levels();

function assigned_department(string $role, $raw): ?int
{
    if ($role === 'citizen' || $raw === '' || $raw === null) {
        return null;
    }
    return (int) $raw;
}

function validate_access(string $role, ?int $dept, array $levels): array
{
    $errors = [];
    if (!isset($levels[$role])) {
        $errors[] = 'Choose a valid access level.';
    }
    if ($role === 'officer' && !$dept) {
        $errors[] = 'Department officers must be assigned to a department.';
    }
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nationalId = preg_replace('/\s+/', '', $_POST['national_id'] ?? '');
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'citizen';
        $dept = assigned_department($role, $_POST['department_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $errors = validate_access($role, $dept, $levels);
        if (!preg_match('/^[0-9]{6,12}$/', $nationalId)) {
            $errors[] = 'National ID should be 6 to 12 digits.';
        }
        if (strlen($name) < 3) {
            $errors[] = 'Enter the full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
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
        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO users (national_id, full_name, email, phone, password_hash, role, department_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([$nationalId, $name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $role, $dept]);
                flash('success', role_label($role) . ' account created.');
                redirect('admin/users.php');
            } catch (PDOException $e) {
                $errors[] = 'That ID or email is already registered.';
            }
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? 'citizen';
        $dept = assigned_department($role, $_POST['department_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $errors = validate_access($role, $dept, $levels);
        if ($id === (int) $user['id'] && $role !== 'admin') {
            $errors[] = 'You cannot remove your own administrator access.';
        }
        if (!$errors) {
            if ($password !== '') {
                if (strlen($password) < 8) {
                    $errors[] = 'New password must be at least 8 characters.';
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
                if (!$errors) {
                    db()->prepare('UPDATE users SET role = ?, department_id = ?, password_hash = ? WHERE id = ?')
                        ->execute([$role, $dept, password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            } else {
                db()->prepare('UPDATE users SET role = ?, department_id = ? WHERE id = ?')
                    ->execute([$role, $dept, $id]);
            }
            if (!$errors) {
                flash('success', 'Access level updated.');
                redirect('admin/users.php');
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id !== (int) $user['id']) {
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('success', 'Account status updated.');
        }
        redirect('admin/users.php');
    }
}

$people = db()->query(
    'SELECT u.*, d.name AS department_name
     FROM users u LEFT JOIN departments d ON d.id = u.department_id
     ORDER BY FIELD(u.role, "admin", "supervisor", "officer", "citizen"), u.full_name'
)->fetchAll();

$title = page_title('Users');
$nav = 'dash';
$adminNav = 'users';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="split">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="grow">
        <h1>Users and access</h1>
        <div class="grid cards-2">
            <?php foreach ($levels as $key => $meta): ?>
                <div class="card">
                    <h3><?= e($meta['label']) ?></h3>
                    <p class="muted"><?= e($meta['help']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <form class="card" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <h2>Add a user</h2>
            <?php foreach ($errors as $error): ?><div class="alert bad"><?= e($error) ?></div><?php endforeach; ?>
            <div class="grid cards-2">
                <div>
                    <label>National ID</label>
                    <input name="national_id" required value="<?= e($_POST['national_id'] ?? '') ?>">
                </div>
                <div>
                    <label>Full name</label>
                    <input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
                <div>
                    <label>Email</label>
                    <input name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div>
                    <label>Phone</label>
                    <input name="phone" required value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
                <div>
                    <label>Access level</label>
                    <select name="role" required>
                        <?php foreach ($levels as $key => $meta): ?>
                            <option value="<?= e($key) ?>" <?= (($_POST['role'] ?? 'citizen') === $key) ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">Not department-specific</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept['id'] ?>"><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="help">Required for department officers (they will only see requests from this department). Ignored for citizens.</p>
                </div>
            </div>
            <label>Temporary password</label>
            <input name="password" type="password" required>
            <p><button class="btn primary" type="submit">Create user</button></p>
        </form>

        <div class="card table-wrap">
            <h2>Existing accounts</h2>
            <table>
                <thead>
                    <tr><th>Person</th><th>Access</th><th>Status</th><th>Change access</th></tr>
                </thead>
                <tbody>
                <?php foreach ($people as $person): ?>
                    <tr>
                        <td>
                            <strong><?= e($person['full_name']) ?></strong><br>
                            <small><?= e($person['national_id']) ?> · <?= e($person['email']) ?></small>
                        </td>
                        <td>
                            <?= e(role_label($person['role'])) ?><br>
                            <?php if ($person['role'] === 'officer' && $person['department_name']): ?>
                                <span class="pill info" style="font-size: 0.75rem;"><?= e($person['department_name']) ?> only</span>
                            <?php else: ?>
                                <small class="muted"><?= e($person['department_name'] ?? 'All departments') ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $person['is_active'] ? 'Active' : 'Disabled' ?>
                            <?php if ((int) $person['id'] !== (int) $user['id']): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
                                    <button class="btn small" type="submit"><?= $person['is_active'] ? 'Disable' : 'Enable' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
                                <select name="role">
                                    <?php foreach ($levels as $key => $meta): ?>
                                        <option value="<?= e($key) ?>" <?= $person['role'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="department_id">
                                    <option value="">Not department-specific</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int) $dept['id'] ?>" <?= (int) $person['department_id'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="password" type="password" placeholder="New password (optional)">
                                <button class="btn small" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
