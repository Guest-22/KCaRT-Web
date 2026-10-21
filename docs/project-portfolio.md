# Kcart Car Rental Management System

## Project Portfolio

**Prepared by:** [Add project team member names]  
**Program:** Bachelor of Science in Information Technology  
**Institution:** [Add institution name]  
**Submitted to:** [Add instructor/adviser name]  
**Submission date:** October 19, 2026  
**Project location:** Bataan, Philippines

> Replace the bracketed cover-page details before submission. This portfolio describes the current Kcart application in this repository; it supersedes older Java/Swing desktop-system descriptions.

## 1. Project Summary

Kcart is a browser-based, locally hosted car-rental operations system for a small rental business. It centralizes customer and fleet records, reservations, vehicle check-out and return, invoices, payment records, team access, and operational reporting. Authorized Admin and Staff users work in the same application with different permissions. Customers are served by the business and do not sign in to the system.

The application is implemented with PHP, HTML, CSS, and JavaScript. Apache and MySQL/MariaDB are provided locally by XAMPP. It is configured for Asia/Manila time and Philippine peso display, with sample business data based in Bataan. It does not connect to an online payment gateway.

## 2. Background and Problem Statement

Rental operations involve linked decisions and records: whether a customer may rent, which vehicle is available, when the rental begins and ends, how charges are calculated, whether a reservation payment has been received, and what condition the vehicle is in when returned. When these records are spread across paper forms or separate spreadsheets, staff may spend time cross-checking information and may introduce inconsistent availability, dates, charges, or payment balances.

Kcart addresses this operational need with a shared record system and a defined reservation-to-return workflow. Vehicle availability, invoice balances, and rental states are maintained alongside related customer, return, and payment records. Search, filters, status indicators, and reports help authorized users locate and monitor the information they need.

## 3. Objectives

### General objective

To develop a locally hosted web application that supports and organizes the day-to-day customer, vehicle, reservation, return, and billing operations of a car-rental business.

### Specific objectives

- Maintain searchable customer records and required identity-document images.
- Maintain vehicle specifications, rental rates, photos, and operational availability.
- Create reservations only for active customers and available vehicles.
- Calculate rental estimates from the rental period and the vehicle's daily rate.
- Track reservation fees, rental balances, additional charges, payments, and invoice balances using whole-peso billing amounts; reject decimal payment entries.
- Record vehicle returns, incoming mileage, inspection condition, and remarks.
- Apply Admin and Staff permissions to relevant screens and actions.
- Present operational metrics and period-based charts, and provide rental and payment CSV exports.
- Provide a local workflow that can be operated and maintained with XAMPP and MySQL/MariaDB.

## 4. Scope and Boundaries

### Included

- Sign-in, sign-out, session handling, and role-aware access.
- Customer directory and customer record forms.
- Vehicle fleet inventory, Admin vehicle maintenance, and availability status.
- Rental reservation creation, pickup confirmation, and lifecycle status.
- Vehicle return processing and return history.
- Invoice details, payment recording, payment history, and printable invoice views.
- Admin team-account management, billing settings, and full reports.
- Staff operational dashboard, Admin financial dashboard, searchable records, filters, and Admin-only CSV reports.
- Local Bataan/Philippines context, PHP currency formatting, and Asia/Manila timezone defaults.

### Outside the current implementation

- Customer-facing registration, booking, or account portal.
- Online payment processing, payment-provider reconciliation, or automatic refunds.
- Mobile-native application, GPS tracking, vehicle telematics, or remote fleet control.
- Insurance claims, accident case management, dispatch, or driver/service scheduling.
- A production multi-branch/cloud deployment configuration.
- Automatic background processing while the site is idle unless the optional Windows Task Scheduler job is configured.

## 5. Users and Access Control

Access follows least privilege while keeping Staff able to complete the customer-facing rental workflow. Staff navigation omits Reports, Team & Access, and Settings. Server-side page/action checks enforce the same restrictions; hiding a link is not the security boundary.

