<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT r.*, s.name AS service_name, s.fee, s.form_fields, d.name AS department_name, d.id AS department_id
     FROM requests r
     JOIN services s ON s.id = r.service_id
     JOIN departments d ON d.id = s.department_id
     WHERE r.id = ?'
);
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) {
    flash('error', 'Request not found.');
    redirect('dashboard.php');
}

$isOwner = (int) $request['user_id'] === (int) $user['id'];
$isStaff = in_array($user['role'], ['admin', 'officer'], true);
if (!$isOwner && !$isStaff) {
    flash('error', 'You cannot view that request.');
    redirect('dashboard.php');
}
if ($isStaff && !$isOwner && !can_manage_request($user, $request)) {
    flash('error', 'That request belongs to another department.');
    redirect('admin/index.php');
}

$timeline = db()->prepare(
    'SELECT t.*, u.full_name
     FROM request_timeline t
     LEFT JOIN users u ON u.id = t.actor_id
     WHERE t.request_id = ?
     ORDER BY t.created_at'
);
$timeline->execute([$id]);
$events = $timeline->fetchAll();

$payStmt = db()->prepare('SELECT * FROM payments WHERE request_id = ? ORDER BY id DESC');
$payStmt->execute([$id]);
$payments = $payStmt->fetchAll();

$fields = service_fields(['form_fields' => $request['form_fields']]);
$payload = request_payload($request);
[$label, $tone] = status_meta($request['status']);

$title = page_title($request['tracking_code']);
require __DIR__ . '/includes/header.php';
?>
<p class="kicker"><?= e($request['department_name']) ?></p>
<div class="toolbar">
    <div>
        <h1><?= e($request['service_name']) ?></h1>
        <p>Tracking code <strong><?= e($request['tracking_code']) ?></strong></p>
    </div>
    <div>
        <span class="pill <?= e($tone) ?>"><?= e($label) ?></span>
        <button class="btn btn-pdf small" data-pdf-filename="<?= e($request['tracking_code']) ?>" style="margin-left: 0.5rem;">Download PDF</button>
    </div>
</div>

<div class="split">
    <section class="card">
        <h2>Application details</h2>
        <?php foreach ($fields as $field): ?>
            <p><strong><?= e($field['label']) ?></strong><br><?= e($payload[$field['name']] ?? '—') ?></p>
        <?php endforeach; ?>
        <?php if ($request['attachment']): ?>
            <p><a href="<?= e(url('uploads/' . $request['attachment'])) ?>" target="_blank">Download attachment</a></p>
        <?php endif; ?>
        <?php if (isset($request['completion_photo']) && $request['completion_photo']): ?>
            <h3>Completion Photo</h3>
            <p><a href="<?= e(url('uploads/' . $request['completion_photo'])) ?>" target="_blank">View completion photo</a></p>
        <?php endif; ?>
        <?php if ($request['status'] === 'awaiting_payment' && $isOwner): ?>
            <p><a class="btn primary" href="<?= e(url('pay.php?id=' . $request['id'])) ?>">Pay <?= e(money((float) $request['fee'])) ?></a></p>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2>Timeline</h2>
        <ol class="timeline">
            <?php foreach ($events as $event): ?>
                <li>
                    <strong><?= e(status_meta($event['status'])[0]) ?></strong>
                    <div><?= e($event['note']) ?></div>
                    <small class="muted"><?= e(date('d M Y H:i', strtotime($event['created_at']))) ?><?= $event['full_name'] ? ' · ' . e($event['full_name']) : '' ?></small>
                </li>
            <?php endforeach; ?>
        </ol>
        <h3>Payments</h3>
        <?php if (!$payments): ?>
            <p class="muted">No payment required or recorded.</p>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <p><?= e($payment['reference']) ?> · <?= e(money((float) $payment['amount'])) ?> · <?= e($payment['status']) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
