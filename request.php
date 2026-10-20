<?php
require_once dirname(__DIR__) . '/includes/functions.php';
$user = require_staff();

// Load department name for officers
if ($user['role'] === 'officer' && $user['department_id']) {
    $deptStmt = db()->prepare('SELECT name FROM departments WHERE id = ?');
    $deptStmt->execute([$user['department_id']]);
    $dept = $deptStmt->fetch();
    $user['department_name'] = $dept['name'] ?? null;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT r.*, s.name AS service_name, s.form_fields, s.fee,
            u.full_name, u.national_id, u.email, u.phone,
            d.name AS department_name, d.id AS department_id
     FROM requests r
     JOIN services s ON s.id = r.service_id
     JOIN departments d ON d.id = s.department_id
     JOIN users u ON u.id = r.user_id
     WHERE r.id = ?'
);
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request || !can_manage_request($user, $request)) {
    flash('error', 'Request not found in your queue.');
    redirect('admin/requests.php');
}

$allowed = ['under_review', 'info_requested', 'approved', 'rejected', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = $_POST['status'] ?? '';
    $note = trim($_POST['note'] ?? '');
    if (!in_array($status, $allowed, true)) {
        flash('error', 'Choose a valid status.');
        redirect('admin/request.php?id=' . $id);
    }
    if ($note === '') {
        $note = 'Status changed to ' . status_meta($status)[0] . '.';
    }

    // Handle completion photo upload when status is 'completed'
    $completionPhoto = isset($request['completion_photo']) ? $request['completion_photo'] : null; // Keep existing photo if not changing

    if ($status === 'completed') {
        // Check if a new photo is being uploaded
        if (isset($_FILES['completion_photo']) && $_FILES['completion_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $completionPhoto = upload_attachment($_FILES['completion_photo'], (int) $request['id']);
            } catch (RuntimeException $e) {
                flash('error', 'Photo upload failed: ' . $e->getMessage());
                redirect('admin/request.php?id=' . $id);
            }
        } elseif (!$completionPhoto) {
            // No photo exists and no new photo uploaded - require one
            flash('error', 'A completion photo is required when marking a request as completed.');
            redirect('admin/request.php?id=' . $id);
        }
    } else {
        // If not completing, and a photo was uploaded, still save it
        if (isset($_FILES['completion_photo']) && $_FILES['completion_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $completionPhoto = upload_attachment($_FILES['completion_photo'], (int) $request['id']);
            } catch (RuntimeException $e) {
                flash('error', 'Photo upload failed: ' . $e->getMessage());
                redirect('admin/request.php?id=' . $id);
            }
        }
    }

    // Update request with note and optional completion photo
    db()->prepare('UPDATE requests SET staff_note = ?, completion_photo = ? WHERE id = ?')
        ->execute([$note, $completionPhoto, $id]);

    $sent = set_request_status($request, $status, $note, (int) $user['id']);
    flash('success', 'Request updated. Citizen notified: ' . delivery_summary($sent));
    redirect('admin/request.php?id=' . $id);
}

$fields = service_fields(['form_fields' => $request['form_fields']]);
$payload = request_payload($request);
$timeline = db()->prepare(
    'SELECT t.*, usr.full_name FROM request_timeline t LEFT JOIN users usr ON usr.id = t.actor_id WHERE t.request_id = ? ORDER BY t.created_at'
);
$timeline->execute([$id]);
$events = $timeline->fetchAll();
[$label, $tone] = status_meta($request['status']);

