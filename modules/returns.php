<?php
$selectedRentalId = query_id('rental_id');
$query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$like = '%' . $query . '%';
$openQuery = "SELECT r.rental_id,r.expected_return_date,r.rental_status,c.first_name,c.last_name,v.brand,v.model,v.plate_no,v.mileage FROM tbl_rental r JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id WHERE r.rental_status IN ('Active','Late')";
$openStatement = $pdo->prepare($openQuery . ($query !== '' ? ' AND (CAST(r.rental_id AS CHAR) LIKE ? OR CONCAT(c.first_name," ",c.last_name) LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.plate_no LIKE ?)' : '') . ' ORDER BY r.expected_return_date');
$openStatement->execute($query !== '' ? [$like, $like, $like, $like, $like] : []);
$openRentals = $openStatement->fetchAll();
$returnsQuery = 'SELECT t.*,r.rental_id,c.first_name,c.last_name,v.brand,v.model,v.plate_no,u.username FROM tbl_return t JOIN tbl_rental r ON r.rental_id=t.rental_id JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id JOIN tbl_user u ON u.user_id=t.processed_by';
$returnsStatement = $pdo->prepare($returnsQuery . ($query !== '' ? ' WHERE (CAST(r.rental_id AS CHAR) LIKE ? OR CONCAT(c.first_name," ",c.last_name) LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.plate_no LIKE ?)' : '') . ' ORDER BY t.return_date DESC');
$returnsStatement->execute($query !== '' ? [$like, $like, $like, $like, $like] : []);
$recentReturns = $returnsStatement->fetchAll();
$selectedRental = null;
foreach ($openRentals as $candidate) {
    if ((int) $candidate['rental_id'] === $selectedRentalId) {
        $selectedRental = $candidate;
        break;
    }
}
$returnToRentalId = query_id('return_to_rental');
$returnToRentalId = $selectedRental && $returnToRentalId === (int) $selectedRental['rental_id']
    ? $returnToRentalId
    : 0;
$returnRentalPage = filter_var($_GET['rental_invoice_page'] ?? 1, FILTER_VALIDATE_INT);
$returnRentalPage = $returnRentalPage === false ? 1 : max(1, $returnRentalPage);
$openRentalPagination = paginate_records($openRentals, 'open_return_page');
$openRentals = $openRentalPagination['items'];
$returnPagination = paginate_records($recentReturns, 'return_history_page');
$recentReturns = $returnPagination['items'];
?>
<div class="page-heading"><div><p class="eyebrow">VEHICLE CHECK-IN</p><h1>Returns</h1><p class="heading-copy">Record mileage, vehicle condition, and any damage charges.</p></div></div>
<?php if ($selectedRental): ?><section class="panel form-panel"><div class="panel-heading"><div><h2>Process rental #RNT<?= (int) $selectedRental['rental_id'] ?></h2><p><?= e($selectedRental['brand'] . ' ' . $selectedRental['model'] . ' · ' . $selectedRental['plate_no']) ?> · <?= e($selectedRental['first_name'] . ' ' . $selectedRental['last_name']) ?></p></div><?= render_status($selectedRental['rental_status']) ?></div>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="return_save"><input type="hidden" name="rental_id" value="<?= (int) $selectedRental['rental_id'] ?>">
    <label>Current mileage out (km)<input value="<?= (int) $selectedRental['mileage'] ?>" disabled></label><label>Return mileage in (km) <span class="required">*</span><input type="number" name="mileage_in" min="<?= (int) $selectedRental['mileage'] ?>" max="2147483647" value="<?= (int) $selectedRental['mileage'] ?>" required></label>
    <label>Vehicle condition<select name="vehicle_condition"><?php foreach (['Excellent','Good','Fair','Damaged'] as $condition): ?><option><?= e($condition) ?></option><?php endforeach; ?></select></label>
    <label>Damage fee (₱)<input type="number" name="damage_fee" min="0" max="99999999" step="1" value="0" required></label>
    <label class="span-two">Remarks<input name="remarks" maxlength="255" placeholder="Optional notes about the return"></label>
    <div class="form-actions span-two"><a class="button button-quiet" href="<?= $returnToRentalId > 0 ? 'index.php?' . e(http_build_query(['page' => 'rentals', 'id' => $returnToRentalId, 'rental_invoice_page' => $returnRentalPage])) : 'index.php?page=returns' ?>">← <?= $returnToRentalId > 0 ? 'Back to rental details' : 'Cancel' ?></a><button class="button button-primary" type="submit">Complete vehicle return</button></div>
