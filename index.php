<?php
require_once __DIR__ . '/includes/functions.php';
$title = page_title('Home');
$nav = 'home';
require __DIR__ . '/includes/header.php';

$departments = active_departments();
$services = db()->query(
    'SELECT s.*, d.name AS department_name
     FROM services s JOIN departments d ON d.id = s.department_id
     WHERE s.is_active = 1
     ORDER BY s.name
     LIMIT 6'
)->fetchAll();
?>
<section class="hero">
    <p class="kicker">County service desk</p>
    <h1>Report a problem or apply for support.</h1>
    <p>Citizens can submit requests for broken streetlights, water issues, garbage collection, and bursaries, then track progress online.</p>
    <form class="search-bar" action="<?= e(url('services.php')) ?>" method="get">
        <input type="search" name="q" placeholder="Search, e.g. streetlight, water, garbage, bursary">
        <button class="btn primary" type="submit">Search</button>
    </form>
    <div class="hero-actions">
        <a class="btn primary" href="<?= e(url('register.php')) ?>">Create citizen account</a>
        <a class="btn ghost" href="<?= e(url('track.php')) ?>">Track a request</a>
    </div>
</section>

<h2>What you can report</h2>
<div class="grid cards-3">
    <?php foreach ($departments as $dept): ?>
        <a class="card" href="<?= e(url('services.php?department=' . $dept['slug'])) ?>">
            <div class="icon-lg"><?= e($dept['icon']) ?></div>
            <h3><?= e($dept['name']) ?></h3>
            <p class="muted"><?= e($dept['summary']) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<h2>Popular services</h2>
<div class="grid cards-2">
    <?php foreach ($services as $service): ?>
        <article class="card">
            <p class="kicker"><?= e($service['department_name']) ?></p>
            <h3><?= e($service['name']) ?></h3>
            <p><?= e($service['summary']) ?></p>
            <p><?= e(service_cost_label($service)) ?></p>
            <a class="btn small" href="<?= e(url('apply.php?service=' . $service['id'])) ?>">Submit request</a>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
