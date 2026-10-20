<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_staff();

// Load department name for officers
if ($user['role'] === 'officer' && $user['department_id']) {
    $deptStmt = db()->prepare('SELECT name FROM departments WHERE id = ?');
    $deptStmt->execute([$user['department_id']]);
    $dept = $deptStmt->fetch();
    $user['department_name'] = $dept['name'] ?? null;
}

$deptFilter = (int) ($_GET['department'] ?? 0);
[$scopeSql, $params] = department_scope_sql($user);
$where = $scopeSql !== '' ? ' WHERE ' . ltrim($scopeSql, ' AND ') : '';

// Admins and supervisors can optionally filter by department
if (in_array($user['role'], ['admin', 'supervisor'], true) && $deptFilter > 0) {
    $where .= ($where !== '' ? ' AND ' : ' WHERE ') . 'd.id = ?';
    $params[] = $deptFilter;
}

$stats = [
    'open' => 0,
    'awaiting_payment' => 0,
    'under_review' => 0,
    'completed' => 0,
];
$sql = "SELECT r.status, COUNT(*) c
        FROM requests r
        JOIN services s ON s.id = r.service_id
        JOIN departments d ON d.id = s.department_id
        $where
        GROUP BY r.status";
$stmt = db()->prepare($sql);
$stmt->execute($params);
foreach ($stmt as $row) {
    if (in_array($row['status'], ['awaiting_payment', 'under_review', 'completed'], true)) {
        $stats[$row['status']] = (int) $row['c'];
    }
    if (!in_array($row['status'], ['completed', 'rejected', 'cancelled'], true)) {
        $stats['open'] += (int) $row['c'];
    }
}

$listSql = "SELECT r.*, s.name AS service_name, u.full_name, d.name AS department_name
            FROM requests r
            JOIN services s ON s.id = r.service_id
            JOIN departments d ON d.id = s.department_id
            JOIN users u ON u.id = r.user_id
            $where
            ORDER BY r.updated_at DESC
            LIMIT 12";
$list = db()->prepare($listSql);
$list->execute($params);
$recent = $list->fetchAll();

// Get all departments for the filter dropdown
$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

$title = page_title('Staff console');
$nav = 'dash';
$adminNav = 'home';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="toolbar">
    <div>
        <p class="kicker">Staff console</p>
        <h1><?= e($user['full_name']) ?></h1>
        <p class="muted"><?= e(role_label($user['role'])) ?><?php if ($user['role'] === 'officer' && isset($user['department_name']) && $user['department_name']): ?> · <?= e($user['department_name']) ?> Department<?php endif; ?></p>
    </div>
    <?php if (in_array($user['role'], ['admin', 'supervisor'], true)): ?>
        <form method="get" class="row">
            <select name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>" <?= $deptFilter === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn small" type="submit">Filter</button>
        </form>
    <?php endif; ?>
</div>
<div class="split">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="grow">
        <?php if ($user['role'] === 'officer' && isset($user['department_name']) && $user['department_name']): ?>
            <div class="card" style="background: linear-gradient(135deg, var(--forest), var(--forest-dark)); color: var(--gold-soft); margin-bottom: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="font-size: 2rem;">🏛️</span>
                    <div>
                        <p class="kicker" style="color: var(--gold);">Your Department</p>
                        <h2 style="margin: 0.2rem 0 0;"><?= e($user['department_name']) ?></h2>
                        <p style="margin: 0.3rem 0 0; opacity: 0.9;">Viewing requests from this department only</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="grid cards-3">
            <div class="card stat"><span>Open queue</span><b><?= (int) $stats['open'] ?></b></div>
            <div class="card stat"><span>Awaiting payment</span><b><?= (int) $stats['awaiting_payment'] ?></b></div>
            <div class="card stat"><span>Completed</span><b><?= (int) $stats['completed'] ?></b></div>
        </div>
        <div class="card table-wrap">
            <h2>Latest requests<?php if ($user['role'] === 'officer' && isset($user['department_name']) && $user['department_name']): ?> · <?= e($user['department_name']) ?><?php endif; ?></h2>
            <table>
                <thead>
                    <tr><th>Code</th><th>Citizen</th><th>Service</th><th>Department</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row): [$label, $tone] = status_meta($row['status']); ?>
                    <tr>
                        <td><?= e($row['tracking_code']) ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['service_name']) ?></td>
                        <td><small class="muted"><?= e($row['department_name']) ?></small></td>
                        <td><span class="pill <?= e($tone) ?>"><?= e($label) ?></span></td>
                        <td>
                            <a href="<?= e(url('admin/request.php?id=' . $row['id'])) ?>">Process</a>
                            · <button class="btn btn-pdf small" data-pdf-filename="<?= e($row['tracking_code']) ?>" style="margin-left: 0.3rem;">PDF</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
