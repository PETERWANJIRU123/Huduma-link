<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
if ($user['role'] !== 'citizen') {
    redirect('admin/index.php');
}

$stmt = db()->prepare(
    'SELECT r.*, s.name AS service_name, s.fee
     FROM requests r JOIN services s ON s.id = r.service_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC'
);
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

$counts = ['all' => count($requests), 'open' => 0, 'done' => 0];
foreach ($requests as $row) {
    if (in_array($row['status'], ['completed', 'rejected', 'cancelled'], true)) {
        $counts['done']++;
    } else {
        $counts['open']++;
    }
}

$title = page_title('My desk');
$nav = 'dash';
require __DIR__ . '/includes/header.php';
?>
<div class="toolbar">
    <div>
        <p class="kicker">Citizen desk</p>
        <h1>Hello, <?= e(explode(' ', $user['full_name'])[0]) ?></h1>
        <p class="muted">National ID <?= e($user['national_id']) ?></p>
    </div>
    <a class="btn primary" href="<?= e(url('services.php')) ?>">New request</a>
</div>

<div class="grid cards-3">
    <div class="card stat"><span>All requests</span><b><?= (int) $counts['all'] ?></b></div>
    <div class="card stat"><span>In progress</span><b><?= (int) $counts['open'] ?></b></div>
    <div class="card stat"><span>Closed</span><b><?= (int) $counts['done'] ?></b></div>
</div>

<div class="card table-wrap">
    <h2>Recent applications</h2>
    <?php if (!$requests): ?>
        <p>You have not submitted any requests yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Tracking</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $row): [$label, $tone] = status_meta($row['status']); ?>
                <tr>
                    <td><?= e($row['tracking_code']) ?></td>
                    <td><?= e($row['service_name']) ?></td>
                    <td><span class="pill <?= e($tone) ?>"><?= e($label) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($row['created_at']))) ?></td>
                    <td>
                        <a href="<?= e(url('request.php?id=' . $row['id'])) ?>">View</a>
                        <?php if ($row['status'] === 'awaiting_payment'): ?>
                            · <a href="<?= e(url('pay.php?id=' . $row['id'])) ?>">Pay</a>
                        <?php endif; ?>
                        · <button class="btn btn-pdf small" data-pdf-filename="<?= e($row['tracking_code']) ?>" style="margin-left: 0.3rem;">PDF</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<p><a href="<?= e(url('profile.php')) ?>">Update contact details</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
