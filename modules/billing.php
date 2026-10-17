<?php
$invoiceId = query_id('invoice_id');
$baseQuery = "SELECT i.*,r.rental_id,r.total_amount,c.first_name,c.last_name,v.plate_no,COALESCE(SUM(p.pay_amount),0) AS paid FROM tbl_invoice i JOIN tbl_rental r ON r.rental_id=i.rental_id JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id LEFT JOIN tbl_payment p ON p.invoice_id=i.invoice_id";
if ($invoiceId > 0) {
    $statement = $pdo->prepare($baseQuery . ' WHERE i.invoice_id=? GROUP BY i.invoice_id');
    $statement->execute([$invoiceId]);
    $invoice = $statement->fetch();
    if (!$invoice) {
        echo '<div class="alert alert-error">Invoice not found.</div>';
    } else {
        $statement = $pdo->prepare('SELECT p.*,u.username FROM tbl_payment p JOIN tbl_user u ON u.user_id=p.processed_by WHERE p.invoice_id=? ORDER BY p.created_at DESC');
        $statement->execute([$invoiceId]);
        $payments = $statement->fetchAll();
        $balance = max(0, round((float) $invoice['invoice_amount'] - (float) $invoice['paid'], 2));
        ?>
        <div class="page-heading no-print"><div><p class="eyebrow">INVOICE DETAILS</p><h1>Invoice #I<?= (int) $invoice['invoice_id'] ?></h1><p class="heading-copy"><?= e($invoice['description']) ?> · Rental #R<?= (int) $invoice['rental_id'] ?></p></div><div class="heading-actions"><a class="button button-secondary" href="index.php?page=billing">← All invoices</a><button class="button button-primary" type="button" onclick="window.print()">Print invoice</button></div></div>
        <section class="panel invoice-panel"><div class="invoice-brand"><img class="brand-logo" src="assets/images/logo.svg" alt=""><div><strong>Kcart Rentals</strong><span>Rental billing statement</span></div><?= render_status($invoice['invoice_status']) ?></div><div class="invoice-summary"><div><span>Invoice number</span><strong>#I<?= (int) $invoice['invoice_id'] ?></strong></div><div><span>Issued</span><strong><?= e(date('M j, Y', strtotime($invoice['created_at']))) ?></strong></div><div><span>Due date</span><strong><?= e($invoice['due_date'] ? date('M j, Y', strtotime($invoice['due_date'])) : 'On receipt') ?></strong></div></div><div class="invoice-customer"><span>BILLED TO</span><strong><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></strong><small>Rental #R<?= (int) $invoice['rental_id'] ?> · Vehicle plate <?= e($invoice['plate_no']) ?></small></div><div class="invoice-line"><span><?= e($invoice['description']) ?></span><strong><?= e(money($invoice['invoice_amount'])) ?></strong></div><div class="invoice-totals"><div><span>Invoice total</span><strong><?= e(money($invoice['invoice_amount'])) ?></strong></div><div><span>Payments received</span><strong><?= e(money($invoice['paid'])) ?></strong></div><div class="invoice-balance"><span>Balance due</span><strong><?= e(money($balance)) ?></strong></div></div>
        <?php if ($balance > 0): ?><div class="payment-entry no-print"><h2>Record a payment</h2><p>Payment amount cannot exceed the remaining balance.</p><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="payment_save"><input type="hidden" name="invoice_id" value="<?= (int) $invoiceId ?>"><label>Payment method<select name="pay_method"><?php foreach (['Cash','Card','GCash','BankTransfer'] as $method): ?><option value="<?= e($method) ?>"><?= e($method === 'BankTransfer' ? 'Bank transfer' : $method) ?></option><?php endforeach; ?></select></label><label>Reference number<input name="reference_no" maxlength="100" placeholder="Optional"></label><label>Payment amount (₱)<input type="number" name="pay_amount" min="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>" step="0.01" required></label><div class="form-actions"><button class="button button-primary">Save payment</button></div></form></div><?php endif; ?>
        <div class="payment-history"><h2>Payment history</h2><?php if (!$payments): ?><p class="field-note">No payments recorded for this invoice.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>DATE</th><th>METHOD</th><th>REFERENCE</th><th>PROCESSED BY</th><th>AMOUNT</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?= e(date('M j, Y · g:i A', strtotime($payment['created_at']))) ?></td><td><?= e($payment['pay_method']) ?></td><td><?= e($payment['reference_no'] ?: '—') ?></td><td><?= e($payment['username']) ?></td><td><?= e(money($payment['pay_amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
        <?php
    }
} else {
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    $query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $validStatuses = ['', 'Unpaid', 'Partial', 'Paid'];
    if (!in_array($status, $validStatuses, true)) {
        $status = '';
    }
    $conditions = [];
    $parameters = [];
    if ($status !== '') {
        $conditions[] = 'i.invoice_status=?';
        $parameters[] = $status;
    }
    if ($query !== '') {
        $conditions[] = '(CAST(i.invoice_id AS CHAR) LIKE ? OR i.description LIKE ? OR CAST(r.rental_id AS CHAR) LIKE ? OR CONCAT(c.first_name," ",c.last_name) LIKE ? OR v.plate_no LIKE ? OR EXISTS (SELECT 1 FROM tbl_payment search_payment WHERE search_payment.invoice_id=i.invoice_id AND search_payment.reference_no LIKE ?))';
        $like = '%' . $query . '%';
        array_push($parameters, $like, $like, $like, $like, $like, $like);
    }
    $statement = $pdo->prepare($baseQuery . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' GROUP BY i.invoice_id ORDER BY i.created_at DESC');
    $statement->execute($parameters);
    $invoices = $statement->fetchAll();
    $outstanding = (float) $pdo->query("SELECT COALESCE(SUM(i.invoice_amount-COALESCE(p.paid,0)),0) FROM tbl_invoice i LEFT JOIN (SELECT invoice_id,SUM(pay_amount) paid FROM tbl_payment GROUP BY invoice_id) p ON p.invoice_id=i.invoice_id WHERE i.invoice_status<>'Paid'")->fetchColumn();
    ?>
    <div class="page-heading"><div><p class="eyebrow">INVOICES & COLLECTIONS</p><h1>Billing</h1><p class="heading-copy">Manage rental charges and record customer payments.</p></div><div class="heading-stat"><span>Total outstanding</span><strong><?= e(money($outstanding)) ?></strong></div></div>
    <section class="panel"><div class="panel-heading"><div><h2>Invoices <span class="count-pill"><?= count($invoices) ?></span></h2><p>Search by invoice, rental, customer, plate, or payment reference.</p></div><form method="get" class="search-form"><input type="hidden" name="page" value="billing"><input name="q" value="<?= e($query) ?>" placeholder="Invoice #, customer, reference" aria-label="Search invoices"><select name="status" aria-label="Filter invoice status"><?php foreach ($validStatuses as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option ?: 'All statuses') ?></option><?php endforeach; ?></select><button class="button button-secondary button-small">Search</button><?php if ($query || $status): ?><a class="button button-quiet button-small" href="index.php?page=billing">Clear</a><?php endif; ?></form></div>
    <div class="table-wrap"><table><thead><tr><th>INVOICE</th><th>DESCRIPTION</th><th>RENTAL / CUSTOMER</th><th>ISSUED</th><th>AMOUNT</th><th>PAID</th><th>STATUS</th><th></th></tr></thead><tbody>
    <?php if (!$invoices): ?><tr><td colspan="8"><div class="empty-state"><strong><?= $query ? 'No matching invoices' : 'No invoices to show' ?></strong><span><?= $query ? 'Try another invoice number, rental, customer, plate, or payment reference.' : 'Invoices are created as rentals progress.' ?></span></div></td></tr><?php endif; ?>
    <?php foreach ($invoices as $invoice): ?><tr><td><a class="table-primary" href="index.php?page=billing&invoice_id=<?= (int) $invoice['invoice_id'] ?>">#I<?= (int) $invoice['invoice_id'] ?></a></td><td><?= e($invoice['description']) ?></td><td><a class="table-primary" href="index.php?page=rentals&id=<?= (int) $invoice['rental_id'] ?>">#R<?= (int) $invoice['rental_id'] ?> · <?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></a></td><td><?= e(date('M j, Y', strtotime($invoice['created_at']))) ?></td><td><?= e(money($invoice['invoice_amount'])) ?></td><td><?= e(money($invoice['paid'])) ?></td><td><?= render_status($invoice['invoice_status']) ?></td><td><a class="button button-quiet button-small" href="index.php?page=billing&invoice_id=<?= (int) $invoice['invoice_id'] ?>"><?= $invoice['invoice_status'] === 'Paid' ? 'View' : 'Collect' ?></a></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
    <?php
}
