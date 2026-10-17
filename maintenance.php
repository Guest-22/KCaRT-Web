<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$pdo = require __DIR__ . '/config/db.php';
date_default_timezone_set(getenv('CRMS_TIMEZONE') ?: 'Asia/Manila');
$now = date('Y-m-d H:i:s');
$dismissed = $pdo->prepare("UPDATE tbl_rental SET rental_status='Dismissed' WHERE rental_status='Pending' AND start_date <= DATE_SUB(?, INTERVAL 1 HOUR)");
$dismissed->execute([$now]);
$released = $pdo->exec("UPDATE tbl_car c SET c.car_status='Available' WHERE c.car_status='Reserved' AND NOT EXISTS (SELECT 1 FROM tbl_rental r WHERE r.car_id=c.car_id AND r.rental_status IN ('Pending','Active','Late'))");
$late = $pdo->prepare("UPDATE tbl_rental SET rental_status='Late' WHERE rental_status='Active' AND expected_return_date < ?");
$late->execute([$now]);

printf(
    "Rental maintenance complete: %d no-shows dismissed, %d vehicles released, %d rentals marked late.\n",
    $dismissed->rowCount(),
    $released,
    $late->rowCount()
);
