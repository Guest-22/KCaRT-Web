<?php
$isAdmin = current_user()['role'] === 'Admin';
$editId = $isAdmin ? query_id('edit') : 0;
$editing = null;
if ($editId > 0) {
    $statement = $pdo->prepare('SELECT * FROM tbl_car WHERE car_id=?');
    $statement->execute([$editId]);
    $editing = $statement->fetch();
}
$filter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$vehicleTypes = ['', 'Sedan', 'SUV', 'Hatchback', 'MPV', 'Van', 'Pickup'];
if (!in_array($type, $vehicleTypes, true)) {
    $type = '';
}
$allowedFilters = ['', 'Available', 'Reserved', 'Rented', 'Maintenance'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = '';
}
$conditions = [];
$parameters = [];
if ($filter !== '') {
    $conditions[] = 'car_status=?';
    $parameters[] = $filter;
}
if ($query !== '') {
    $conditions[] = '(plate_no LIKE ? OR brand LIKE ? OR model LIKE ? OR color LIKE ? OR car_type LIKE ?)';
    $like = '%' . $query . '%';
    array_push($parameters, $like, $like, $like, $like, $like);
}
$conditionsSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
if ($type !== '') {
    $conditions[] = 'car_type=?';
    $parameters[] = $type;
    $conditionsSql = ' WHERE ' . implode(' AND ', $conditions);
}
$countStatement = $pdo->prepare('SELECT COUNT(*) FROM tbl_car' . $conditionsSql);
$countStatement->execute($parameters);
$resultCount = (int) $countStatement->fetchColumn();
$perPage = 12;
$pageCount = max(1, (int) ceil($resultCount / $perPage));
$fleetPage = filter_var($_GET['fleet_page'] ?? 1, FILTER_VALIDATE_INT);
$fleetPage = $fleetPage === false ? 1 : min($pageCount, max(1, $fleetPage));
$offset = ($fleetPage - 1) * $perPage;
$statement = $pdo->prepare('SELECT car_id,plate_no,brand,model,car_type,year,color,transmission_type,fuel_type,seat_cap,mileage,daily_rate,car_status,created_at,(car_photo IS NOT NULL) AS has_photo FROM tbl_car' . $conditionsSql . ' ORDER BY created_at DESC,car_id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$statement->execute($parameters);
$cars = $statement->fetchAll();
$fleetCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_car')->fetchColumn();
$vehicleForm = $isAdmin && ($editing || isset($_GET['new']));
?>
<div class="page-heading"><div><p class="eyebrow">YOUR FLEET, UNDER CONTROL</p><h1>Vehicle fleet</h1><p class="heading-copy">Manage vehicles, daily rates, and availability.</p></div><?php if ($isAdmin && !$vehicleForm): ?><a class="button button-primary" href="index.php?page=vehicles&new=1">＋ Add vehicle</a><?php endif; ?></div>
<?php if ($vehicleForm): ?>
<section class="panel form-panel"><div class="panel-heading"><div><h2><?= $editing ? 'Edit vehicle' : 'Add vehicle' ?></h2><p>Vehicle specifications and rental rate.</p></div></div>
<form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="vehicle_save"><input type="hidden" name="car_id" value="<?= (int) ($editing['car_id'] ?? 0) ?>">
    <label>Plate number <span class="required">*</span><input name="plate_no" maxlength="20" required value="<?= e($editing['plate_no'] ?? '') ?>"></label>
    <label>Brand <span class="required">*</span><input name="brand" maxlength="50" required value="<?= e($editing['brand'] ?? '') ?>"></label>
    <label>Model <span class="required">*</span><input name="model" maxlength="50" required value="<?= e($editing['model'] ?? '') ?>"></label>
    <label>Vehicle type<select name="car_type" required><?php foreach (['Sedan','SUV','Hatchback','MPV','Van','Pickup'] as $option): ?><option <?= ($editing['car_type'] ?? 'Sedan') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
    <label>Year<input type="number" name="year" min="1980" max="<?= (int) date('Y') + 1 ?>" required value="<?= e($editing['year'] ?? date('Y')) ?>"></label>
    <label>Color<input name="color" maxlength="30" required value="<?= e($editing['color'] ?? '') ?>"></label>
    <label>Transmission<select name="transmission_type"><?php foreach (['Automatic','Manual'] as $option): ?><option <?= ($editing['transmission_type'] ?? 'Automatic') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
    <label>Fuel type<select name="fuel_type"><?php foreach (['Gasoline','Diesel','Electric','Hybrid'] as $option): ?><option <?= ($editing['fuel_type'] ?? 'Gasoline') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
    <label>Seat capacity<input type="number" name="seat_cap" min="1" max="99" required value="<?= e($editing['seat_cap'] ?? 5) ?>"></label>
    <label>Current mileage (km)<input type="number" name="mileage" min="0" max="2147483647" required value="<?= e($editing['mileage'] ?? 0) ?>"></label>
    <label>Daily rate (₱)<input type="number" name="daily_rate" min="0.01" max="99999999.99" step="0.01" required value="<?= e($editing['daily_rate'] ?? '') ?>"></label>
    <label>Vehicle photo<input type="file" name="car_photo" accept="image/jpeg,image/png,image/webp"><?php if ($editing && $editing['car_photo']): ?><small class="field-note"><a class="text-link" target="_blank" rel="noopener" href="index.php?image=car&id=<?= (int) $editing['car_id'] ?>">View saved photo</a> · Choose a new image to replace it.</small><?php endif; ?></label>
    <div class="form-actions span-two"><a href="index.php?page=vehicles" class="button button-quiet">Cancel</a><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Add vehicle' ?></button></div>
</form></section>
<?php else: ?>
<section class="panel"><div class="panel-heading"><div><h2>Fleet inventory <span class="count-pill"><?= $resultCount ? ($offset + 1) . '–' . min($offset + $perPage, $resultCount) : 0 ?> / <?= $resultCount ?> matching · <?= $fleetCount ?> total</span></h2><p>Search by plate, brand, model, color, or vehicle type; filter by availability.</p></div><form method="get" class="search-form"><input type="hidden" name="page" value="vehicles"><input name="q" value="<?= e($query) ?>" placeholder="Plate, brand, model, color, type" aria-label="Search vehicles"><select name="status" aria-label="Filter by status"><?php foreach ($allowedFilters as $option): ?><option value="<?= e($option) ?>" <?= $filter === $option ? 'selected' : '' ?>><?= e($option ?: 'All vehicles') ?></option><?php endforeach; ?></select><button class="button button-secondary button-small">Search</button><?php if ($query || $filter): ?><a class="button button-quiet button-small" href="index.php?page=vehicles">Clear</a><?php endif; ?></form></div>
<div class="vehicle-grid">
<?php if (!$cars): ?><div class="empty-state vehicle-empty"><strong><?= $query ? 'No matching vehicles' : ($fleetCount ? 'No vehicles in this category' : 'Your fleet is empty') ?></strong><span><?= $query ? 'Try a different plate, brand, model, color, type, or status.' : 'Add vehicles to make them available for reservations.' ?></span><?php if ($isAdmin && !$fleetCount && !$query): ?><a class="button button-secondary button-small" href="index.php?page=vehicles&new=1">Add vehicle</a><?php endif; ?></div><?php endif; ?>
<?php foreach ($cars as $car): ?><article class="vehicle-card">
    <div class="vehicle-image"><?php if ($car['has_photo']): ?><img src="index.php?image=car&id=<?= (int) $car['car_id'] ?>" alt="<?= e($car['brand'] . ' ' . $car['model']) ?>"><?php else: ?><span class="vehicle-placeholder">▰</span><?php endif; ?><span class="vehicle-status"><?= render_status($car['car_status']) ?></span></div>
    <div class="vehicle-info"><div class="vehicle-title"><div><h3><?= e($car['brand'] . ' ' . $car['model']) ?></h3><span><?= e($car['plate_no']) ?> · <?= e($car['year']) ?></span></div><?php if ($isAdmin): ?><a class="icon-button" href="index.php?page=vehicles&edit=<?= (int) $car['car_id'] ?>" aria-label="Edit vehicle">✎</a><?php endif; ?></div>
        <div class="vehicle-specs"><span><?= e($car['car_type']) ?></span><span><?= e($car['transmission_type']) ?></span><span><?= (int) $car['seat_cap'] ?> seats</span></div>
        <div class="vehicle-bottom"><strong><?= e(money($car['daily_rate'])) ?><small> / day</small></strong><?php if ($isAdmin): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="vehicle_status"><input type="hidden" name="car_id" value="<?= (int) $car['car_id'] ?>"><select name="car_status" aria-label="Vehicle fleet status"><option value="Available" <?= $car['car_status'] === 'Available' ? 'selected' : '' ?>>Available</option><option value="Maintenance" <?= $car['car_status'] === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option></select><button class="text-link" title="Save status">Save</button></form><?php else: ?><?= render_status($car['car_status']) ?><?php endif; ?></div>
    </div>
</article><?php endforeach; ?>
</div></section>
<?php if ($pageCount > 1): $baseFleetQuery = ['page' => 'vehicles', 'q' => $query, 'status' => $filter, 'type' => $type]; ?>
<nav class="pagination" aria-label="Vehicle fleet pages">
    <?php if ($fleetPage > 1): ?><a class="button button-quiet button-small" href="index.php?<?= e(http_build_query($baseFleetQuery + ['fleet_page' => $fleetPage - 1])) ?>">← Previous</a><?php else: ?><span class="button button-quiet button-small is-disabled" aria-disabled="true">← Previous</span><?php endif; ?>
    <strong>Page <?= $fleetPage ?> of <?= $pageCount ?></strong>
    <?php if ($fleetPage < $pageCount): ?><a class="button button-quiet button-small" href="index.php?<?= e(http_build_query($baseFleetQuery + ['fleet_page' => $fleetPage + 1])) ?>">Next →</a><?php else: ?><span class="button button-quiet button-small is-disabled" aria-disabled="true">Next →</span><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
