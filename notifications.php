<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$notes = $stmt->fetchAll();

$deliveries = [];
$del = db()->prepare(
    'SELECT * FROM message_deliveries WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
);
$del->execute([$user['id']]);
foreach ($del as $row) {
    $key = (string) ($row['notification_id'] ?: $row['id']);
    $deliveries[$key][] = $row;
}

$title = page_title('Alerts');
$nav = 'alerts';
require __DIR__ . '/includes/header.php';
?>
<h1>Alerts</h1>
<p class="muted">The same updates are emailed and texted when those channels are switched on in your profile.</p>
<?php if (!$notes): ?>
    <div class="card">No alerts yet.</div>
<?php endif; ?>
<div class="grid">
    <?php foreach ($notes as $note): ?>
        <article class="card">
            <h3><?= e($note['title']) ?></h3>
            <p><?= e($note['body']) ?></p>
            <p class="muted"><?= e(date('d M Y H:i', strtotime($note['created_at']))) ?></p>
            <?php foreach ($deliveries[(string) $note['id']] ?? [] as $sent): ?>
                <span class="pill <?= $sent['status'] === 'sent' ? 'ok' : ($sent['status'] === 'failed' ? 'bad' : 'muted') ?>">
                    <?= e(strtoupper($sent['channel'])) ?> <?= e($sent['status']) ?>
                </span>
            <?php endforeach; ?>
            <?php if ($note['request_id']): ?>
                <p><a href="<?= e(url('request.php?id=' . $note['request_id'])) ?>">Open request</a></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
