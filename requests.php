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

$status = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$deptFilter = (int) ($_GET['department'] ?? 0);

$sql = 'SELECT r.*, s.name AS service_name, u.full_name, d.name AS department_name, d.id AS department_id
        FROM requests r
        JOIN services s ON s.id = r.service_id
        JOIN departments d ON d.id = s.department_id
        JOIN users u ON u.id = r.user_id
        WHERE 1=1';
[$scopeSql, $params] = department_scope_sql($user);
$sql .= $scopeSql;

// Admins and supervisors can optionally filter by department
if (in_array($user['role'], ['admin', 'supervisor'], true) && $deptFilter > 0) {
    $sql .= ' AND d.id = ?';
    $params[] = $deptFilter;
}

if ($status !== '') {
    $sql .= ' AND r.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $sql .= ' AND (r.tracking_code LIKE ? OR u.full_name LIKE ? OR u.national_id LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY r.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get all departments for the filter dropdown
$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

$title = page_title('Requests');
$nav = 'dash';
$adminNav = 'requests';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="split">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="grow">
        <div class="toolbar">
            <h1>Incoming requests</h1>
            <?php if ($user['role'] === 'officer' && isset($user['department_name']) && $user['department_name']): ?>
                <span class="pill muted"><?= e($user['department_name']) ?> Department</span>
            <?php endif; ?>
        </div>
        <form class="row" method="get">
            <input class="grow" name="q" value="<?= e($q) ?>" placeholder="Search name, ID, or tracking code">
            <?php if (in_array($user['role'], ['admin', 'supervisor'], true)): ?>
                <select name="department">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int) $dept['id'] ?>" <?= $deptFilter === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['submitted','awaiting_payment','paid','under_review','info_requested','approved','rejected','completed','cancelled'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e(status_meta($st)[0]) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Filter</button>
        </form>
        <div class="card table-wrap">
            <table>
                <thead>
                    <tr><th>Submitted</th><th>Code</th><th>Citizen</th><th>Service</th><th>Department</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): [$label, $tone] = status_meta($row['status']); ?>
                    <tr>
                        <td><?= e(date('d M Y', strtotime($row['created_at']))) ?></td>
                        <td><?= e($row['tracking_code']) ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['service_name']) ?></td>
                        <td><small class="muted"><?= e($row['department_name']) ?></small></td>
                        <td><span class="pill <?= e($tone) ?>"><?= e($label) ?></span></td>
                        <td>
                            <a href="<?= e(url('admin/request.php?id=' . $row['id'])) ?>">Open</a>
                            · <button class="btn btn-pdf small" data-pdf-filename="<?= e($row['tracking_code']) ?>" style="margin-left: 0.3rem;">PDF</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$rows): ?><p>No requests match those filters.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