| Module or capability | Admin | Staff | Access decision |
| --- | --- | --- | --- |
| Sign in/out | Yes | Yes | Both use individual active accounts. |
| Dashboard / Overview | Full | Operational view | Both see rental, customer, and fleet counts/charts. Only Admin sees outstanding balances, collections charts, and rental amounts. |
| Customers | Add, view, edit, change status | Add, view, edit details | Staff cannot activate/deactivate customer accounts; only Admin can change customer status. |
| Vehicle fleet | View, add/edit, set eligible status | View/search only | Fleet editing/status changes are Admin-only; open reservations/rentals prevent conflicting changes. |
| Rentals | Create reservation, view, confirm eligible pickup | Same operational access | Both need this module to serve customers and hand over vehicles. |
| Returns | Process and view return records | Same operational access | Both need this module for vehicle check-in. |
| Billing | View invoices, record payments, print invoice views | Same operational access | Staff can record payments received during operations; payment-void/refund functions are not provided. |
| Reports and CSV exports | Full reports and downloads | No access | Admin-only because reports include financial summaries, customer rankings, and rental/payment exports. |
| Team & Access | Yes | No | Admin-only account and role administration. |
| Settings | Yes | No | Admin-only billing configuration. |

The dashboard queries and renders financial aggregates and rental amounts only for Admin. Staff retains operational dashboard charts for rental outcomes and fleet status. Reports are enforced as an Admin-only route, so the restriction also applies when a Staff user requests a report or CSV URL directly.

Customer status is also protected server-side: Staff-created customer records default to Active, while Staff edits preserve the saved status even if a request is altered. Admin alone can set Active or Inactive. This ensures customer eligibility changes are made by an authorized decision-maker.

The server also prevents an administrator from deactivating their own account or changing their own role, and prevents removal of the last active Admin.

## 6. Operational Workflow

### 6.1 Customer registration

Staff or Admin searches the customer directory. A new customer record requires identity and contact details, a date of birth confirming an age of at least 20, an address, customer photo, driver's license image, and secondary ID image. The accepted image formats are JPEG, PNG, or WebP, up to 5 MB per image. Existing document images can be retained when editing a record; missing required documents must be uploaded.

Inactive customers cannot be selected for a new reservation. Customer and eligibility checks are repeated on the server when the reservation is saved.

### 6.2 Fleet preparation

Admin adds or edits vehicle information, including plate, brand/model, vehicle type, year, color, transmission, fuel, seating capacity, mileage, rate, and optional photo. Admin can mark an idle vehicle Available or Maintenance. An open reservation or rental prevents conflicting status changes. Staff can search and inspect the fleet but cannot create or edit vehicles.

### 6.3 Reservation and rental calculation

An authorized user selects an active customer and an Available vehicle using searchable suggestion fields, then enters pickup and expected-return date/time. A suggestion must be explicitly selected; typed text alone is not treated as a valid customer or vehicle selection. The expected return must be later than pickup, and the new reservation cannot be created for a past pickup time.

The estimate uses 24-hour rental days, rounds partial days up, and applies a one-day minimum. On save, the server rechecks customer and vehicle eligibility within a database transaction, creates the Pending rental, changes the vehicle to Reserved, and creates a Reservation fee invoice. The configured fee is capped at the rental total.

### 6.4 Payment and pickup

Staff or Admin opens the invoice, records the amount actually received, selects Cash, Card, GCash, or Bank Transfer, and may enter a reference number. Partial payments are supported; a payment cannot exceed the remaining invoice balance. The invoice is marked Partial or Paid based on recorded payments.

Pickup can be confirmed only when the reservation-fee invoice is Paid, the customer is still Active, and the current time is from the scheduled pickup time through the one-hour grace period afterward. On confirmation the rental becomes Active, the vehicle becomes Rented, and a rental-balance invoice is generated when a balance remains.

### 6.5 Return and final charges

An Active or Late rental can be returned. The operator records incoming mileage, vehicle condition, optional remarks, and any approved damage fee. Incoming mileage cannot be below the vehicle's saved mileage. Completing the return records the return, completes the rental, updates vehicle mileage, and releases the vehicle as Available. Damage and configured late-return charges are recorded as invoice items associated with the rental.

### 6.6 No-shows and overdue rentals

Pending reservations more than one hour beyond their scheduled pickup are dismissed, and vehicles with no remaining open rental/reservation are released. Active rentals past their expected return time become Late. These maintenance transitions run when an authenticated page is opened. For updates while nobody is using the application, an administrator may configure the CLI-only `maintenance.php` script in Windows Task Scheduler to run periodically (the README recommends every five minutes).

Dismissal does not automatically refund a paid reservation fee. A business refund policy must be defined and handled outside automatic system behavior.

### 6.7 Billing, monitoring, and sign-out

Billing supports searching invoices, viewing line amount/payment history, recording partial or full payments, and printing invoice views. The dashboard shows operational counts to both roles; only Admin sees its financial summaries. Admin Reports provide six-month navigation, line/pie/column visualizations, and rental/payment CSV downloads. Staff has no Reports page or export access. Users should sign out when finished, especially on shared workstations.

