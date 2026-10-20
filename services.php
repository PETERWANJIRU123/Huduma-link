<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_admin();
$errors = [];
$editId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);

$blankService = [
    'id' => 0,
    'department_id' => '',
    'name' => '',
    'code' => '',
    'summary' => '',
    'fee' => '0.00',
    'processing_days' => 3,
    'requirements' => '',
    'form_fields' => '[]',
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'add_department') {
        $name = trim($_POST['dept_name'] ?? '');
        $summary = trim($_POST['dept_summary'] ?? '');
        $icon = trim($_POST['dept_icon'] ?? '⬤');
        if ($name === '' || $summary === '') {
            flash('error', 'Department name and summary are required.');
        } else {
            $slug = slugify($name);
            try {
                db()->prepare('INSERT INTO departments (name, slug, summary, icon) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $slug, $summary, $icon !== '' ? $icon : '⬤']);
                flash('success', 'Department added. You can now attach services to it.');
            } catch (PDOException $e) {
                flash('error', 'That department already exists.');
            }
        }
        redirect('admin/services.php');
    }

    if ($action === 'delete_department') {
        $deptId = (int) ($_POST['department_id'] ?? 0);
        $used = db()->prepare('SELECT COUNT(*) FROM services WHERE department_id = ?');
        $used->execute([$deptId]);
        if ((int) $used->fetchColumn() > 0) {
            flash('error', 'Remove or move the services in that department first.');
        } else {
            db()->prepare('DELETE FROM departments WHERE id = ?')->execute([$deptId]);
            flash('success', 'Department removed.');
        }
        redirect('admin/services.php');
    }

    if ($action === 'restore_defaults') {
        seed_civic_catalogue(db(), true);
        flash('success', 'Default civic requests (streetlight, water, garbage, bursary) are published.');
        redirect('admin/services.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (request_count_for_service($id) > 0) {
            db()->prepare('UPDATE services SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash('success', 'This service already has citizen requests, so it was unpublished instead of deleted.');
        } else {
            db()->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
            flash('success', 'Service removed.');
        }
        redirect('admin/services.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $summary = trim($_POST['summary'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $fee = max(0, (float) ($_POST['fee'] ?? 0));
    $days = max(1, (int) ($_POST['processing_days'] ?? 1));
    $active = isset($_POST['is_active']) ? 1 : 0;
    $fields = parse_admin_fields($_POST['fields'] ?? []);

    if ($name === '' || $summary === '') {
        $errors[] = 'Name and short description are required.';
    }
    if ($departmentId < 1) {
        $errors[] = 'Choose a department.';
    }
    if ($code === '') {
        $code = strtoupper(substr(slugify($name), 0, 12));
    }
    if (!$fields) {
        $errors[] = 'Add at least one form field so citizens can describe the request.';
    }

    if (!$errors) {
        $payload = json_encode($fields, JSON_UNESCAPED_UNICODE);
        try {
            if ($id > 0) {
                db()->prepare(
                    'UPDATE services
                     SET department_id = ?, name = ?, code = ?, summary = ?, fee = ?, processing_days = ?, requirements = ?, form_fields = ?, is_active = ?
                     WHERE id = ?'
                )->execute([$departmentId, $name, $code, $summary, $fee, $days, $requirements, $payload, $active, $id]);
                flash('success', 'Service updated.');
            } else {
                db()->prepare(
                    'INSERT INTO services (department_id, name, code, summary, fee, processing_days, requirements, form_fields, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$departmentId, $name, $code, $summary, $fee, $days, $requirements, $payload, $active]);
                flash('success', 'Service added. Citizens can submit it from the catalogue.');
            }
            redirect('admin/services.php');
        } catch (PDOException $e) {
            $errors[] = 'That service code is already in use. Choose another code.';
        }
    }

    $blankService = array_merge($blankService, [
        'id' => $id,
        'department_id' => $departmentId,
        'name' => $name,
        'code' => $code,
        'summary' => $summary,
        'fee' => $fee,
        'processing_days' => $days,
        'requirements' => $requirements,
        'form_fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        'is_active' => $active,
    ]);
    $editId = $id;
}

$departments = db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();
$services = db()->query(
    'SELECT s.*, d.name AS department_name
     FROM services s JOIN departments d ON d.id = s.department_id
     ORDER BY s.is_active DESC, d.name, s.name'
)->fetchAll();

$editing = $blankService;
if ($editId && !$errors) {
    foreach ($services as $row) {
        if ((int) $row['id'] === $editId) {
            $editing = $row;
            break;
        }
    }
}
$fieldRows = fields_for_admin_form(service_fields($editing));

$title = page_title('Services');
$nav = 'dash';
$adminNav = 'services';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="split">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="grow">
        <div class="toolbar">
            <div>
                <p class="kicker">Catalogue</p>
                <h1>Add or remove services</h1>
                <p class="muted">Citizens only see published services. Department officers process requests in their assigned department.</p>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore_defaults">
                <button class="btn" type="submit">Restore default civic requests</button>
            </form>
        </div>

        <section class="card">
            <h2>Departments</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_department">
                <div class="grid cards-3">
                    <div>
                        <label>Name</label>
                        <input name="dept_name" required placeholder="e.g. Roads">
                    </div>
                    <div>
                        <label>Summary</label>
                        <input name="dept_summary" required placeholder="What citizens report here">
                    </div>
                    <div>
                        <label>Icon</label>
                        <input name="dept_icon" maxlength="8" placeholder="🛣️">
                    </div>
                </div>
                <p><button class="btn" type="submit">Add department</button></p>
            </form>
            <?php if ($departments): ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Department</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?= e($dept['icon']) ?> <?= e($dept['name']) ?><br><small class="muted"><?= e($dept['summary']) ?></small></td>
                                <td>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_department">
                                        <input type="hidden" name="department_id" value="<?= (int) $dept['id'] ?>">
                                        <button class="btn small danger" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <form class="card" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <h2><?= $editing['id'] ? 'Edit service' : 'Add a service' ?></h2>
            <?php foreach ($errors as $error): ?><div class="alert bad"><?= e($error) ?></div><?php endforeach; ?>
            <div class="grid cards-2">
                <div>
                    <label for="name">Service name</label>
                    <input id="name" name="name" required value="<?= e($editing['name']) ?>" placeholder="e.g. Broken Streetlight">
                </div>
                <div>
                    <label for="code">Short code</label>
                    <input id="code" name="code" value="<?= e($editing['code']) ?>" placeholder="LIT-LIGHT">
                </div>
                <div>
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Select</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept['id'] ?>" <?= (int) $editing['department_id'] === (int) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="processing_days">Typical response days</label>
                    <input id="processing_days" name="processing_days" type="number" min="1" value="<?= e($editing['processing_days']) ?>">
                </div>
            </div>
            <label for="summary">What citizens see</label>
            <textarea id="summary" name="summary" required><?= e($editing['summary']) ?></textarea>
            <label for="requirements">What to bring / know</label>
            <textarea id="requirements" name="requirements"><?= e($editing['requirements']) ?></textarea>
            <div class="row">
                <div>
                    <label for="fee">Fee (KES, 0 if free)</label>
                    <input id="fee" name="fee" type="number" min="0" step="0.01" value="<?= e($editing['fee']) ?>">
                </div>
                <label>
                    <input type="checkbox" name="is_active" value="1" <?= !empty($editing['is_active']) ? 'checked' : '' ?>>
                    Published for citizens
                </label>
            </div>

            <h3>Request form fields</h3>
            <p class="help">Each row becomes a question on the citizen form. For dropdowns, list options separated by commas.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Dropdown options</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fieldRows as $i => $row): ?>
                        <tr>
                            <td><input name="fields[<?= (int) $i ?>][label]" value="<?= e($row['label']) ?>" placeholder="Location"></td>
                            <td>
                                <select name="fields[<?= (int) $i ?>][type]">
                                    <?php foreach (['text' => 'Short text', 'textarea' => 'Long text', 'date' => 'Date', 'select' => 'Dropdown'] as $type => $label): ?>
                                        <option value="<?= e($type) ?>" <?= $row['type'] === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input name="fields[<?= (int) $i ?>][options]" value="<?= e($row['options']) ?>" placeholder="Out, Flickering, Damaged"></td>
                            <td><input type="checkbox" name="fields[<?= (int) $i ?>][required]" value="1" <?= $row['required'] ? 'checked' : '' ?>></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p>
                <button class="btn primary" type="submit"><?= $editing['id'] ? 'Save changes' : 'Add service' ?></button>
                <?php if ($editing['id']): ?>
                    <a class="btn" href="<?= e(url('admin/services.php')) ?>">Cancel edit</a>
                <?php endif; ?>
            </p>
        </form>

        <div class="card table-wrap">
            <h2>Current services</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Requests</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td>
                            <strong><?= e($service['name']) ?></strong><br>
                            <small class="muted"><?= e($service['code']) ?> · <?= e(service_cost_label($service)) ?></small>
                        </td>
                        <td><?= e($service['department_name']) ?></td>
                        <td><?= $service['is_active'] ? 'Published' : 'Unpublished' ?></td>
                        <td><?= request_count_for_service((int) $service['id']) ?></td>
                        <td>
                            <a class="btn small" href="<?= e(url('admin/services.php?edit=' . $service['id'])) ?>">Edit</a>
                            <form method="post" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                <button class="btn small danger" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$services): ?><p>No services yet. Add one above or restore the defaults.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
