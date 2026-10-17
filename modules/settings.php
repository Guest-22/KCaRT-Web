<?php
$reservationFee = setting($pdo, 'reservation_fee', '1000.00');
$lateFee = setting($pdo, 'late_fee_per_day', '0.00');
?>
<div class="page-heading"><div><p class="eyebrow">MAKE IT WORK FOR YOU</p><h1>Settings</h1><p class="heading-copy">Choose the billing rules applied to new charges.</p></div></div>
<section class="panel settings-panel"><div class="panel-heading"><div><h2>Rental billing rules</h2><p>Changes apply to invoices created after you save.</p></div><span class="settings-icon">⚙</span></div>
<form method="post" class="settings-form"><?= csrf_field() ?><input type="hidden" name="action" value="settings_save"><label>Reservation fee (₱)<span>The upfront invoice for a new reservation. It will not exceed the rental total.</span><div class="currency-input"><span>₱</span><input type="number" name="reservation_fee" min="0" max="99999999.99" step="0.01" required value="<?= e($reservationFee) ?>"></div></label><label>Late return fee per day (₱)<span>Set to zero to disable late fees. Late fees are calculated when the vehicle is returned.</span><div class="currency-input"><span>₱</span><input type="number" name="late_fee_per_day" min="0" max="99999999.99" step="0.01" required value="<?= e($lateFee) ?>"></div></label><div class="settings-note"><strong>Pickup grace period</strong><span>Customers have one hour after their scheduled pickup time. Uncollected reservations are dismissed after the grace period and the vehicle becomes available again.</span></div><div class="form-actions"><button class="button button-primary">Save billing settings</button></div></form>
</section>
