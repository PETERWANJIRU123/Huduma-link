<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $channel = $_POST['channel'] ?? 'email';
    $target = trim($_POST['target'] ?? '');
    $subject = 'HudumaLink test message';
    $body = 'This is a test progress alert from ' . APP_NAME . '.';
    try {
        if ($channel === 'email') {
            if (!mail_is_configured()) {
                throw new RuntimeException('Set MAIL_USER and MAIL_PASS in config/config.php first.');
            }
            if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }
            send_smtp_email($target, $subject, progress_email_html('Tester', $subject, $body, public_url('track.php')), $body);
            log_delivery((int) $user['id'], null, null, 'email', $target, $subject, $body, 'sent', 'Admin test');
            flash('success', 'Test email accepted by the mail server.');
        } else {
            if (!sms_is_configured()) {
                throw new RuntimeException('Set AT_API_KEY in config/config.php first.');
            }
            $msisdn = kenya_msisdn($target);
            if (strlen($msisdn) < 11) {
                throw new RuntimeException('Enter a valid Kenyan mobile number.');
            }
            $raw = send_at_sms($msisdn, APP_NAME . ': ' . $body);
            log_delivery((int) $user['id'], null, null, 'sms', '+' . $msisdn, $subject, $body, 'sent', $raw);
            flash('success', 'Test SMS was handed to Africa\'s Talking.');
        }
        redirect('admin/messages.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$logs = db()->query(
    'SELECT m.*, u.full_name
     FROM message_deliveries m
     LEFT JOIN users u ON u.id = m.user_id
     ORDER BY m.created_at DESC
     LIMIT 80'
)->fetchAll();

$title = page_title('Email & SMS');
$nav = 'dash';
$adminNav = 'messages';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="split">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="grow">
        <p class="kicker">Outbound alerts</p>
        <h1>Email and SMS</h1>
        <div class="grid cards-2">
            <div class="card">
                <h3>Email (SMTP)</h3>
                <p><?= mail_is_configured() ? 'Configured — messages will be sent.' : 'Not configured. Add MAIL_USER and MAIL_PASS in config/config.php.' ?></p>
                <p class="muted"><?= e(MAIL_HOST) ?>:<?= (int) MAIL_PORT ?> · from <?= e(MAIL_FROM) ?></p>
            </div>
            <div class="card">
                <h3>SMS (Africa's Talking)</h3>
                <p><?= sms_is_configured() ? 'Configured — texts will be sent.' : 'Not configured. Add AT_API_KEY in config/config.php.' ?></p>
                <p class="muted"><?= AT_SANDBOX ? 'Sandbox API' : 'Live API' ?> · user <?= e(AT_USERNAME) ?></p>
            </div>
        </div>

        <form class="card" method="post">
            <?= csrf_field() ?>
            <h2>Send a test</h2>
            <?php foreach ($errors as $error): ?><div class="alert bad"><?= e($error) ?></div><?php endforeach; ?>
            <label for="channel">Channel</label>
            <select id="channel" name="channel">
                <option value="email">Email</option>
                <option value="sms">SMS</option>
            </select>
            <label for="target">Email address or phone number</label>
            <input id="target" name="target" required placeholder="you@example.com or 0712345678">
            <p><button class="btn primary" type="submit">Send test</button></p>
        </form>

        <div class="card table-wrap">
            <h2>Delivery log</h2>
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Channel</th>
                        <th>To</th>
                        <th>Citizen</th>
                        <th>Status</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $row): ?>
                    <tr>
                        <td><?= e(date('d M Y H:i', strtotime($row['created_at']))) ?></td>
                        <td><?= e(strtoupper($row['channel'])) ?></td>
                        <td><?= e($row['recipient']) ?></td>
                        <td><?= e($row['full_name'] ?? '—') ?></td>
                        <td><span class="pill <?= $row['status'] === 'sent' ? 'ok' : ($row['status'] === 'failed' ? 'bad' : 'muted') ?>"><?= e($row['status']) ?></span></td>
                        <td><small><?= e($row['subject']) ?><br><?= e($row['provider_response']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$logs): ?><p>No email or SMS attempts yet. Submit or update a request to generate one.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
