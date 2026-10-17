<?php
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM tbl_rental WHERE rental_status IN ('Active','Late')) AS active_rentals,
    (SELECT COUNT(*) FROM tbl_car WHERE car_status='Available') AS available_cars,
    (SELECT COUNT(*) FROM tbl_customer WHERE customer_status='Active') AS active_customers,
    (SELECT COALESCE(SUM(i.invoice_amount - COALESCE(p.paid,0)),0) FROM tbl_invoice i LEFT JOIN (SELECT invoice_id,SUM(pay_amount) AS paid FROM tbl_payment GROUP BY invoice_id) p ON p.invoice_id=i.invoice_id WHERE i.invoice_status <> 'Paid') AS outstanding,
    (SELECT COALESCE(SUM(pay_amount),0) FROM tbl_payment WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS monthly_revenue")->fetch();
$paymentRows = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, SUM(pay_amount) AS amount FROM tbl_payment WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month")->fetchAll();
$paymentsByMonth = array_column($paymentRows, 'amount', 'month');
$monthlyCollections = [];
for ($offset = 5; $offset >= 0; $offset--) {
    $month = new DateTimeImmutable('first day of this month');
    $month = $month->modify("-{$offset} months");
    $key = $month->format('Y-m');
    $monthlyCollections[] = ['label' => $month->format('M'), 'amount' => (float) ($paymentsByMonth[$key] ?? 0)];
}
$peakCollections = max(1.0, ...array_column($monthlyCollections, 'amount'));
$rentalStatuses = $pdo->query("SELECT rental_status,COUNT(*) AS total FROM tbl_rental GROUP BY rental_status ORDER BY FIELD(rental_status,'Pending','Active','Late','Completed','Dismissed')")->fetchAll();
$rentalTotal = max(1, array_sum(array_column($rentalStatuses, 'total')));
$rentals = $pdo->query("SELECT r.rental_id,r.start_date,r.expected_return_date,r.rental_status,r.total_amount,c.first_name,c.last_name,v.brand,v.model,v.plate_no FROM tbl_rental r JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id ORDER BY r.created_at DESC LIMIT 6")->fetchAll();
?>
<div class="page-heading">
    <div><p class="eyebrow">YOUR OPERATIONS, AT A GLANCE · BATAAN</p><h1>Good <?= e((int) date('G') < 12 ? 'morning' : ((int) date('G') < 18 ? 'afternoon' : 'evening')) ?>, <?= e(current_user()['first_name']) ?></h1><p class="heading-copy">Here’s what’s happening across your Bataan rental business today.</p></div>
    <a class="button button-primary" href="index.php?page=rentals&new=1"><span>＋</span> New reservation</a>
</div>
<div class="metric-grid">
    <article class="metric-card"><div class="metric-top"><span class="metric-icon metric-blue">↗</span><span class="metric-label">Active rentals</span></div><strong><?= e($stats['active_rentals']) ?></strong><span class="metric-foot">Currently on the road</span></article>
    <article class="metric-card"><div class="metric-top"><span class="metric-icon metric-green">▰</span><span class="metric-label">Available vehicles</span></div><strong><?= e($stats['available_cars']) ?></strong><span class="metric-foot">Ready to reserve</span></article>
    <article class="metric-card"><div class="metric-top"><span class="metric-icon metric-violet">◎</span><span class="metric-label">Active customers</span></div><strong><?= e($stats['active_customers']) ?></strong><span class="metric-foot">In your customer list</span></article>
    <article class="metric-card"><div class="metric-top"><span class="metric-icon metric-amber">₱</span><span class="metric-label">Outstanding invoices</span></div><strong><?= e(money($stats['outstanding'])) ?></strong><span class="metric-foot">Payments received this month: <?= e(money($stats['monthly_revenue'])) ?></span></article>
</div>
<div class="dashboard-charts">
    <section class="panel chart-panel"><div class="panel-heading"><div><h2>Monthly collections</h2><p>Payments received · last six months.</p></div><strong class="chart-summary"><?= e(money($stats['monthly_revenue'])) ?><small>this month</small></strong></div>
        <div class="column-chart" role="img" aria-label="Monthly payment collections for the last six months">
            <?php foreach ($monthlyCollections as $month): $height = $month['amount'] > 0 ? max(5, (int) round($month['amount'] / $peakCollections * 100)) : 0; ?>
                <div class="column-chart-item" aria-label="<?= e($month['label'] . ': ' . money($month['amount'])) ?>"><div class="column-chart-value"><?= e($month['amount'] > 0 ? money($month['amount']) : '—') ?></div><div class="column-chart-track"><i style="height:<?= $height ?>%"></i></div><span><?= e($month['label']) ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="panel chart-panel"><div class="panel-heading"><div><h2>Rental pipeline</h2><p><?= array_sum(array_column($rentalStatuses, 'total')) ?> rentals by current status.</p></div></div>
        <div class="status-chart" role="img" aria-label="Rental counts grouped by status">
            <?php if (!$rentalStatuses): ?><div class="empty-state"><strong>No rental activity yet</strong><span>Reservations will appear here.</span></div><?php endif; ?>
            <?php foreach ($rentalStatuses as $status): $share = (int) round(((int) $status['total'] / $rentalTotal) * 100); ?>
                <div class="status-chart-row"><div><span><?= render_status($status['rental_status']) ?></span><strong><?= (int) $status['total'] ?></strong></div><div class="status-chart-track"><i class="status-chart-<?= e(strtolower($status['rental_status'])) ?>" style="width:<?= $share ?>%"></i></div></div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<section class="panel">
    <div class="panel-heading"><div><h2>Recent rentals</h2><p>Your latest reservations and rental activity.</p></div><a class="text-link" href="index.php?page=rentals">All rentals <span>→</span></a></div>
    <div class="table-wrap"><table><thead><tr><th>RENTAL</th><th>CUSTOMER</th><th>VEHICLE</th><th>PICKUP</th><th>RETURN</th><th>STATUS</th><th>AMOUNT</th></tr></thead><tbody>
    <?php if (!$rentals): ?><tr><td colspan="7"><div class="empty-state"><strong>No rentals yet</strong><span>Create a reservation to see activity here.</span><a class="button button-secondary button-small" href="index.php?page=rentals&new=1">Create reservation</a></div></td></tr><?php endif; ?>
    <?php foreach ($rentals as $rental): ?>
        <tr><td><a class="table-primary" href="index.php?page=rentals&id=<?= (int) $rental['rental_id'] ?>">#R<?= (int) $rental['rental_id'] ?></a></td><td><?= e($rental['first_name'] . ' ' . $rental['last_name']) ?></td><td><span class="table-primary"><?= e($rental['brand'] . ' ' . $rental['model']) ?></span><span class="table-secondary"><?= e($rental['plate_no']) ?></span></td><td><?= e(date('M j, Y', strtotime($rental['start_date']))) ?></td><td><?= e(date('M j, Y', strtotime($rental['expected_return_date']))) ?></td><td><?= render_status($rental['rental_status']) ?></td><td><?= e(money($rental['total_amount'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<div class="dashboard-lower">
    <section class="panel quick-panel"><div class="panel-heading"><div><h2>Quick actions</h2><p>Get common tasks done faster.</p></div></div><div class="quick-actions"><a href="index.php?page=customers&new=1"><span class="quick-icon quick-blue">＋</span><span><strong>Add a customer</strong><small>Register a new renter</small></span><b>→</b></a><a href="index.php?page=vehicles&new=1"><span class="quick-icon quick-green">▰</span><span><strong>Add a vehicle</strong><small>Update your fleet</small></span><b>→</b></a><a href="index.php?page=returns"><span class="quick-icon quick-amber">↙</span><span><strong>Process a return</strong><small>Close out a rental</small></span><b>→</b></a></div></section>
    <section class="panel insight-panel"><div class="panel-heading"><div><h2>Keep things moving</h2><p>A smooth rental starts with a clear next step.</p></div></div><div class="insight-body"><div class="insight-art">↗</div><p>Check upcoming pickups and outstanding invoices to keep your vehicles and customers on track.</p><a class="text-link" href="index.php?page=reports">Explore reports <span>→</span></a></div></section>
</div>
