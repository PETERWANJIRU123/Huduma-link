<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/messaging.php';

$installed = false;
$error = '';
$refreshed = false;

try {
    $root = new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `' . DB_NAME . '`');

    $exists = $root->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($exists) {
        $count = (int) $root->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            $installed = true;
        }
    }

    $action = $_POST['action'] ?? '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $action === 'refresh' && $installed) {
        ensure_user_roles($root);
        ensure_messaging_schema($root);
        seed_civic_catalogue($root, true);

        // Add completion_photo column if it doesn't exist
        $columnExists = $root->query("SHOW COLUMNS FROM requests LIKE 'completion_photo'")->fetch();
        if (!$columnExists) {
            $root->exec('ALTER TABLE requests ADD COLUMN completion_photo VARCHAR(180) NULL AFTER staff_note');
        }

        $refreshed = true;
    } elseif (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        $schema = preg_replace('/CREATE DATABASE.*?;/s', '', $schema, 1);
        $schema = preg_replace('/USE .*?;/', '', $schema, 1);
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $root->exec($statement);
        }

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        ensure_user_roles($pdo);
        ensure_messaging_schema($pdo);
        seed_civic_catalogue($pdo, false);

        $ids = [];
        foreach ($pdo->query('SELECT id, slug FROM departments') as $row) {
            $ids[$row['slug']] = (int) $row['id'];
        }

        $userStmt = $pdo->prepare(
            'INSERT INTO users (national_id, full_name, email, phone, password_hash, role, department_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $userStmt->execute([
            '00000001', 'System Administrator', 'admin@hudumalink.go.ke', '0700000001',
            password_hash('Admin@123', PASSWORD_DEFAULT), 'admin', null,
        ]);
        $userStmt->execute([
            '00000002', 'Street Lighting Officer', 'lighting@hudumalink.go.ke', '0700000002',
            password_hash('Officer@123', PASSWORD_DEFAULT), 'officer', $ids['lighting'],
        ]);
        $userStmt->execute([
            '00000003', 'County Supervisor', 'supervisor@hudumalink.go.ke', '0700000003',
            password_hash('Supervisor@123', PASSWORD_DEFAULT), 'supervisor', null,
        ]);
        $userStmt->execute([
            '12345678', 'Amina Wanjiku Otieno', 'citizen@hudumalink.go.ke', '0712345678',
            password_hash('Citizen@123', PASSWORD_DEFAULT), 'citizen', null,
        ]);

        $installed = true;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-shell">
        <div class="card auth-card">
            <p class="kicker">Setup</p>
            <h1>Install <?= htmlspecialchars(APP_NAME) ?></h1>
            <?php if ($error): ?>
                <div class="alert bad"><?= htmlspecialchars($error) ?></div>
                <p class="muted">Confirm MySQL is running and that <code>config/config.php</code> has the correct username and password (XAMPP default is user <code>root</code> with a blank password).</p>
            <?php elseif ($installed): ?>
                <?php if ($refreshed): ?>
                    <div class="alert ok">Civic request types are now active. Older catalogue items were unpublished.</div>
                <?php else: ?>
                    <div class="alert ok">Installation complete. You can sign in with the demo accounts below.</div>
                <?php endif; ?>
                <ul class="plain-list">
                    <li><strong>Administrator:</strong> 00000001 / Admin@123</li>
                    <li><strong>Department officer:</strong> 00000002 / Officer@123</li>
                    <li><strong>Supervisor:</strong> 00000003 / Supervisor@123</li>
                    <li><strong>Citizen:</strong> 12345678 / Citizen@123</li>
                </ul>
                <p><a class="btn primary" href="login.php">Go to sign in</a></p>
                <form method="post">
                    <input type="hidden" name="action" value="refresh">
                    <button class="btn" type="submit">Update catalogue to civic requests</button>
                </form>
                <p class="muted">Use the update button if this database was installed with the older document services. Delete or protect <code>install.php</code> afterwards.</p>
            <?php else: ?>
                <p>This creates the <code><?= htmlspecialchars(DB_NAME) ?></code> database, civic request types, and demo users at different access levels.</p>
                <form method="post">
                    <button class="btn primary" type="submit">Run installation</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
