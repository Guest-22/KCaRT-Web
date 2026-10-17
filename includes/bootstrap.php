<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

date_default_timezone_set(getenv('CRMS_TIMEZONE') ?: 'Asia/Manila');

try {
    $pdo = require __DIR__ . '/../config/db.php';
} catch (PDOException $exception) {
    http_response_code(503);
    exit('Database connection failed. Check the XAMPP setup instructions and database configuration.');
}

require_once __DIR__ . '/functions.php';
