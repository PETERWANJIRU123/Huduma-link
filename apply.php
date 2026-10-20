<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
if ($user['role'] !== 'citizen') {
    flash('error', 'Staff accounts cannot submit citizen requests.');
    redirect('admin/index.php');
}

$serviceId = (int) ($_GET['service'] ?? $_POST['service_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT s.*, d.name AS department_name
     FROM services s JOIN departments d ON d.id = s.department_id
     WHERE s.id = ? AND s.is_active = 1'
);
$stmt->execute([$serviceId]);
$service = $stmt->fetch();
if (!$service) {
    flash('error', 'That service is not available.');
    redirect('services.php');
}

$fields = service_fields($service);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $payload = [];
    foreach ($fields as $field) {
        $name = $field['name'];
        $value = trim((string) ($_POST[$name] ?? ''));
        if (!empty($field['required']) && $value === '') {
            $errors[] = $field['label'] . ' is required.';
        }
        $payload[$name] = $value;
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $status = ((float) $service['fee'] > 0) ? 'awaiting_payment' : 'submitted';
            $code = tracking_code();
            $ins = $pdo->prepare(
                'INSERT INTO requests (tracking_code, user_id, service_id, status, payload)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$code, $user['id'], $service['id'], $status, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $requestId = (int) $pdo->lastInsertId();

            if (!empty($_FILES['attachment']['name'])) {
                $stored = upload_attachment($_FILES['attachment'], $requestId);
                $pdo->prepare('UPDATE requests SET attachment = ? WHERE id = ?')->execute([$stored, $requestId]);
            }

            add_timeline($requestId, $status, 'Application submitted.', (int) $user['id']);

            if ((float) $service['fee'] > 0) {
                $ref = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
                $pdo->prepare(
                    'INSERT INTO payments (request_id, amount, method, reference, status) VALUES (?, ?, ?, ?, ?)'
                )->execute([$requestId, $service['fee'], 'pending', $ref, 'pending']);
            }

            $pdo->commit();
            $next = $status === 'awaiting_payment'
                ? 'Pay the fee to send it to officers. Tracking code: ' . $code
                : 'Officers will review it. Tracking code: ' . $code;
            notify(
                (int) $user['id'],
                'Request ' . $code . ' received',
                'Your ' . $service['name'] . ' request has been received. ' . $next,
                $requestId,
                $code
            );
            flash('success', 'Request submitted. Tracking code: ' . $code . '. We also sent email and SMS updates.');
            redirect((float) $service['fee'] > 0 ? 'pay.php?id=' . $requestId : 'request.php?id=' . $requestId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$title = page_title('Apply');
require __DIR__ . '/includes/header.php';
?>
<p class="kicker"><?= e($service['department_name']) ?></p>
<h1><?= e($service['name']) ?></h1>
<p><?= e($service['summary']) ?></p>
<p><?= e(service_cost_label($service)) ?></p>

<div class="split">
    <form class="card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
        <?php foreach ($errors as $error): ?><div class="alert bad"><?= e($error) ?></div><?php endforeach; ?>
        <?php foreach ($fields as $field): ?>
            <label for="<?= e($field['name']) ?>"><?= e($field['label']) ?></label>
            <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                <textarea id="<?= e($field['name']) ?>" name="<?= e($field['name']) ?>" <?= !empty($field['required']) ? 'required' : '' ?>><?= e($_POST[$field['name']] ?? '') ?></textarea>
            <?php elseif (($field['type'] ?? '') === 'select'): ?>
                <select id="<?= e($field['name']) ?>" name="<?= e($field['name']) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                    <option value="">Select</option>
                    <?php foreach ($field['options'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= (($_POST[$field['name']] ?? '') === $option) ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input id="<?= e($field['name']) ?>" name="<?= e($field['name']) ?>" type="<?= e($field['type'] ?? 'text') ?>"
                       value="<?= e($_POST[$field['name']] ?? '') ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
            <?php endif; ?>
        <?php endforeach; ?>
        <label for="attachment">Supporting document (optional)</label>
        <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png">
        <p class="help">PDF, JPG, or PNG up to 4 MB.</p>
        <p><button class="btn primary" type="submit">Submit request</button></p>
    </form>
    <aside class="card">
        <h3>What you need</h3>
        <div class="req-box"><?= e($service['requirements']) ?></div>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
