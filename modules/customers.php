<?php
$editId = query_id('edit');
$editing = null;
if ($editId > 0) {
    $statement = $pdo->prepare('SELECT * FROM tbl_customer WHERE customer_id=?');
    $statement->execute([$editId]);
    $editing = $statement->fetch();
}
$query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
if ($query !== '') {
    $statement = $pdo->prepare('SELECT customer_id,first_name,middle_name,last_name,contact_no,email,customer_status,created_at,(customer_photo IS NOT NULL) AS has_photo FROM tbl_customer WHERE CONCAT(first_name," ",COALESCE(middle_name,"")," ",last_name) LIKE ? OR contact_no LIKE ? OR email LIKE ? OR address LIKE ? ORDER BY created_at DESC');
    $like = '%' . $query . '%';
    $statement->execute([$like, $like, $like, $like]);
    $customers = $statement->fetchAll();
} else {
    $customers = $pdo->query('SELECT customer_id,first_name,middle_name,last_name,contact_no,email,customer_status,created_at,(customer_photo IS NOT NULL) AS has_photo FROM tbl_customer ORDER BY created_at DESC')->fetchAll();
}
$customerCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_customer')->fetchColumn();
$customerForm = $editing || isset($_GET['new']);
?>
<div class="page-heading"><div><p class="eyebrow">PEOPLE FIRST</p><h1>Customers</h1><p class="heading-copy">Keep renter details and documents in one place.</p></div><?php if (!$customerForm): ?><a class="button button-primary" href="index.php?page=customers&new=1">＋ Add customer</a><?php endif; ?></div>
<?php if ($customerForm): ?>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2><?= $editing ? 'Edit customer' : 'New customer' ?></h2><p>Customers must be at least 20 years old. Fields marked with <span class="required">*</span> are required.</p></div></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="customer_save"><input type="hidden" name="customer_id" value="<?= (int) ($editing['customer_id'] ?? 0) ?>">
        <label>First name <span class="required">*</span><input name="first_name" maxlength="50" required value="<?= e($editing['first_name'] ?? '') ?>"></label>
        <label>Middle name<input name="middle_name" maxlength="50" value="<?= e($editing['middle_name'] ?? '') ?>"></label>
        <label>Last name <span class="required">*</span><input name="last_name" maxlength="50" required value="<?= e($editing['last_name'] ?? '') ?>"></label>
        <label>Sex <span class="required">*</span><select name="sex" required><option value="">Select</option><?php foreach (['Male','Female','Other'] as $option): ?><option <?= ($editing['sex'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
        <label>Date of birth <span class="required">*</span><input type="date" name="date_of_birth" max="<?= e(date('Y-m-d', strtotime('-20 years'))) ?>" required value="<?= e($editing['date_of_birth'] ?? '') ?>"></label>
        <label>Contact number <span class="required">*</span><input type="tel" name="contact_no" maxlength="20" required value="<?= e($editing['contact_no'] ?? '') ?>"></label>
        <label>Email address<input type="email" name="email" maxlength="100" value="<?= e($editing['email'] ?? '') ?>"></label>
        <label>Customer status<select name="customer_status"><option <?= ($editing['customer_status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option><option <?= ($editing['customer_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
        <label class="span-two">Address<input name="address" maxlength="255" value="<?= e($editing['address'] ?? '') ?>"></label>
        <div class="form-divider span-two"><span>Documents & photo</span><small>JPG, PNG, or WebP · max 5 MB per file</small></div>
        <?php foreach (['customer_photo' => 'Customer photo', 'driver_license' => "Driver's license", 'secondary_id' => 'Secondary ID'] as $field => $label): ?>
            <label><?= e($label) ?><input type="file" name="<?= e($field) ?>" accept="image/jpeg,image/png,image/webp" data-file-preview><?php if ($editing && !empty($editing[$field])): ?><small class="field-note"><a class="text-link" target="_blank" rel="noopener" href="index.php?image=<?= e(match ($field) { 'driver_license' => 'license', 'secondary_id' => 'secondary-id', default => 'customer' }) ?>&id=<?= (int) $editing['customer_id'] ?>">View saved image</a> · Choose a new image to replace it.</small><?php endif; ?></label>
        <?php endforeach; ?>
        <div class="form-actions span-two"><a href="index.php?page=customers" class="button button-quiet">Cancel</a><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Add customer' ?></button></div>
    </form>
</section>
<?php else: ?>
<section class="panel">
    <div class="panel-heading"><div><h2>Customer directory <span class="count-pill"><?= count($customers) ?> / <?= $customerCount ?></span></h2><p>Search by renter name, phone, email, or address.</p></div><form class="search-form" method="get"><input type="hidden" name="page" value="customers"><input name="q" value="<?= e($query) ?>" placeholder="Name, phone, email, address" aria-label="Search customers"><button class="button button-secondary button-small">Search</button><?php if ($query): ?><a class="button button-quiet button-small" href="index.php?page=customers">Clear</a><?php endif; ?></form></div>
    <div class="table-wrap"><table><thead><tr><th>CUSTOMER</th><th>CONTACT</th><th>EMAIL</th><th>STATUS</th><th>ADDED</th><th></th></tr></thead><tbody>
    <?php if (!$customers): ?><tr><td colspan="6"><div class="empty-state"><strong><?= $query ? 'No matching customers' : 'Your customer list is empty' ?></strong><span><?= $query ? 'Try a different name, contact, email, or address.' : 'Add your first customer to get started.' ?></span><?php if (!$query): ?><a class="button button-secondary button-small" href="index.php?page=customers&new=1">Add customer</a><?php endif; ?></div></td></tr><?php endif; ?>
    <?php foreach ($customers as $customer): ?><tr><td class="person-cell"><?php if ($customer['has_photo']): ?><img class="person-avatar" src="index.php?image=customer&id=<?= (int) $customer['customer_id'] ?>" alt=""><?php else: ?><span class="person-avatar person-avatar-fallback"><?= e(strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1))) ?></span><?php endif; ?><span><strong><?= e(full_name($customer)) ?></strong><small>Customer #<?= (int) $customer['customer_id'] ?></small></span></td><td><?= e($customer['contact_no']) ?></td><td><?= e($customer['email'] ?: '—') ?></td><td><?= render_status($customer['customer_status']) ?></td><td><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td><td><a class="button button-quiet button-small" href="index.php?page=customers&edit=<?= (int) $customer['customer_id'] ?>">Edit</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
