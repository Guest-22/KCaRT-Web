<?php
$rentalId = query_id('id');
if ($rentalId > 0) {
    $statement = $pdo->prepare('SELECT r.*, c.first_name,c.middle_name,c.last_name,c.contact_no,c.email,c.customer_status,v.brand,v.model,v.plate_no,v.daily_rate,u.username FROM tbl_rental r JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id JOIN tbl_user u ON u.user_id=r.processed_by WHERE r.rental_id=?');
    $statement->execute([$rentalId]);
    $rental = $statement->fetch();
    if (!$rental) {
        echo '<div class="alert alert-error">Rental record not found.</div>';
    } else {
        $statement = $pdo->prepare('SELECT i.*, COALESCE(SUM(p.pay_amount),0) AS paid FROM tbl_invoice i LEFT JOIN tbl_payment p ON p.invoice_id=i.invoice_id WHERE i.rental_id=? GROUP BY i.invoice_id ORDER BY i.created_at');
        $statement->execute([$rentalId]);
        $invoices = $statement->fetchAll();
        $reservationPaid = false;
        $reservationFeePaidAmount = 0.0;
        foreach ($invoices as $invoice) {
            if ($invoice['description'] === 'Reservation fee') {
                $reservationFeePaidAmount += (float) $invoice['paid'];
                $reservationPaid = $invoice['invoice_status'] === 'Paid';
            }
        }
        $remainingRentalBalance = max(0, round((float) $rental['total_amount'] - $reservationFeePaidAmount, 2));
        $invoicePagination = paginate_records($invoices, 'rental_invoice_page');
        $invoices = $invoicePagination['items'];
        ?>
        <div class="page-heading"><div><p class="eyebrow">RENTAL RECORD</p><h1>Rental #RNT<?= (int) $rental['rental_id'] ?></h1><p class="heading-copy">Reservation, vehicle, and billing details.</p></div><div class="heading-actions"><a class="button button-secondary" href="index.php?page=rentals">← All rentals</a><?php if (in_array($rental['rental_status'], ['Active','Late'], true)): ?><a class="button button-primary" href="index.php?page=returns&rental_id=<?= (int) $rental['rental_id'] ?>">Process return</a><?php endif; ?></div></div>
        <div class="detail-grid"><section class="panel detail-panel"><div class="panel-heading"><div><h2>Rental overview</h2><p>Created <?= e(date('M j, Y · g:i A', strtotime($rental['created_at']))) ?></p></div><?= render_status($rental['rental_status']) ?></div><dl class="detail-list"><div><dt>Customer</dt><dd><?= e(full_name($rental)) ?></dd></div><div><dt>Contact</dt><dd><?= e($rental['contact_no']) ?></dd></div><div><dt>Vehicle</dt><dd><?= e($rental['brand'] . ' ' . $rental['model']) ?> <span class="field-note"><?= e($rental['plate_no']) ?></span></dd></div><div><dt>Pickup</dt><dd><?= e(date('M j, Y · g:i A', strtotime($rental['start_date']))) ?></dd></div><div><dt>Expected return</dt><dd><?= e(date('M j, Y · g:i A', strtotime($rental['expected_return_date']))) ?></dd></div><div><dt>Rental total</dt><dd><?= e(money($rental['total_amount'])) ?></dd></div><div><dt>Reservation fee paid</dt><dd><?= e(money($reservationFeePaidAmount)) ?></dd></div><div><dt>Remaining rental balance</dt><dd><?= e(money($remainingRentalBalance)) ?></dd></div><div><dt>Processed by</dt><dd><?= e($rental['username']) ?></dd></div></dl>
        <?php if ($rental['rental_status'] === 'Pending'): ?><div class="workflow-callout"><strong>Reservation pickup</strong><span>Reservation fees must be paid before pickup. The customer has a one-hour grace period after the scheduled time.</span><?php if ($rental['customer_status'] !== 'Active'): ?><span class="field-note">Reactivate this customer before confirming vehicle pickup.</span><?php elseif ($reservationPaid): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rental_pickup"><input type="hidden" name="rental_id" value="<?= (int) $rental['rental_id'] ?>"><button class="button button-primary button-small">Confirm vehicle pickup</button></form><?php else: ?><a class="text-link" href="index.php?page=billing">View reservation invoice and payments →</a><?php endif; ?></div><?php endif; ?>
        </section><section class="panel"><div class="panel-heading"><div><h2>Invoices</h2><p>Charges associated with this rental.</p></div></div><div class="table-wrap"><table><thead><tr><th>DESCRIPTION</th><th>AMOUNT</th><th>PAID</th><th>STATUS</th><th></th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><?= e($invoice['description']) ?></td><td><?= e(money($invoice['invoice_amount'])) ?></td><td><?= e(money($invoice['paid'])) ?></td><td><?= render_status($invoice['invoice_status']) ?></td><td><a class="text-link" href="index.php?page=billing&invoice_id=<?= (int) $invoice['invoice_id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div><?= render_pagination($invoicePagination, 'rental_invoice_page') ?></section></div>
        <?php
    }
} else {
    $newRental = isset($_GET['new']);
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    $query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $statuses = ['', 'Pending', 'Active', 'Late', 'Completed', 'Dismissed'];
    if (!in_array($status, $statuses, true)) {
        $status = '';
    }
    $conditions = [];
    $parameters = [];
    if ($status !== '') {
        $conditions[] = 'r.rental_status=?';
        $parameters[] = $status;
    }
    if ($query !== '') {
        $conditions[] = '(CAST(r.rental_id AS CHAR) LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name," ",c.last_name) LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.plate_no LIKE ?)';
        $like = '%' . $query . '%';
        array_push($parameters, $like, $like, $like, $like, $like, $like, $like);
    }
    $statement = $pdo->prepare('SELECT r.*,c.first_name,c.last_name,v.brand,v.model,v.car_type,v.plate_no FROM tbl_rental r JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY r.created_at DESC');
    $statement->execute($parameters);
    $rentals = $statement->fetchAll();
    $rentalPagination = paginate_records($rentals, 'rental_page');
    $rentals = $rentalPagination['items'];
    $customers = $pdo->query("SELECT customer_id,first_name,last_name,contact_no FROM tbl_customer WHERE customer_status='Active' ORDER BY last_name,first_name")->fetchAll();
    $cars = $pdo->query("SELECT car_id,plate_no,brand,model,daily_rate FROM tbl_car WHERE car_status='Available' ORDER BY brand,model")->fetchAll();
    ?>
    <div class="page-heading"><div><p class="eyebrow">RESERVATIONS & ACTIVE RENTALS</p><h1>Rentals</h1><p class="heading-copy">Reserve vehicles, confirm pickups, and follow rental status.</p></div><?php if (!$newRental): ?><a class="button button-primary" href="index.php?page=rentals&new=1">＋ New reservation</a><?php endif; ?></div>
    <?php if ($newRental): ?>
    <section class="panel form-panel"><div class="panel-heading"><div><h2>Create a reservation</h2><p>The reservation invoice will be created automatically.</p></div></div>
    <?php if (!$customers || !$cars): ?><div class="alert alert-error">You need at least one active customer and one available vehicle to create a reservation.</div><?php else: ?>
    <form method="post" class="form-grid" data-rental-form><?= csrf_field() ?><input type="hidden" name="action" value="rental_save">
        <div class="form-field">
            <label for="reservation-customer-search">Customer <span class="required">*</span></label>
            <span class="search-picker" data-search-picker>
                <input id="reservation-customer-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="reservation-customer-options" aria-required="true" autocomplete="off" placeholder="Search active customers" required data-picker-input>
                <input type="hidden" name="customer_id" data-picker-value>
                <span class="search-picker-options" id="reservation-customer-options" role="listbox" hidden>
                    <?php foreach ($customers as $customer): $customerLabel = $customer['last_name'] . ', ' . $customer['first_name'] . ' · ' . $customer['contact_no'] . ' · #' . $customer['customer_id']; ?>
                    <button type="button" class="search-picker-option" id="reservation-customer-options-<?= (int) $customer['customer_id'] ?>" role="option" aria-selected="false" data-value="<?= (int) $customer['customer_id'] ?>" data-label="<?= e($customerLabel) ?>" data-search="<?= e($customerLabel) ?>"><?= e($customerLabel) ?></button>
                    <?php endforeach; ?>
                    <span class="search-picker-empty" role="status" hidden>No active customer matches your search.</span>
                </span>
            </span>
            <a class="field-link" href="index.php?page=customers&new=1">＋ Add customer</a>
        </div>
        <div class="form-field">
            <label for="reservation-vehicle-search">Available vehicle <span class="required">*</span></label>
            <span class="search-picker" data-search-picker>
                <input id="reservation-vehicle-search" type="search" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="reservation-vehicle-options" aria-required="true" autocomplete="off" placeholder="Search available vehicles" required data-picker-input data-car-rate>
                <input type="hidden" name="car_id" data-picker-value>
                <span class="search-picker-options" id="reservation-vehicle-options" role="listbox" hidden>
                    <?php foreach ($cars as $car): $carLabel = $car['brand'] . ' ' . $car['model'] . ' · ' . $car['plate_no'] . ' · ' . money($car['daily_rate']) . '/day'; ?>
                    <button type="button" class="search-picker-option" id="reservation-vehicle-options-<?= (int) $car['car_id'] ?>" role="option" aria-selected="false" data-value="<?= (int) $car['car_id'] ?>" data-label="<?= e($carLabel) ?>" data-search="<?= e($carLabel) ?>" data-rate="<?= e($car['daily_rate']) ?>"><?= e($carLabel) ?></button>
                    <?php endforeach; ?>
                    <span class="search-picker-empty" role="status" hidden>No available vehicle matches your search.</span>
                </span>
            </span>
        </div>
        <label>Pickup date & time <span class="required">*</span><input type="datetime-local" name="start_date" required min="<?= e(date('Y-m-d\TH:i')) ?>"></label>
        <label>Expected return <span class="required">*</span><input type="datetime-local" name="expected_return_date" required></label>
        <div class="estimate-card span-two"><span>Estimated rental total</span><strong data-rental-estimate>Choose a vehicle and rental dates</strong><small>Charged by 24-hour rental day, with a one-day minimum.</small></div>
        <div class="form-actions span-two"><a class="button button-quiet" href="index.php?page=rentals">Cancel</a><button class="button button-primary" type="submit">Create reservation</button></div>
    </form><?php endif; ?></section>
    <?php else: ?>
    <section class="panel"><div class="panel-heading"><div><h2>Rental Activity <span class="count-pill"><?= $rentalPagination['total'] ?></span></h2><p>Search by rental number, customer, plate, or vehicle; filter by status.</p></div><form method="get" class="search-form"><input type="hidden" name="page" value="rentals"><input name="q" value="<?= e($query) ?>" placeholder="Rental #, customer, plate, vehicle" aria-label="Search rentals"><select name="status" aria-label="Filter by rental status"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option ?: 'All statuses') ?></option><?php endforeach; ?></select><button class="button button-secondary button-small">Search/Filter</button><?php if ($query || $status): ?><a class="button button-quiet button-small" href="index.php?page=rentals">Clear</a><?php endif; ?></form></div>
    <div class="table-wrap"><table><thead><tr><th>REFERENCE</th><th>CUSTOMER</th><th>VEHICLE</th><th>PICKUP</th><th>EXPECTED RETURN</th><th>STATUS</th><th>TOTAL</th><th>ACTIONS</th></tr></thead><tbody>
    <?php if (!$rentals): ?><tr><td colspan="8"><div class="empty-state"><strong><?= $query ? 'No matching rentals' : 'No rentals to show' ?></strong><span><?= $query ? 'Try a different rental number, customer, plate, vehicle, or status.' : 'Create a reservation to get started.' ?></span><?php if (!$query): ?><a class="button button-secondary button-small" href="index.php?page=rentals&new=1">New reservation</a><?php endif; ?></div></td></tr><?php endif; ?>
    <?php foreach ($rentals as $rental): ?><tr><td><a class="table-primary" href="index.php?page=rentals&id=<?= (int) $rental['rental_id'] ?>">#RNT<?= (int) $rental['rental_id'] ?></a></td><td><?= e($rental['first_name'] . ' ' . $rental['last_name']) ?></td><td><span class="table-primary"><?= e($rental['brand'] . ' ' . $rental['model']) ?></span><span class="table-secondary"><?= e($rental['plate_no']) ?></span></td><td><?= e(date('M j, Y · g:i A', strtotime($rental['start_date']))) ?></td><td><?= e(date('M j, Y · g:i A', strtotime($rental['expected_return_date']))) ?></td><td><?= render_status($rental['rental_status']) ?></td><td><?= e(money($rental['total_amount'])) ?></td><td><a class="button button-quiet button-small" href="index.php?page=rentals&id=<?= (int) $rental['rental_id'] ?>">Details</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?= render_pagination($rentalPagination, 'rental_page') ?></section>
    <?php endif; ?>
    <?php
}
