<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT r.*, s.name AS service_name, s.fee
     FROM requests r JOIN services s ON s.id = r.service_id
     WHERE r.id = ? AND r.user_id = ?'
);
$stmt->execute([$id, $user['id']]);
$request = $stmt->fetch();
if (!$request || $request['status'] !== 'awaiting_payment') {
    flash('error', 'There is no outstanding payment for that request.');
    redirect('dashboard.php');
}

$payStmt = db()->prepare('SELECT * FROM payments WHERE request_id = ? ORDER BY id DESC LIMIT 1');
$payStmt->execute([$id]);
$payment = $payStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $method = $_POST['method'] ?? 'M-Pesa';
    $phone = trim($_POST['phone'] ?? $user['phone']);
    $ref = 'MP' . strtoupper(bin2hex(random_bytes(5)));

    $pdo = db();
    $pdo->beginTransaction();
    if ($payment) {
        $pdo->prepare(
            'UPDATE payments SET method = ?, reference = ?, status = ?, paid_at = NOW() WHERE id = ?'
        )->execute([$method, $ref, 'paid', $payment['id']]);
    } else {
        $pdo->prepare(
            'INSERT INTO payments (request_id, amount, method, reference, status, paid_at) VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$id, $request['fee'], $method, $ref, 'paid']);
    }
    $pdo->prepare('UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['paid', $id]);
    add_timeline($id, 'paid', 'Payment received via ' . $method . ' (' . $ref . ').', (int) $user['id']);
    $pdo->commit();
    notify(
        (int) $user['id'],
        'Payment received for ' . $request['tracking_code'],
        'Payment was recorded. Officers can now review your request.',
        $id,
        $request['tracking_code']
    );

    flash('success', 'Payment confirmed with reference ' . $ref . '. A confirmation was sent by email and SMS.');
    redirect('request.php?id=' . $id);
}

$title = page_title('Pay');
require __DIR__ . '/includes/header.php';
?>
<p class="kicker">Simulated checkout</p>
<h1>Pay for <?= e($request['service_name']) ?></h1>
<p>Amount due <strong><?= e(money((float) $request['fee'])) ?></strong> for <?= e($request['tracking_code']) ?>.</p>

<form class="card" method="post" style="max-width:480px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
    <label for="method">Payment method</label>
    <select id="method" name="method">
        <option>M-Pesa</option>
        <option>Card</option>
        <option>Bank deposit</option>
    </select>
    <label for="phone">Mobile number / payer details</label>
    <input id="phone" name="phone" value="<?= e($user['phone']) ?>" required>
    <p class="help">This is a classroom simulation. No real money is collected.</p>
    <p><button class="btn primary" type="submit">Confirm payment</button></p>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
