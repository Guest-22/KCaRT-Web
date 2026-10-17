# Kcart Car Rental Management System

A local car-rental operations app built with PHP 8.1+, MySQL/MariaDB, HTML, CSS, and JavaScript for XAMPP. The interface works without a network connection.

## XAMPP setup

1. Copy the project folder into `C:\xampp\htdocs\car_rental_management_system\kcart` (or keep the current location).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`, select **Import**, and import `database/schema.sql`. This creates the `crms_db` database and all application tables.
4. The default local XAMPP connection is `127.0.0.1`, database `crms_db`, user `root`, and a blank password. If your MySQL account differs, set `CRMS_DB_HOST`, `CRMS_DB_NAME`, `CRMS_DB_USER`, and `CRMS_DB_PASSWORD` in the Apache/PHP environment before restarting Apache.
5. For customer photo and ID uploads, allow at least `5M` for `upload_max_filesize` and `18M` for `post_max_size` in `C:\xampp\php\php.ini`, then restart Apache.
6. On a fresh database, follow **Create the first administrator** below; the current local test database already has test accounts. Then visit `http://localhost/car_rental_management_system/kcart/`.

## Roles and access

- **Admin:** full workspace access, including team accounts and system settings.
- **Staff:** day-to-day dashboard, customer, fleet, rental, return, billing, and report access. Team management and system settings are restricted to Admin.

Access is checked server-side, so hiding the management links for Staff is not the only restriction.

## Create the first administrator

There is intentionally no seeded password or default login. On a fresh database, generate a hash for a unique password in a terminal:

```powershell
& 'C:\xampp\php\php.exe' -r 'echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;' 'Replace-with-a-unique-password'
```

In phpMyAdmin, select `crms_db`, open the **SQL** tab, and run the following after replacing `PASTE_GENERATED_HASH_HERE` with the generated hash and changing the account details:

```sql
INSERT INTO tbl_user
    (first_name, last_name, sex, role, username, password, user_status)
VALUES
    ('System', 'Administrator', 'Other', 'Admin', 'admin',
     'PASTE_GENERATED_HASH_HERE', 'Active');
```

Sign in with the username and the original password (not the hash). Remove the temporary password from shell history if you entered it directly in a shared terminal.

The requested test accounts exist only in the current local database and are not included in the schema or source files. Their credentials are for local testing only; change them before exposing the app to a network or using real data.

The local test database is populated with fictional customers from Bataan municipalities, sample vehicles, and reservations/rentals across pending, active, late, and completed states. The records are synthetic and should not be treated as real customer or payment information.

## Updated operating guide

See [CRMS_Overall.pdf](./CRMS_Overall.pdf) for role-based Admin and Staff procedures, the end-to-end rental workflow, and instructions for replacing the logo and customer/vehicle images. The editable source is `docs/workflow-guide.html`; the previous base document is preserved as `CRMS_Overall_Source.pdf`.

The replaceable app logo is `assets/images/logo.svg`. Customer and vehicle photos are validated uploads stored in MySQL (`tbl_customer` and `tbl_car` image columns), not loose files under the project folder.

## Included workflows

- Customer directory, customer photo, driver's license and secondary-ID image uploads.
- Vehicle fleet, daily rates, mileage, fleet status, and vehicle photos.
- Reservations with day-based rental pricing and a configurable reservation fee; reservation invoices must be paid before pickup.
- Pickup activation with a one-hour grace period, late rental tracking, no-show release, and return processing.
- Damage invoices and configurable per-day late charges created at return.
- Invoice and receipt views, printable statements, full or partial payments, and balance tracking.
- Admin/staff accounts, role-restricted settings and user administration, operational dashboard, CSV exports, and reports.
- Local business context: Bataan, Philippines, using Asia/Manila time and PHP currency formatting.
- Dashboard and reports include six-month collection charts, rental-status charts, fleet-availability charts, and summary metrics.
- Searchable directory/list lookups are available for customers, vehicles, rentals, returns, invoices, and (for Admins) team accounts; existing status filters can be combined with search.

No-show reservations are dismissed and the car is released; the app does not automatically refund a paid reservation invoice. The PDF does not specify a refund policy.

## Automatic status maintenance

The app refreshes no-shows and overdue rentals when an authenticated page is opened. For status changes even when nobody is using the site, create a Windows Task Scheduler task that runs every five minutes:

- **Program:** `C:\xampp\php\php.exe`
- **Arguments:** `C:\xampp\htdocs\car_rental_management_system\kcart\maintenance.php`
- **Start in:** `C:\xampp\htdocs\car_rental_management_system\kcart`

The maintenance script is CLI-only and does not expose an unauthenticated web endpoint.

## Configuration and data

The PDO connection can be customized with the `CRMS_DB_HOST`, `CRMS_DB_NAME`, `CRMS_DB_USER`, and `CRMS_DB_PASSWORD` environment variables. Set `CRMS_TIMEZONE` to override the default `Asia/Manila` application timezone.

Uploaded images are validated as JPG, PNG, or WebP (maximum 5 MB each) and stored in the database. Rental, invoice, return, payment, and customer records are preserved; use inactive status instead of deleting history.