## 7. Functional Requirements

The implemented application shall:

1. Authenticate active user accounts and expose application screens only to signed-in users, except sign-in.
2. Enforce Admin-only access to Reports and CSV exports, vehicle-management actions, team management, settings, and customer status changes.
3. Search customers and vehicles, and allow authorized users to add/edit customer details subject to validation.
4. Require active customers and available vehicles when creating reservations and recheck these conditions server-side.
5. Reject invalid rental dates and calculate a day-based estimate using the selected vehicle rate.
6. Create a Pending reservation, reserve its vehicle, and create its reservation-fee invoice as a single transactional workflow.
7. Require reservation-fee payment, active customer status, and a valid pickup window before activating a rental.
8. Record payment method, optional reference, amount, processor, and time, while maintaining invoice balances and status.
9. Record returns with condition and mileage checks, complete the rental, release the vehicle, and create applicable additional charges.
10. Maintain and display rental states Pending, Active, Late, Completed, and Dismissed, and vehicle states Available, Reserved, Rented, and Maintenance.
11. Present operational dashboard metrics to both roles; expose financial dashboard metrics, full Reports, and rental/payment CSV downloads only to Admin.
12. Allow Admins to manage team accounts and change reservation and late-fee settings.

## 8. Quality, Security, and Operational Requirements

- **Usability:** Consistent navigation, responsive page layouts, clear status labels, search/filter forms, flash messages, date/amount validation, dark/light theme, and printable invoice presentation.
- **Data integrity:** MySQL foreign keys and transactional workflows protect connected rental, invoice, return, and payment records. Rental/vehicle status transitions are managed by workflow rather than unrestricted user selection.
- **Input handling:** Server-side validation complements browser validation. Database interactions use PDO prepared statements. State-changing forms use CSRF tokens.
- **Authentication:** Passwords are stored as password hashes and checked with password verification. New or changed team passwords require at least eight characters and confirmation.
- **Authorization:** Role guards apply on the server; hidden navigation is not the sole protection. Reports and CSV exports, fleet edits, team administration, settings, and customer status changes are Admin-only. Staff dashboard queries and output omit the financial aggregate/amount fields.
- **Privacy:** Customer photos and identity documents are uploaded and stored as database blobs, not loose files in the project image folder. Access to image routes requires authentication and MIME type validation.
- **Maintainability:** Shared bootstrap/functions/actions support modular PHP pages; schema definitions are centralized in `database/schema.sql`.
- **Availability:** The application depends on the local Apache and MySQL/MariaDB services being available. Automatic lifecycle maintenance while idle requires scheduled CLI execution.

## 9. System Architecture and Technology

| Layer | Current implementation |
| --- | --- |
| User interface | HTML rendered by PHP, CSS in `assets/css/style.css`, JavaScript in `assets/js/app.js`; accessed in a browser |
| Application | PHP 8.1+ with session-based login, role checks, server-side validation, and PDO database access |
| Web server/runtime | Apache and PHP through XAMPP on a local Windows workstation |
| Data storage | MySQL or MariaDB database named `crms_db` by default |
| Local configuration | Database connection environment variables; timezone defaults to `Asia/Manila` |
| Export/output | Printable invoice views and Admin-only CSV rental/payment reports |

### Request flow

Browser request → `index.php` route and access checks → shared bootstrap and `includes/` helpers/actions → selected `modules/` page → PDO queries/transactions to MySQL/MariaDB → rendered HTML response.

The application keeps customer and vehicle image uploads in database columns. The replaceable app logo is a separate SVG file in the assets folder.

## 10. Application Modules

| Module | Purpose |
| --- | --- |
| Sign in | Verifies username/password for an active account and starts the authenticated session. |
| Dashboard / Overview | Shows shared operational counts and rental/fleet charts. Admin also sees financial metrics, collections charts, and rental amounts; Staff does not. |
| Customers | Both roles search, add, and edit customer details and required image documents. Only Admin can change Active/Inactive customer status. |
| Vehicle fleet | Shows vehicle details, photos, search/filter results, and availability. Admin has vehicle add/edit/status controls; Staff has read-only access. Fleet cards are paginated. |
| Rentals | Searches rentals by identifiers, customer, vehicle and plate; creates reservations, shows rental details, and supports eligible pickup confirmation. |
| Returns | Lists open rentals, records vehicle check-in, and displays recent return history. |
| Billing | Lists and filters invoices, records payments, displays payment history and balances, and provides a print view. |
| Reports | Admin-only metrics, charts, six-month navigation, and rental/payment CSV exports. Staff has no Reports navigation or route access. |
| Team & access | Admin-only account creation/editing, role/status management, and password changes. |
| Settings | Admin-only reservation-fee and per-day late-fee configuration. |