</form></section><?php elseif ($selectedRentalId): ?><div class="alert alert-error">This rental is not open for return.</div><?php endif; ?>
<section class="panel"><div class="panel-heading"><div><h2>Return Tracking <span class="count-pill"><?= $openRentalPagination['total'] ?></span></h2><p>Search open rentals and return history by rental number, customer, plate, or vehicle.</p></div><form method="get" class="search-form"><input type="hidden" name="page" value="returns"><?php if ($selectedRentalId): ?><input type="hidden" name="rental_id" value="<?= $selectedRentalId ?>"><?php endif; ?><input name="q" value="<?= e($query) ?>" placeholder="Rental #, customer, plate, vehicle" aria-label="Search returns"><button class="button button-secondary button-small">Search</button><?php if ($query): ?><a class="button button-quiet button-small" href="index.php?page=returns<?= $selectedRentalId ? '&rental_id=' . $selectedRentalId : '' ?>">Clear</a><?php endif; ?></form></div><div class="table-wrap"><table><thead><tr><th>RENTAL</th><th>CUSTOMER</th><th>VEHICLE</th><th>DUE BACK</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody>
<?php if (!$openRentals): ?><tr><td colspan="6"><div class="empty-state"><strong><?= $query ? 'No matching open rentals' : 'All vehicles are accounted for' ?></strong><span><?= $query ? 'Try another rental number, customer, plate, or vehicle.' : 'Active and overdue rentals will appear here.' ?></span></div></td></tr><?php endif; ?>
<?php foreach ($openRentals as $rental): ?><tr><td><a class="table-primary" href="index.php?page=rentals&id=<?= (int) $rental['rental_id'] ?>">#RNT<?= (int) $rental['rental_id'] ?></a></td><td><?= e($rental['first_name'] . ' ' . $rental['last_name']) ?></td><td><?= e($rental['brand'] . ' ' . $rental['model'] . ' · ' . $rental['plate_no']) ?></td><td><?= e(date('M j, Y · g:i A', strtotime($rental['expected_return_date']))) ?></td><td><?= render_status($rental['rental_status']) ?></td><td><a class="button button-primary button-small" href="index.php?page=returns&rental_id=<?= (int) $rental['rental_id'] ?>">Process return</a></td></tr><?php endforeach; ?>
</tbody></table></div><?= render_pagination($openRentalPagination, 'open_return_page') ?></section>
<section class="panel"><div class="panel-heading"><div><h2>Recent Returns</h2><p>Return history and vehicle condition.</p></div><span class="count-pill"><?= $returnPagination['total'] ?></span></div><div class="table-wrap"><table><thead><tr><th>RETURNED</th><th>RENTAL</th><th>VEHICLE</th><th>MILEAGE</th><th>CONDITION</th><th>DAMAGE FEE</th></tr></thead><tbody>
<?php if (!$recentReturns): ?><tr><td colspan="6"><div class="empty-state"><strong><?= $query ? 'No matching return records' : 'No completed returns yet' ?></strong><span><?= $query ? 'Try another rental number, customer, plate, or vehicle.' : 'Completed check-ins will be listed here.' ?></span></div></td></tr><?php endif; ?>
<?php foreach ($recentReturns as $record): ?><tr><td><?= e(date('M j, Y · g:i A', strtotime($record['return_date']))) ?></td><td><a class="table-primary" href="index.php?page=rentals&id=<?= (int) $record['rental_id'] ?>">#RNT<?= (int) $record['rental_id'] ?></a></td><td><?= e($record['brand'] . ' ' . $record['model'] . ' · ' . $record['plate_no']) ?></td><td><?= number_format((int) $record['mileage_out']) ?> → <?= number_format((int) $record['mileage_in']) ?> km</td><td><?= e($record['vehicle_condition']) ?></td><td><?= e(money($record['damage_fee'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?= render_pagination($returnPagination, 'return_history_page') ?></section>
