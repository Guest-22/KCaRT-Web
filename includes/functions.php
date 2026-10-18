<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $page = 'dashboard', array $query = []): never
{
    $query = ['page' => $page] + $query;
    header('Location: index.php?' . http_build_query($query));
    exit;
}

function query_id(string $key): int
{
    $value = $_GET[$key] ?? null;
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id === false || $id < 1 ? 0 : $id;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        redirect('login');
    }
}

function require_admin(): void
{
    require_login();
    if (current_user()['role'] !== 'Admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session token expired. Go back, reload the form, and try again.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function setting(PDO $pdo, string $key, string $default = '0.00'): string
{
    $statement = $pdo->prepare('SELECT setting_value FROM tbl_setting WHERE setting_key = ?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function money(mixed $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function chart_period(): array
{
    $rawOffset = $_GET['period'] ?? '0';
    $requestedOffset = is_string($rawOffset) ? filter_var($rawOffset, FILTER_VALIDATE_INT) : false;
    $offset = $requestedOffset === false ? 0 : min(0, max(-120, $requestedOffset));
    $offset = (int) (floor($offset / 6) * 6);
    $start = (new DateTimeImmutable('first day of this month'))->modify(($offset - 5) . ' months');
    $end = $start->modify('+6 months');
    return ['offset' => $offset, 'start' => $start, 'end' => $end];
}

function chart_period_controls(string $page, array $period): string
{
    $offset = (int) $period['offset'];
    $start = $period['start'];
    $end = $period['end']->modify('-1 month');
    $range = $start->format('M Y') . ' – ' . $end->format('M Y');
    $previous = $offset > -120
        ? '<a class="button button-quiet button-small" href="index.php?' . e(http_build_query(['page' => $page, 'period' => $offset - 6])) . '" aria-label="Show previous six months">← Previous</a>'
        : '<span class="button button-quiet button-small is-disabled" aria-disabled="true">← Previous</span>';
    $next = $offset < 0
        ? '<a class="button button-quiet button-small" href="index.php?' . e(http_build_query(['page' => $page, 'period' => $offset + 6])) . '" aria-label="Show next six months">Next →</a>'
        : '<span class="button button-quiet button-small is-disabled" aria-disabled="true">Next →</span>';
    return '<nav class="chart-period" aria-label="Chart date range">' . $previous . '<strong>' . e($range) . '</strong>' . $next . '</nav>';
}

function paginate_records(array $records, string $queryKey, int $perPage = 15): array
{
    $total = count($records);
    $pageCount = max(1, (int) ceil($total / $perPage));
    $rawPage = $_GET[$queryKey] ?? 1;
    $requestedPage = is_string($rawPage) || is_int($rawPage)
        ? filter_var($rawPage, FILTER_VALIDATE_INT)
        : false;
    $page = min($pageCount, max(1, $requestedPage === false ? 1 : $requestedPage));

    return [
        'items' => array_slice($records, ($page - 1) * $perPage, $perPage),
        'page' => $page,
        'page_count' => $pageCount,
        'per_page' => $perPage,
        'total' => $total,
    ];
}

function render_pagination(array $pagination, string $queryKey): string
{
    $page = (int) $pagination['page'];
    $pageCount = (int) $pagination['page_count'];
    $total = (int) $pagination['total'];
    $first = $total === 0 ? 0 : (($page - 1) * (int) $pagination['per_page']) + 1;
    $last = min($total, $page * (int) $pagination['per_page']);
    $query = array_filter($_GET, static fn(mixed $value): bool => is_scalar($value));

    $link = static function (int $targetPage) use ($query, $queryKey): string {
        $query[$queryKey] = $targetPage;
        return 'index.php?' . e(http_build_query($query));
    };
    $previous = $page > 1
        ? '<a class="button button-quiet button-small" href="' . $link($page - 1) . '" aria-label="Show previous 15 rows">← Previous</a>'
        : '<span class="button button-quiet button-small is-disabled" aria-disabled="true">← Previous</span>';
    $next = $page < $pageCount
        ? '<a class="button button-quiet button-small" href="' . $link($page + 1) . '" aria-label="Show next 15 rows">Next →</a>'
        : '<span class="button button-quiet button-small is-disabled" aria-disabled="true">Next →</span>';

    return '<nav class="pagination" aria-label="Table pages">' . $previous
        . '<strong>Showing ' . $first . '–' . $last . ' of ' . $total . '</strong>'
        . $next . '</nav>';
}

function full_name(array $person): string
{
    return trim(implode(' ', array_filter([
        $person['first_name'] ?? '',
        $person['middle_name'] ?? '',
        $person['last_name'] ?? '',
    ], static fn(string $part): bool => $part !== '')));
}

function post_string(string $key, int $maxLength = 255, bool $required = true): string
{
    $rawValue = $_POST[$key] ?? '';
    if (!is_string($rawValue)) {
        throw new InvalidArgumentException('Please enter valid form data.');
    }
    $value = trim($rawValue);
    if ($required && $value === '') {
        throw new InvalidArgumentException('Please complete all required fields.');
    }
    if (mb_strlen($value) > $maxLength) {
        throw new InvalidArgumentException('One or more fields are longer than allowed.');
    }
    return $value;
}

function post_password(string $key, bool $required = true, int $minimumLength = 0): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || ($required && $value === '') || strlen($value) > 72) {
        throw new InvalidArgumentException('Enter a valid password.');
    }
    if ($value !== '' && mb_strlen($value) < $minimumLength) {
        throw new InvalidArgumentException("Passwords must be at least {$minimumLength} characters.");
    }
    return $value;
}

function post_enum(string $key, array $allowed): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        throw new InvalidArgumentException('Please select a valid option.');
    }
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException('Please select a valid option.');
    }
    return $value;
}

