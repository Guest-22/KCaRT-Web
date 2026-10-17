<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/actions.php';

if (current_user() !== null) {
    $accountQuery = $pdo->prepare("SELECT user_id,first_name,middle_name,last_name,role,username,user_status FROM tbl_user WHERE user_id=? AND user_status='Active'");
    $accountQuery->execute([current_user()['user_id']]);
    $freshAccount = $accountQuery->fetch();
    if (!$freshAccount) {
        unset($_SESSION['user']);
        flash('This account is no longer active. Contact an administrator.');
        redirect('login');
    }
    $_SESSION['user'] = $freshAccount;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    process_action($pdo);
}

if (current_user() !== null) {
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE tbl_rental SET rental_status='Dismissed' WHERE rental_status='Pending' AND start_date <= DATE_SUB(?, INTERVAL 1 HOUR)")
        ->execute([$now]);
    $pdo->prepare("UPDATE tbl_car c SET c.car_status='Available' WHERE c.car_status='Reserved' AND NOT EXISTS (SELECT 1 FROM tbl_rental r WHERE r.car_id=c.car_id AND r.rental_status IN ('Pending','Active','Late'))")->execute();
    $pdo->prepare("UPDATE tbl_rental SET rental_status='Late' WHERE rental_status='Active' AND expected_return_date < ?")->execute([$now]);
}

$requestedPage = $_GET['page'] ?? (current_user() ? 'dashboard' : 'login');
$page = is_string($requestedPage) ? $requestedPage : 'dashboard';
$publicPages = ['login'];
$adminPages = ['users', 'settings'];
$knownPages = ['login', 'dashboard', 'customers', 'vehicles', 'rentals', 'returns', 'billing', 'users', 'reports', 'settings'];
if (!in_array($page, $knownPages, true)) {
    $page = 'dashboard';
}
if (!in_array($page, $publicPages, true)) {
    require_login();
}
if (in_array($page, $adminPages, true)) {
    require_admin();
}

$titles = [
    'dashboard' => 'Dashboard',
    'customers' => 'Customers',
    'vehicles' => 'Fleet',
    'rentals' => 'Rentals',
    'returns' => 'Returns',
    'billing' => 'Billing',
    'users' => 'Team & access',
    'reports' => 'Reports',
    'settings' => 'Settings',
    'login' => 'Sign in',
];
$title = $titles[$page];
$flashMessage = take_flash();

if (isset($_GET['image'], $_GET['id'])) {
    $imageKind = is_string($_GET['image']) ? $_GET['image'] : '';
    $imageId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $table = $imageKind === 'car' ? 'tbl_car' : 'tbl_customer';
    $column = match ($imageKind) {
        'car' => 'car_photo',
        'customer' => 'customer_photo',
        'license' => 'driver_license',
        'secondary-id' => 'secondary_id',
        default => null,
    };
    if ($column !== null && $imageId !== false && $imageId > 0) {
        $statement = $pdo->prepare("SELECT {$column} FROM {$table} WHERE " . ($table === 'tbl_car' ? 'car_id' : 'customer_id') . '=?');
        $statement->execute([$imageId]);
        $blob = $statement->fetchColumn();
        if (is_string($blob) && $blob !== '') {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($blob);
            if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                header('Content-Type: ' . $mime);
                header('Cache-Control: private, max-age=3600');
                echo $blob;
                exit;
            }
        }
    }
    http_response_code(404);
    exit('Image not found.');
}

if ($page === 'reports' && isset($_GET['export'])) {
    $export = (string) $_GET['export'];
    if ($export === 'rentals') {
        $rows = $pdo->query('SELECT r.rental_id, c.first_name, c.last_name, v.plate_no, r.start_date, r.expected_return_date, r.rental_status, r.total_amount FROM tbl_rental r JOIN tbl_customer c ON c.customer_id=r.customer_id JOIN tbl_car v ON v.car_id=r.car_id ORDER BY r.created_at DESC')->fetchAll();
        $filename = 'rental-report.csv';
        $headers = ['Rental ID', 'Customer first name', 'Customer last name', 'Plate', 'Pickup', 'Expected return', 'Status', 'Total amount'];
    } elseif ($export === 'payments') {
        $rows = $pdo->query('SELECT p.payment_id, i.invoice_id, r.rental_id, p.pay_method, p.reference_no, p.pay_amount, p.created_at, u.username FROM tbl_payment p JOIN tbl_invoice i ON i.invoice_id=p.invoice_id JOIN tbl_rental r ON r.rental_id=i.rental_id JOIN tbl_user u ON u.user_id=p.processed_by ORDER BY p.created_at DESC')->fetchAll();
        $filename = 'payment-report.csv';
        $headers = ['Payment ID', 'Invoice ID', 'Rental ID', 'Method', 'Reference', 'Amount', 'Paid at', 'Processed by'];
    } else {
        http_response_code(400);
        exit('Unknown report.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Could not create the report download.');
    }
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, array_values($row));
    }
    fclose($output);
    exit;
}

require __DIR__ . '/includes/header.php';

$module = __DIR__ . '/modules/' . $page . '.php';
if (is_file($module)) {
    require $module;
}

require __DIR__ . '/includes/footer.php';