## 11. Database Design

The schema defines eight InnoDB tables:

| Table | Main responsibility and relationships |
| --- | --- |
| `tbl_user` | Admin/Staff profiles, unique usernames, password hashes, role, status, and timestamps. |
| `tbl_customer` | Customer identity/contact/address/status and document/photo blobs. |
| `tbl_car` | Plate, vehicle specifications, mileage, daily rate, photo, and fleet status. |
| `tbl_rental` | Customer, car, processing user, schedule, status, and total. Foreign keys link to `tbl_customer`, `tbl_car`, and `tbl_user`. |
| `tbl_return` | One return per rental, processing user, date, mileage out/in, condition, remarks, and damage fee. |
| `tbl_invoice` | One or more rental charges with amount, status, due date, and rental relationship. |
| `tbl_payment` | Payment amount, method, optional reference, processor, and timestamp; linked to an invoice and user. |
| `tbl_setting` | Key/value application billing settings, including reservation fee and late-fee-per-day. |

### Relationship summary

- One customer can have multiple rental records; each rental refers to one customer.
- One vehicle can be associated with multiple historical rentals; each rental refers to one vehicle.
- One rental can have multiple invoices and payments through invoices.
- A rental can have at most one return record (`tbl_return.rental_id` is unique).
- User records are referenced as the processor for rentals, returns, and payments.
- Foreign-key delete restrictions preserve operational history.

## 12. User Interface and Accessibility Features

- Sign-in screen with light/dark theme selection.
- Shared navigation, current-role/account display, location context, and logout.
- Searchable customer and available-vehicle reservation pickers with keyboard interaction and explicit selection validation.
- Search/filter controls for customer, fleet, rentals, returns, billing, and Admin team records.
- Fleet result paging and responsive vehicle cards with contained vehicle photography.
- Status pills for rental, invoice, customer, and fleet states.
- Staff operational dashboard charts and Admin dashboard/Reports charts with six-month previous/next controls. Staff does not see Reports, Team & Access, or Settings navigation.
- Theme-aware form controls, dropdowns, cards, metric symbols, and report symbols.
- Invoice print control and Admin-only downloadable CSV report actions.

## 13. Validation and Data Rules

- Customers must be at least 20; date of birth and contact details are validated on the server.
- Customer email, address, customer photo, driver's license, and secondary ID are required; existing uploaded documents are retained on edits unless replaced.
- Team forms require sex, date of birth, contact number, email, and username; password confirmation is required on creation and when a new password is entered during editing.
- Team passwords must be at least eight characters.
- Only Admin can change a customer's Active/Inactive status; Staff edits preserve the saved status, and Staff-created customers default to Active.
- Vehicle plates, enumerated vehicle attributes, mileage, seat capacity, year, and rate are validated.
- Reservation pickup must not be in the past; expected return must be later than pickup.
- The selected reservation customer must exist and remain Active; the selected vehicle must exist and remain Available at save time.
- Pickup requires a paid reservation fee, an Active customer, and the scheduled-to-one-hour-after pickup window.
- Return mileage cannot be below the saved vehicle mileage; charges and payments must be valid amounts and payments cannot exceed invoice balance.
- An open reservation/rental blocks conflicting vehicle status and edit operations.

## 14. Installation and Operation

1. Place the project under the XAMPP web root, for example `C:\xampp\htdocs\car_rental_management_system\kcart`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import `database/schema.sql` into MySQL/MariaDB using phpMyAdmin. The schema creates `crms_db` and its tables.
4. Configure the database connection if needed using `CRMS_DB_HOST`, `CRMS_DB_NAME`, `CRMS_DB_USER`, and `CRMS_DB_PASSWORD`. Defaults are localhost, `crms_db`, `root`, and a blank password.
5. For customer document uploads, configure PHP upload limits as documented in `README.md`.
6. Create an initial Admin account in a fresh database using the password-hash procedure in `README.md`. This repository does not seed a universal default credential.
7. Visit the local application URL, sign in, verify the assigned role, and follow this portfolio's workflow or the detailed [System Workflow Guide](./workflow-guide.html).

The current local test database contains fictional Bataan-oriented operational examples. Demo credentials belong to that local database and are not embedded by the schema; replace them before handling real information or making the server accessible beyond localhost.

## 15. Testing and Verification

