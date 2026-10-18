<?php
$counts = $pdo->query("SELECT
    (SELECT COUNT(*) FROM tbl_rental) AS rentals,
    (SELECT COUNT(*) FROM tbl_rental WHERE rental_status IN ('Pending','Active','Late')) AS open_rentals,
    (SELECT COUNT(*) FROM tbl_car) AS vehicles,
    (SELECT COUNT(*) FROM tbl_customer) AS customers,
    (SELECT COALESCE(SUM(pay_amount),0) FROM tbl_payment WHERE created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS month_payments,
    (SELECT COALESCE(SUM(i.invoice_amount-COALESCE(p.paid,0)),0) FROM tbl_invoice i LEFT JOIN (SELECT invoice_id,SUM(pay_amount) paid FROM tbl_payment GROUP BY invoice_id) p ON p.invoice_id=i.invoice_id WHERE i.invoice_status<>'Paid') AS unpaid")->fetch();
$period = chart_period();
$periodStart = $period['start']->format('Y-m-d');
$periodEnd = $period['end']->format('Y-m-d');
$monthlyQuery = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, SUM(pay_amount) AS amount FROM tbl_payment WHERE created_at >= ? AND created_at < ? GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month");
$monthlyQuery->execute([$periodStart, $periodEnd]);
$monthlyRows = $monthlyQuery->fetchAll();
$paymentsByMonth = array_column($monthlyRows, 'amount', 'month');
$monthly = [];
$month = $period['start'];
for ($index = 0; $index < 6; $index++, $month = $month->modify('+1 month')) {
    $key = $month->format('Y-m');
    $monthly[] = ['month' => $month->format('Y-m'), 'amount' => (float) ($paymentsByMonth[$key] ?? 0)];
}
$fleetQuery = $pdo->prepare('SELECT car_status,COUNT(*) AS total FROM tbl_car WHERE created_at >= ? AND created_at < ? GROUP BY car_status ORDER BY total DESC');
$fleetQuery->execute([$periodStart, $periodEnd]);
$fleetBreakdown = $fleetQuery->fetchAll();
$rentalQuery = $pdo->prepare("SELECT rental_status,COUNT(*) AS total FROM tbl_rental WHERE start_date >= ? AND start_date < ? GROUP BY rental_status ORDER BY FIELD(rental_status,'Pending','Active','Late','Completed','Dismissed')");
$rentalQuery->execute([$periodStart, $periodEnd]);
$rentalBreakdown = $rentalQuery->fetchAll();
$topCustomers = $pdo->query("SELECT c.customer_id,c.first_name,c.last_name,COUNT(r.rental_id) AS rentals,COALESCE(SUM(r.total_amount),0) AS amount FROM tbl_customer c JOIN tbl_rental r ON r.customer_id=c.customer_id GROUP BY c.customer_id ORDER BY rentals DESC,amount DESC LIMIT 5")->fetchAll();
$peak = max(1.0, ...array_column($monthly, 'amount'));
$fleetTotal = array_sum(array_column($fleetBreakdown, 'total'));
$rentalTotal = array_sum(array_column($rentalBreakdown, 'total'));
$chartColors = ['#248776', '#d7a64a', '#668eb0', '#d4775e', '#8b78ad', '#55a7a0'];
$collectionPoints = [];
foreach ($monthly as $index => $month) {
    $x = 34 + ($index * 106);
    $y = 145 - (int) round(($month['amount'] / $peak) * 112);
    $collectionPoints[] = $x . ',' . $y;
}
?>
<div class="page-heading"><div><p class="eyebrow">INSIGHTS FOR YOUR BUSINESS</p><h1>Reports</h1><p class="heading-copy">A clear view of fleet usage, customer activity, and collections.</p></div><div class="heading-actions"><a class="button button-secondary" href="index.php?page=reports&export=rentals">↓ Rental CSV</a><a class="button button-secondary" href="index.php?page=reports&export=payments">↓ Payment CSV</a></div></div>
<div class="report-metrics"><article class="report-metric"><div class="metric-top"><span class="metric-icon metric-blue" aria-hidden="true">↗</span><span class="metric-label">All Rentals</span></div><strong><?= e($counts['rentals']) ?></strong><small><?= e($counts['open_rentals']) ?> reservations and rentals currently open</small></article><article class="report-metric"><div class="metric-top"><span class="metric-icon metric-green" aria-hidden="true">▰</span><span class="metric-label">Fleet Size</span></div><strong><?= e($counts['vehicles']) ?></strong><small>Registered vehicles</small></article><article class="report-metric"><div class="metric-top"><span class="metric-icon metric-violet" aria-hidden="true">◎</span><span class="metric-label">Customers</span></div><strong><?= e($counts['customers']) ?></strong><small>Customer records</small></article><article class="report-metric"><div class="metric-top"><span class="metric-icon metric-amber" aria-hidden="true">₱</span><span class="metric-label">Collected this Month</span></div><strong><?= e(money($counts['month_payments'])) ?></strong><small><?= e(money($counts['unpaid'])) ?> outstanding across invoices</small></article></div>
<div class="report-grid">
<section class="panel chart-panel"><div class="panel-heading"><div><h2>Monthly Earnings</h2><p>Monthly payment totals · line chart.</p></div></div><?= chart_period_controls('reports', $period) ?><div class="line-chart-wrap"><svg class="line-chart" viewBox="0 0 600 190" role="img" aria-label="Line chart of monthly collections from <?= e($period['start']->format('M Y')) ?> to <?= e($period['end']->modify('-1 month')->format('M Y')) ?>">
    <line x1="30" y1="145" x2="570" y2="145" class="chart-axis"></line><line x1="30" y1="89" x2="570" y2="89" class="chart-gridline"></line><line x1="30" y1="33" x2="570" y2="33" class="chart-gridline"></line>
    <polyline points="<?= e(implode(' ', $collectionPoints)) ?>" class="collection-line"></polyline>
    <?php foreach ($monthly as $index => $month): $x = 34 + ($index * 106); $y = 145 - (int) round(($month['amount'] / $peak) * 112); ?>
    <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4.5" class="collection-point"><title><?= e(date('F Y', strtotime($month['month'] . '-01')) . ': ' . money($month['amount'])) ?></title></circle><text x="<?= $x ?>" y="174" text-anchor="middle" class="chart-label"><?= e(date('M', strtotime($month['month'] . '-01'))) ?></text>
    <?php endforeach; ?>
    </svg><div class="line-chart-values"><?php foreach ($monthly as $month): ?><span><?= e(money($month['amount'])) ?></span><?php endforeach; ?></div></div></section>
<section class="panel chart-panel"><div class="panel-heading"><div><h2>Fleet Availability</h2><p>Vehicle status mix · pie chart.</p></div></div><?= chart_period_controls('reports', $period) ?>
    <?php if (!$fleetBreakdown): ?><div class="empty-state"><strong>No vehicles added in this period</strong><span>Choose an earlier period to view older additions.</span></div><?php else: ?>
    <div class="pie-chart-layout"><svg class="donut-chart" viewBox="0 0 120 120" role="img" aria-label="Fleet status distribution, <?= $fleetTotal ?> vehicles"><circle class="donut-track" cx="60" cy="60" r="42"></circle>
        <?php $fleetOffset = 0; $circumference = 2 * M_PI * 42; foreach ($fleetBreakdown as $index => $row): $segment = ((int) $row['total'] / $fleetTotal) * $circumference; ?>
        <circle class="donut-segment" cx="60" cy="60" r="42" stroke="<?= e($chartColors[$index % count($chartColors)]) ?>" stroke-dasharray="<?= e(number_format($segment, 3, '.', '')) ?> <?= e(number_format($circumference - $segment, 3, '.', '')) ?>" stroke-dashoffset="<?= e(number_format(-$fleetOffset, 3, '.', '')) ?>"><title><?= e($row['car_status'] . ': ' . $row['total']) ?></title></circle>
        <?php $fleetOffset += $segment; endforeach; ?><text x="60" y="57" text-anchor="middle" class="donut-total"><?= $fleetTotal ?></text><text x="60" y="70" text-anchor="middle" class="donut-caption">vehicles</text></svg>
        <ul class="chart-legend"><?php foreach ($fleetBreakdown as $index => $row): ?><li><span><i style="--legend-color:<?= e($chartColors[$index % count($chartColors)]) ?>"></i><?= e($row['car_status']) ?></span><strong><?= (int) $row['total'] ?></strong></li><?php endforeach; ?></ul></div>
    <?php endif; ?></section>
<section class="panel chart-panel"><div class="panel-heading"><div><h2>Rental Activity</h2><p>Reservations and rentals by pickup date · column chart.</p></div></div><?= chart_period_controls('reports', $period) ?>
    <?php if (!$rentalBreakdown): ?><div class="empty-state"><strong>No rental activity in this period</strong><span>Choose an earlier period to view older rentals.</span></div><?php else: ?>
    <div class="outcome-columns" role="img" aria-label="Rental counts by status"><?php foreach ($rentalBreakdown as $index => $row): $height = (int) round(((int) $row['total'] / $rentalTotal) * 100); ?>
        <div class="outcome-column"><strong><?= (int) $row['total'] ?></strong><div class="outcome-column-track"><i style="height:<?= $height ?>%;--column-color:<?= e($chartColors[$index % count($chartColors)]) ?>"></i></div><span><?= e($row['rental_status']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?></section>
</div>
<section class="panel"><div class="panel-heading"><div><h2>Top Customers</h2><p>Ranked by number of rentals.</p></div><a href="index.php?page=customers" class="text-link">Customer directory →</a></div><div class="table-wrap"><table><thead><tr><th>RANK</th><th>CUSTOMER</th><th>RENTALS</th><th>RENTAL TOTAL</th></tr></thead><tbody><?php if (!$topCustomers): ?><tr><td colspan="4"><div class="empty-state"><strong>No customer rentals yet</strong><span>Customer activity will appear as you process rentals.</span></div></td></tr><?php endif; ?><?php foreach ($topCustomers as $index => $customer): ?><tr><td><span class="rank-number"><?= $index + 1 ?></span></td><td><a class="table-primary" href="index.php?page=customers&edit=<?= (int) $customer['customer_id'] ?>"><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></a></td><td><?= (int) $customer['rentals'] ?></td><td><?= e(money($customer['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