function post_int(string $key, int $minimum = 0, int $maximum = PHP_INT_MAX): int
{
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < $minimum || $value > $maximum) {
        throw new InvalidArgumentException('Please enter a valid number.');
    }
    return $value;
}

function post_record_id(string $key): int
{
    $value = $_POST[$key] ?? null;
    if ($value === '0' || $value === 0) {
        return 0;
    }
    return post_int($key, 1);
}

function post_phone(string $key, bool $required = true): ?string
{
    $value = post_string($key, 20, $required);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^\+?[0-9(). -]+$/', $value)) {
        throw new InvalidArgumentException('Enter a valid contact number.');
    }
    $digits = preg_replace('/\D/', '', $value);
    if ($digits === null || strlen($digits) < 7 || strlen($digits) > 15) {
        throw new InvalidArgumentException('Contact numbers must contain 7 to 15 digits.');
    }
    return $value;
}

function post_amount(string $key, float $minimum = 0): float
{
    $rawValue = $_POST[$key] ?? null;
    if (!is_string($rawValue) || !preg_match('/^(?:\d{1,8}(?:\.\d{1,2})?|\.\d{1,2})$/', $rawValue)) {
        throw new InvalidArgumentException('Please enter a valid amount.');
    }
    $value = (float) $rawValue;
    if (!is_finite($value) || $value < $minimum || $value > 99999999.99) {
        throw new InvalidArgumentException('Please enter a valid amount.');
    }
    return round($value, 2);
}

function post_email(string $key, bool $required = false): ?string
{
    $value = post_string($key, 100, $required);
    if ($value === '') {
        return null;
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    return $value;
}

function post_optional_date(string $key): ?string
{
    $rawValue = $_POST[$key] ?? '';
    if (!is_string($rawValue)) {
        throw new InvalidArgumentException('Please enter valid date data.');
    }
    $value = trim($rawValue);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $date > new DateTime('today')) {
        throw new InvalidArgumentException('Please enter a valid date that is not in the future.');
    }
    return $value;
}

function post_adult_date(string $key, int $minimumAge = 20): string
{
    $value = post_optional_date($key);
    if ($value === null) {
        throw new InvalidArgumentException('Enter the customer’s date of birth.');
    }
    $birthDate = new DateTimeImmutable($value);
    $latestAllowedBirthDate = (new DateTimeImmutable('today'))->modify("-{$minimumAge} years");
    if ($birthDate > $latestAllowedBirthDate) {
        throw new InvalidArgumentException("Customers must be at least {$minimumAge} years old.");
    }
    return $value;
}

function post_date(string $key): string
{
    $rawValue = $_POST[$key] ?? '';
    if (!is_string($rawValue)) {
        throw new InvalidArgumentException('Please enter valid date and time data.');
    }
    $value = trim($rawValue);
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
        throw new InvalidArgumentException('Please enter a valid date and time.');
    }
    return $date->format('Y-m-d H:i:s');
}

function uploaded_blob(string $key): ?string
{
    $file = $_FILES[$key] ?? null;
    if ($file === null || (is_array($file) && ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE)) {
        return null;
    }
    if (!is_array($file) || !isset($file['error'], $file['size'], $file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK || !is_int($file['size']) || $file['size'] > 5 * 1024 * 1024 || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Uploads must be no larger than 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Upload a JPG, PNG, or WebP image.');
    }
    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false) {
        throw new RuntimeException('The uploaded file could not be read.');
    }
    return $contents;
}

function status_class(string $status): string
{
    return match (strtolower($status)) {
        'available', 'active', 'paid', 'completed' => 'status-good',
        'reserved', 'pending', 'partial', 'late', 'maintenance' => 'status-warn',
        'rented', 'unpaid', 'dismissed', 'inactive' => 'status-muted',
        default => 'status-muted',
    };
}

function render_status(string $status): string
{
    return '<span class="status ' . e(status_class($status)) . '">' . e($status) . '</span>';
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    verify_csrf();
}
