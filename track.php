<?php
require_once __DIR__ . '/includes/functions.php';

$result = null;
$events = [];
$error = '';
$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code !== '') {
    $stmt = db()->prepare(
        'SELECT r.*, s.name AS service_name, d.name AS department_name
         FROM requests r
         JOIN services s ON s.id = r.service_id
         JOIN departments d ON d.id = s.department_id
         WHERE r.tracking_code = ?'
    );
    $stmt->execute([$code]);
    $result = $stmt->fetch();
    if (!$result) {
        $error = 'No request uses that tracking code.';
    } else {
        $tl = db()->prepare('SELECT * FROM request_timeline WHERE request_id = ? ORDER BY created_at');
        $tl->execute([$result['id']]);
        $events = $tl->fetchAll();
    }
}

$title = page_title('Track request');
$nav = 'track';
require __DIR__ . '/includes/header.php';
?>
<p class="kicker">Public tracker</p>
<h1>Follow a request</h1>
<p>Enter the tracking code from your application receipt. You do not need to sign in.</p>

<form class="card row" method="get" style="max-width:640px">
    <input class="grow" name="code" value="<?= e($code) ?>" placeholder="e.g. HL-2026-A1B2C3D4" required>
    <button class="btn primary" type="submit">Track</button>
</form>

<?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>

<?php if ($result): [$label, $tone] = status_meta($result['status']); ?>
    <article class="card">
        <div class="toolbar">
            <div>
                <h2><?= e($result['service_name']) ?></h2>
                <p class="muted"><?= e($result['department_name']) ?> · <?= e($result['tracking_code']) ?></p>
            </div>
            <div>
                <span class="pill <?= e($tone) ?>"><?= e($label) ?></span>
                <button class="btn btn-pdf small" data-pdf-filename="<?= e($result['tracking_code']) ?>" style="margin-left: 0.5rem;">Download PDF</button>
            </div>
        </div>
        <ol class="timeline">
            <?php foreach ($events as $event): ?>
                <li>
                    <strong><?= e(status_meta($event['status'])[0]) ?></strong>
                    <div><?= e($event['note']) ?></div>
                    <small class="muted"><?= e(date('d M Y H:i', strtotime($event['created_at']))) ?></small>
                </li>
            <?php endforeach; ?>
        </ol>
    </article>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