$title = page_title('Process request');
$nav = 'dash';
$adminNav = 'requests';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="split">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="grow">
        <div class="toolbar">
            <div>
                <p class="kicker"><?= e($request['department_name']) ?></p>
                <h1><?= e($request['tracking_code']) ?></h1>
                <p><?= e($request['service_name']) ?> · <?= e(money((float) $request['fee'])) ?></p>
                <?php if ($user['role'] === 'officer' && isset($user['department_name']) && $user['department_name']): ?>
                    <small class="muted">Your department: <?= e($user['department_name']) ?></small>
                <?php endif; ?>
            </div>
            <div>
                <span class="pill <?= e($tone) ?>"><?= e($label) ?></span>
                <button class="btn btn-pdf small" data-pdf-filename="<?= e($request['tracking_code']) ?>" style="margin-left: 0.5rem;">Download PDF</button>
            </div>
        </div>
        <div class="grid cards-2">
            <section class="card">
                <h2>Citizen</h2>
                <p><strong><?= e($request['full_name']) ?></strong><br>
                    ID <?= e($request['national_id']) ?><br>
                    <?= e($request['email']) ?><br>
                    <?= e($request['phone']) ?></p>
                <h3>Form answers</h3>
                <?php foreach ($fields as $field): ?>
                    <p><strong><?= e($field['label']) ?></strong><br><?= e($payload[$field['name']] ?? '—') ?></p>
                <?php endforeach; ?>
                <?php if ($request['attachment']): ?>
                    <p><a href="<?= e(url('uploads/' . $request['attachment'])) ?>" target="_blank">View attachment</a></p>
                <?php endif; ?>
                <?php if (isset($request['completion_photo']) && $request['completion_photo']): ?>
                    <h3>Completion Photo</h3>
                    <p><a href="<?= e(url('uploads/' . $request['completion_photo'])) ?>" target="_blank">View completion photo</a></p>
                <?php endif; ?>
            </section>
            <section class="card">
                <h2>Decision</h2>
                <?php if ($request['status'] === 'awaiting_payment'): ?>
                    <div class="alert warn">Payment is still outstanding. You can still add a note after the citizen pays.</div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                    <label for="status">New status</label>
                    <select id="status" name="status" required onchange="toggleCompletionPhotoField()">
                        <?php foreach ($allowed as $st): ?>
                            <option value="<?= e($st) ?>" <?= $request['status'] === $st ? 'selected' : '' ?>><?= e(status_meta($st)[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="note">Note to citizen</label>
                    <textarea id="note" name="note" required placeholder="Explain the decision or the next step."></textarea>
                    <div id="completion-photo-field" style="display: none;">
                        <label for="completion_photo">Completion Photo<?php if (!isset($request['completion_photo']) || !$request['completion_photo']): ?> (required when marking as completed)<?php endif; ?></label>
                        <input type="file" id="completion_photo" name="completion_photo" accept="image/*">
                        <p class="help">Upload a photo showing proof of completion (JPG, PNG, or PDF, max 4MB)<?php if (isset($request['completion_photo']) && $request['completion_photo']): ?> - Leave blank to keep existing photo<?php endif; ?></p>
                    </div>
                    <p><button class="btn primary" type="submit">Update request</button></p>
                </form>
                <script>
                function toggleCompletionPhotoField() {
                    const statusSelect = document.getElementById('status');
                    const photoField = document.getElementById('completion-photo-field');
                    const photoInput = document.getElementById('completion_photo');
                    const hasExistingPhoto = <?php echo (isset($request['completion_photo']) && $request['completion_photo']) ? 'true' : 'false'; ?>;

                    if (statusSelect.value === 'completed') {
                        photoField.style.display = 'block';
                        // Only require photo if there's no existing photo
                        photoInput.required = !hasExistingPhoto;
                    } else {
                        photoField.style.display = 'none';
                        photoInput.required = false;
                        photoInput.value = '';
                    }
                }

                // Initialize on page load
                document.addEventListener('DOMContentLoaded', toggleCompletionPhotoField);
                </script>
                <h3>History</h3>
                <ol class="timeline">
                    <?php foreach ($events as $event): ?>
                        <li>
                            <strong><?= e(status_meta($event['status'])[0]) ?></strong>
                            <div><?= e($event['note']) ?></div>
                            <small class="muted"><?= e(date('d M Y H:i', strtotime($event['created_at']))) ?><?= $event['full_name'] ? ' · ' . e($event['full_name']) : '' ?></small>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
