<?php
require_once __DIR__ . '/includes/functions.php';
$title = page_title('Services');
$nav = 'services';
require __DIR__ . '/includes/header.php';

$q = trim($_GET['q'] ?? '');
$deptSlug = trim($_GET['department'] ?? '');

$sql = 'SELECT s.*, d.name AS department_name, d.slug
        FROM services s
        JOIN departments d ON d.id = s.department_id
        WHERE s.is_active = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (s.name LIKE ? OR s.summary LIKE ? OR s.code LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
if ($deptSlug !== '') {
    $sql .= ' AND d.slug = ?';
    $params[] = $deptSlug;
}
$sql .= ' ORDER BY d.name, s.name';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();
$departments = active_departments();
?>
<div class="toolbar">
    <div>
        <p class="kicker">Citizen requests</p>
        <h1>Submit a service request</h1>
    </div>
    <form class="row grow" method="get">
        <input class="grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Search services">
        <select name="department">
            <option value="">All departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= e($dept['slug']) ?>" <?= $deptSlug === $dept['slug'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
</div>

<?php if (!$services): ?>
    <div class="card">No services matched that search.</div>
<?php endif; ?>

<div class="grid cards-2">
    <?php foreach ($services as $service): ?>
        <article class="card">
            <p class="kicker"><?= e($service['department_name']) ?> · <?= e($service['code']) ?></p>
            <h3><?= e($service['name']) ?></h3>
            <p><?= e($service['summary']) ?></p>
            <p><?= e(service_cost_label($service)) ?></p>
            <a class="btn primary small" href="<?= e(url('apply.php?service=' . $service['id'])) ?>">Submit request</a>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
