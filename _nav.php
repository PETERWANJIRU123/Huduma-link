<?php
$adminNav = $adminNav ?? '';

// Load department name for officers if not already loaded
if ($user['role'] === 'officer' && $user['department_id'] && !isset($user['department_name'])) {
    $deptStmt = db()->prepare('SELECT name FROM departments WHERE id = ?');
    $deptStmt->execute([$user['department_id']]);
    $dept = $deptStmt->fetch();
    $user['department_name'] = $dept['name'] ?? null;
}
?>
<aside class="card side-nav">
    <?php if ($user['role'] === 'officer' && isset($user['department_name']) && $user['department_name']): ?>
        <div style="padding: 0.55rem 0.75rem; color: var(--muted); font-size: 0.85rem; border-bottom: 1px solid var(--line);">
            <strong><?= e($user['department_name']) ?></strong><br>
            <small>Department</small>
        </div>
    <?php endif; ?>
    <a class="<?= $adminNav === 'home' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>">Overview</a>
    <a class="<?= $adminNav === 'requests' ? 'active' : '' ?>" href="<?= e(url('admin/requests.php')) ?>">Requests</a>
    <?php if ($user['role'] === 'admin'): ?>
        <a class="<?= $adminNav === 'services' ? 'active' : '' ?>" href="<?= e(url('admin/services.php')) ?>">Services</a>
        <a class="<?= $adminNav === 'users' ? 'active' : '' ?>" href="<?= e(url('admin/users.php')) ?>">Users &amp; access</a>
        <a class="<?= $adminNav === 'messages' ? 'active' : '' ?>" href="<?= e(url('admin/messages.php')) ?>">Email &amp; SMS</a>
    <?php endif; ?>
</aside>