The implementation has been exercised through targeted browser and local workflow checks during development. Previously documented checks include PHP syntax validation, whitespace/diff checks, role-based page-access smoke checks, search and form validation scenarios, and a retained end-to-end rental workflow. These checks are useful evidence but are not a claim of formal certification, exhaustive regression testing, or production readiness.

| Test area | Expected/verified behavior |
| --- | --- |
| Role access | Staff can use operational pages; Reports/exports, Team & Access, Settings, vehicle edits, and customer status changes are Admin-only. |
| Dashboard privacy | Staff sees operational charts/counts; financial summary, collections chart, and rental amounts are Admin-only. |
| Reservation selection | Customer and vehicle suggestions open on field interaction; form submission requires a selected suggestion. |
| Reservation eligibility | Inactive/nonexistent customer and unavailable vehicle are rejected server-side; rental is not created on rejected input. |
| Search | Rental/return lists can be searched using displayed vehicle identity details as well as rental/customer identifiers. |
| Rental billing lifecycle | Reservation fee, pickup, balance invoice, payment status, and return processing were exercised in a retained local QA workflow. |
| Return validation | Open rental status and mileage are checked before check-in; completed return updates rental and vehicle state. |
| Syntax and patch checks | PHP lint and `git diff --check` passed during targeted development validations. |

For a formal submission or production handoff, repeat the test cases against a fresh backup/restore copy and record tester, date, data setup, expected result, actual result, and evidence for every test. Do not delete shared operational data to conduct tests.

## 16. Limitations and Recommendations

### Current limitations

- The application is locally hosted and assumes a trusted workstation/network configuration.
- It does not charge payment cards or connect to payment services; staff record payments they have actually received.
- No-shows are dismissed and vehicles released, but the system does not automatically refund a reservation fee.
- Automatic overdue/no-show status updates require an authenticated page visit or scheduled maintenance task.
- Customer and vehicle photo/document blobs increase database-backup size and require careful access and retention management.
- The current implementation does not provide full audit history, multi-branch synchronization, or an online customer portal.

### Recommendations before real-world deployment

1. Replace local test credentials, create named accounts, and use strong unique passwords.
2. Configure database/PHP credentials securely; restrict Apache, MySQL, and phpMyAdmin to trusted access.
3. Define customer consent, identity-document access, retention/deletion, backup encryption, and restore procedures.
4. Define written refund, no-show, late-fee, damage-assessment, and dispute policies that match actual business practice.
5. Configure and monitor scheduled maintenance if status updates must continue while the application is idle.
6. Test database backups and restorations and retain off-device copies.
7. Add audit logging and deployment security controls before exposing the application to a wider network.
8. Have operational users perform acceptance testing with representative data before launch.

## 17. Project Files and Customization

| Path | Purpose |
| --- | --- |
| `index.php` | Request routing, authentication/access checks, and application entry point. |
| `config/db.php` | PDO database connection configuration. |
| `database/schema.sql` | Database and table definitions plus default billing settings. |
| `includes/` | Bootstrap, shared helpers, header/footer, and state-changing actions. |
| `modules/` | Login, dashboard, customer, vehicle, rental, return, billing, report, user, and settings pages. |
| `assets/css/style.css` | Interface layout, responsive styling, and themes. |
| `assets/js/app.js` | Theme control, searchable pickers, estimates, and client-side interactions. |
| `assets/images/logo.svg` | Replaceable application logo used by the interface and invoice view. |
| `docs/workflow-guide.html` | Detailed Admin/Staff workflow and asset guidance. |
| `README.md` | Installation, initial Admin setup, operational notes, and configuration. |

Customer photos, driver's licenses, secondary IDs, and vehicle photos are uploaded through application forms and stored in MySQL blobs. They are not loose image files in `assets/images/`. Keep the logo filename/path or update every reference if replacing it with a differently named asset.

## 18. Conclusion

Kcart provides a structured local workflow from customer and fleet preparation through reservation, payment, pickup, return, and invoice reconciliation. Its key controls are server-side role enforcement, server-side eligibility validation, workflow-managed vehicle availability, linked transactional records, and operational reporting. The system is suitable for local demonstration and further supervised evaluation; production use should follow the security, privacy, backup, policy, and acceptance-testing recommendations above.

## 19. Project Documentation References

- Kcart repository `README.md` — local installation and configuration.
- `docs/workflow-guide.html` — current role-specific operating procedures and branding guidance.
- `database/schema.sql` — implemented relational schema.
- `index.php`, `includes/`, `modules/`, and `assets/` — current application behavior and interface.
