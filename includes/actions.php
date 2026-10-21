<?php
declare(strict_types=1);

function process_action(PDO $pdo): void
{
    require_post();
    $submittedAction = $_POST['action'] ?? '';
    $action = is_string($submittedAction) ? $submittedAction : '';

    try {
        if ($action === '') {
            throw new InvalidArgumentException('Invalid form action.');
        }
        switch ($action) {
            case 'login':
                $username = post_string('username', 50);
                $password = post_password('password');
                $statement = $pdo->prepare("SELECT * FROM tbl_user WHERE username = ? AND user_status = 'Active' LIMIT 1");
                $statement->execute([$username]);
                $user = $statement->fetch();
                if (!$user || !password_verify($password, $user['password'])) {
                    throw new InvalidArgumentException('The username or password is incorrect.');
                }
                session_regenerate_id(true);
                unset($user['password']);
                $_SESSION['user'] = $user;
                flash('Welcome back, ' . $user['first_name'] . '.');
                redirect('dashboard');

            case 'logout':
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }
                session_destroy();
                redirect('login');

            case 'customer_save':
                require_login();
                $id = post_record_id('customer_id');
                $existingCustomerStatus = 'Active';
                if ($id > 0) {
                    $existingCustomer = $pdo->prepare('SELECT customer_status FROM tbl_customer WHERE customer_id=?');
                    $existingCustomer->execute([$id]);
                    $savedCustomerStatus = $existingCustomer->fetchColumn();
                    if ($savedCustomerStatus === false) {
                        throw new InvalidArgumentException('Customer record not found.');
                    }
                    $existingCustomerStatus = (string) $savedCustomerStatus;
                }
                $customerStatus = current_user()['role'] === 'Admin'
                    ? post_enum('customer_status', ['Active', 'Inactive'])
                    : $existingCustomerStatus;
                $values = [
                    post_string('first_name', 50),
                    post_string('middle_name', 50, false),
                    post_string('last_name', 50),
                    post_enum('sex', ['Male', 'Female', 'Other']),
                    post_adult_date('date_of_birth', 20),
                    post_phone('contact_no'),
                    post_email('email', true),
                    post_string('address', 255) ?: null,
                    $customerStatus,
                ];
                $uploads = [
                    'driver_license' => uploaded_blob('driver_license'),
                    'secondary_id' => uploaded_blob('secondary_id'),
                    'customer_photo' => uploaded_blob('customer_photo'),
                ];
                if ($id === 0) {
                    foreach (['customer_photo' => 'Customer photo', 'driver_license' => "Driver's license", 'secondary_id' => 'Secondary ID'] as $field => $label) {
                        if ($uploads[$field] === null) {
                            throw new InvalidArgumentException("Upload the required {$label}.");
                        }
                    }
                }
                if ($id > 0) {
                    $existingDocuments = $pdo->prepare('SELECT customer_photo IS NOT NULL AS has_customer_photo,driver_license IS NOT NULL AS has_driver_license,secondary_id IS NOT NULL AS has_secondary_id FROM tbl_customer WHERE customer_id=?');
                    $existingDocuments->execute([$id]);
                    $savedDocuments = $existingDocuments->fetch();
                    foreach ([
                        'customer_photo' => ['Customer photo', 'has_customer_photo'],
                        'driver_license' => ["Driver's license", 'has_driver_license'],
                        'secondary_id' => ['Secondary ID', 'has_secondary_id'],
                    ] as $field => [$label, $savedField]) {
                        if ($uploads[$field] === null && empty($savedDocuments[$savedField])) {
                            throw new InvalidArgumentException("Upload the required {$label}.");
                        }
                    }
                    $statement = $pdo->prepare('UPDATE tbl_customer SET first_name=?, middle_name=?, last_name=?, sex=?, date_of_birth=?, contact_no=?, email=?, address=?, customer_status=? WHERE customer_id=?');
                    $statement->execute([...$values, $id]);
                    foreach ($uploads as $column => $blob) {
                        if ($blob !== null) {
                            $pdo->prepare("UPDATE tbl_customer SET {$column}=? WHERE customer_id=?")->execute([$blob, $id]);
                        }
                    }
                } else {
                    $statement = $pdo->prepare('INSERT INTO tbl_customer (first_name,middle_name,last_name,sex,date_of_birth,contact_no,email,address,customer_status,driver_license,secondary_id,customer_photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                    $statement->execute([...$values, $uploads['driver_license'], $uploads['secondary_id'], $uploads['customer_photo']]);
                }
                flash($id > 0 ? 'Customer details updated.' : 'Customer added.');
                redirect('customers');

            case 'vehicle_save':
                require_admin();
                $id = post_record_id('car_id');
                $values = [
                    strtoupper(post_string('plate_no', 20)),
                    post_string('brand', 50),
                    post_string('model', 50),
                    post_enum('car_type', ['Sedan', 'SUV', 'Hatchback', 'MPV', 'Van', 'Pickup']),
                    (string) post_int('year', 1980),
                    post_string('color', 30),
                    post_enum('transmission_type', ['Automatic', 'Manual']),
                    post_enum('fuel_type', ['Gasoline', 'Diesel', 'Electric', 'Hybrid']),
                    post_int('seat_cap', 1, 99),
                    post_int('mileage', 0, 2147483647),
                    post_amount('daily_rate', 0.01),
                ];
                if (!preg_match('/^[A-Z0-9][A-Z0-9 -]{1,19}$/', $values[0])) {
                    throw new InvalidArgumentException('Enter a valid plate number using letters, numbers, spaces, or hyphens.');
                }
                if ((int) $values[4] > (int) date('Y') + 1) {
                    throw new InvalidArgumentException('Enter a valid vehicle year.');
                }
                if ((int) $values[8] > 99) {
                    throw new InvalidArgumentException('Seat capacity must be between 1 and 99.');
                }
                $photo = uploaded_blob('car_photo');
                if ($id > 0) {
                    $pdo->beginTransaction();
                    $vehicleLock = $pdo->prepare('SELECT car_status FROM tbl_car WHERE car_id=? FOR UPDATE');
                    $vehicleLock->execute([$id]);
                    $existingStatus = $vehicleLock->fetchColumn();
                    if ($existingStatus === false) {
                        throw new InvalidArgumentException('Vehicle not found.');
                    }
                    if (in_array($existingStatus, ['Reserved', 'Rented'], true)) {
                        throw new InvalidArgumentException('Vehicle details cannot be changed while it is reserved or rented.');
                    }
                    $statement = $pdo->prepare('UPDATE tbl_car SET plate_no=?,brand=?,model=?,car_type=?,year=?,color=?,transmission_type=?,fuel_type=?,seat_cap=?,mileage=?,daily_rate=? WHERE car_id=?');
                    $statement->execute([...$values, $id]);
                    if ($photo !== null) {
                        $pdo->prepare('UPDATE tbl_car SET car_photo=? WHERE car_id=?')->execute([$photo, $id]);
                    }
                    $pdo->commit();
                } else {
                    $statement = $pdo->prepare("INSERT INTO tbl_car (plate_no,brand,model,car_type,year,color,transmission_type,fuel_type,seat_cap,mileage,daily_rate,car_photo,car_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Available')");
                    $statement->execute([...$values, $photo]);
                }
                flash($id > 0 ? 'Vehicle details updated.' : 'Vehicle added to the fleet.');
                redirect('vehicles');

            case 'vehicle_status':
                require_admin();
                $carId = post_int('car_id', 1);
                $status = post_enum('car_status', ['Available', 'Maintenance']);
                $pdo->beginTransaction();
                $vehicleLock = $pdo->prepare('SELECT car_id FROM tbl_car WHERE car_id=? FOR UPDATE');
                $vehicleLock->execute([$carId]);
                if (!$vehicleLock->fetchColumn()) {
                    throw new InvalidArgumentException('Vehicle not found.');
                }
                $active = $pdo->prepare("SELECT COUNT(*) FROM tbl_rental WHERE car_id=? AND rental_status IN ('Pending','Active','Late')");
                $active->execute([$carId]);
                if ((int) $active->fetchColumn() > 0) {
                    throw new InvalidArgumentException('A vehicle with an open rental or reservation cannot change fleet status.');
                }
                $pdo->prepare('UPDATE tbl_car SET car_status=? WHERE car_id=?')->execute([$status, $carId]);
                $pdo->commit();
                flash('Vehicle status updated.');
                redirect('vehicles');

            case 'rental_save':
                require_login();
                $customerId = post_int('customer_id', 1);
                $carId = post_int('car_id', 1);
                $start = post_date('start_date');
                $expected = post_date('expected_return_date');
                if (strtotime($expected) <= strtotime($start)) {
                    throw new InvalidArgumentException('The return date must be later than the pickup date.');
                }
                if (strtotime($start) < time() - 300) {
                    throw new InvalidArgumentException('A new reservation cannot start in the past.');
                }
                $pdo->beginTransaction();
                $customer = $pdo->prepare("SELECT customer_id FROM tbl_customer WHERE customer_id=? AND customer_status='Active' FOR UPDATE");
                $customer->execute([$customerId]);
                if (!$customer->fetchColumn()) {
                    throw new InvalidArgumentException('Select an active customer.');
                }
                $car = $pdo->prepare("SELECT * FROM tbl_car WHERE car_id=? AND car_status='Available' FOR UPDATE");
                $car->execute([$carId]);
                $vehicle = $car->fetch();
                if (!$vehicle) {
                    throw new InvalidArgumentException('That vehicle is no longer available.');
                }
                $days = max(1, (int) ceil((strtotime($expected) - strtotime($start)) / 86400));
                $total = round($days * (float) $vehicle['daily_rate']);
                if ($total > 99999999.99) {
                    throw new InvalidArgumentException('The rental total exceeds the maximum supported invoice amount.');
                }
                $reservationFee = min($total, max(0, (int) round((float) setting($pdo, 'reservation_fee', '1000.00'))));
                $pdo->prepare("INSERT INTO tbl_rental (customer_id,car_id,processed_by,start_date,expected_return_date,rental_status,total_amount) VALUES (?,?,?,?,?,'Pending',?)")
                    ->execute([$customerId, $carId, current_user()['user_id'], $start, $expected, $total]);
                $rentalId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO tbl_invoice (rental_id,description,invoice_amount,invoice_status,due_date) VALUES (?,'Reservation fee',?,?,DATE(?))")
                    ->execute([$rentalId, $reservationFee, $reservationFee > 0 ? 'Unpaid' : 'Paid', $start]);
                $pdo->prepare("UPDATE tbl_car SET car_status='Reserved' WHERE car_id=?")->execute([$carId]);
                $pdo->commit();
                flash('Reservation created. Collect the reservation invoice before vehicle pickup.');
                redirect('rentals');

            case 'rental_pickup':
                require_login();
                $rentalId = post_int('rental_id', 1);
                $pdo->beginTransaction();
                $statement = $pdo->prepare("SELECT r.*, i.invoice_id, i.invoice_status FROM tbl_rental r JOIN tbl_invoice i ON i.rental_id=r.rental_id AND i.description='Reservation fee' WHERE r.rental_id=? AND r.rental_status='Pending' FOR UPDATE");
                $statement->execute([$rentalId]);
                $rental = $statement->fetch();
                if (!$rental || $rental['invoice_status'] !== 'Paid') {
                    throw new InvalidArgumentException('Pay the reservation fee before confirming pickup.');
                }
                $customerStatus = $pdo->prepare("SELECT customer_status FROM tbl_customer WHERE customer_id=? FOR UPDATE");
                $customerStatus->execute([(int) $rental['customer_id']]);
                if ($customerStatus->fetchColumn() !== 'Active') {
                    throw new InvalidArgumentException('Reactivate the customer account before confirming vehicle pickup.');
                }
                $now = time();
                if ($now < strtotime($rental['start_date']) || $now > strtotime($rental['start_date']) + 3600) {
                    throw new InvalidArgumentException('Pickup can be confirmed only from the scheduled time through the one-hour grace period.');
                }
                $pdo->prepare("UPDATE tbl_rental SET rental_status='Active' WHERE rental_id=?")->execute([$rentalId]);
                $pdo->prepare("UPDATE tbl_car SET car_status='Rented' WHERE car_id=?")->execute([$rental['car_id']]);
                $pdo->prepare("INSERT INTO tbl_invoice (rental_id,description,invoice_amount,invoice_status,due_date) SELECT ?, 'Rental balance', GREATEST(? - COALESCE(SUM(invoice_amount),0),0), 'Unpaid', CURDATE() FROM tbl_invoice WHERE rental_id=?")
                    ->execute([$rentalId, $rental['total_amount'], $rentalId]);
                $pdo->prepare("DELETE FROM tbl_invoice WHERE rental_id=? AND description='Rental balance' AND invoice_amount=0")->execute([$rentalId]);
                $pdo->commit();
                flash('Pickup confirmed and the rental balance invoice was created.');
                redirect('rentals');

            case 'return_save':
                require_login();
                $rentalId = post_int('rental_id', 1);
                $mileageIn = post_int('mileage_in', 0, 2147483647);
                $condition = post_enum('vehicle_condition', ['Excellent', 'Good', 'Fair', 'Damaged']);
                $damageFee = post_whole_amount('damage_fee', 0);
                $remarks = post_string('remarks', 255, false);
                $pdo->beginTransaction();
                $statement = $pdo->prepare("SELECT r.*, c.mileage, c.car_id FROM tbl_rental r JOIN tbl_car c ON c.car_id=r.car_id WHERE r.rental_id=? AND r.rental_status IN ('Active','Late') FOR UPDATE");
                $statement->execute([$rentalId]);
                $rental = $statement->fetch();
                if (!$rental) {
                    throw new InvalidArgumentException('Select an active or late rental to return.');
                }
                if ($mileageIn < (int) $rental['mileage']) {
                    throw new InvalidArgumentException('Returned mileage cannot be lower than the vehicle’s last recorded mileage.');
                }
                $now = date('Y-m-d H:i:s');
                $pdo->prepare('INSERT INTO tbl_return (rental_id,processed_by,return_date,mileage_out,mileage_in,damage_fee,vehicle_condition,remarks) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$rentalId, current_user()['user_id'], $now, $rental['mileage'], $mileageIn, $damageFee, $condition, $remarks ?: null]);
                $pdo->prepare("UPDATE tbl_rental SET rental_status='Completed' WHERE rental_id=?")->execute([$rentalId]);
                $pdo->prepare("UPDATE tbl_car SET car_status='Available', mileage=? WHERE car_id=?")->execute([$mileageIn, $rental['car_id']]);
                if ($damageFee > 0) {
                    $pdo->prepare("INSERT INTO tbl_invoice (rental_id,description,invoice_amount,invoice_status,due_date) VALUES (?,'Damage fee',?,'Unpaid',CURDATE())")->execute([$rentalId, $damageFee]);
                }
                $lateRate = max(0, (int) round((float) setting($pdo, 'late_fee_per_day', '0.00')));
                $lateSeconds = strtotime($now) - strtotime($rental['expected_return_date']);
                if ($lateRate > 0 && $lateSeconds > 0) {
                    $lateDays = (int) ceil($lateSeconds / 86400);
                    $lateAmount = $lateDays * $lateRate;
                    if ($lateAmount > 99999999.99) {
                        throw new InvalidArgumentException('The late fee exceeds the maximum supported invoice amount.');
                    }
                    $pdo->prepare("INSERT INTO tbl_invoice (rental_id,description,invoice_amount,invoice_status,due_date) VALUES (?,'Late return fee',?,'Unpaid',CURDATE())")
                        ->execute([$rentalId, $lateAmount]);
                }
                $pdo->commit();
                flash('Return recorded. Vehicle mileage and availability were updated.');
                redirect('returns');

            case 'payment_save':
                require_login();
                $invoiceId = post_int('invoice_id', 1);
                $method = post_enum('pay_method', ['Cash', 'Card', 'GCash', 'BankTransfer']);
                $reference = post_string('reference_no', 100, false);
                $amount = post_whole_amount('pay_amount');
                $pdo->beginTransaction();
                $invoiceQuery = $pdo->prepare('SELECT invoice_id, invoice_amount, invoice_status FROM tbl_invoice WHERE invoice_id=? FOR UPDATE');
                $invoiceQuery->execute([$invoiceId]);
                $invoice = $invoiceQuery->fetch();
                if (!$invoice || $invoice['invoice_status'] === 'Paid') {
                    throw new InvalidArgumentException('Select an outstanding invoice.');
                }
                $paidQuery = $pdo->prepare('SELECT COALESCE(SUM(pay_amount),0) FROM tbl_payment WHERE invoice_id=?');
                $paidQuery->execute([$invoiceId]);
                $balance = round((float) $invoice['invoice_amount'] - (float) $paidQuery->fetchColumn(), 2);
                if (abs($balance - round($balance)) >= 0.001) {
                    throw new InvalidArgumentException('This invoice has a fractional balance and cannot be paid using whole-peso payments. Contact an administrator to adjust it.');
                }
                if ($amount > $balance + 0.001) {
                    throw new InvalidArgumentException('Payment cannot exceed the remaining invoice balance of ' . money_compact($balance) . '.');
                }
                $pdo->prepare('INSERT INTO tbl_payment (invoice_id,processed_by,pay_method,reference_no,pay_amount) VALUES (?,?,?,?,?)')
                    ->execute([$invoiceId, current_user()['user_id'], $method, $reference ?: null, $amount]);
                $newBalance = round($balance - $amount, 2);
                $status = $newBalance <= 0.001 ? 'Paid' : 'Partial';
                $pdo->prepare('UPDATE tbl_invoice SET invoice_status=? WHERE invoice_id=?')->execute([$status, $invoiceId]);
                $pdo->commit();
                flash('Payment recorded. Invoice status: ' . $status . '.');
                redirect('billing');

            case 'user_save':
                require_admin();
                $id = post_record_id('user_id');
                $firstName = post_string('first_name', 50);
                $middleName = post_string('middle_name', 50, false) ?: null;
                $lastName = post_string('last_name', 50);
                $sex = post_enum('sex', ['Male', 'Female', 'Other']);
                $contact = post_phone('contact_no');
                $email = post_email('email', true);
                $role = post_enum('role', ['Admin', 'Staff']);
                $username = post_string('username', 50);
                $status = post_enum('user_status', ['Active', 'Inactive']);
                $dateOfBirth = post_optional_date('date_of_birth');
                if ($dateOfBirth === null) {
                    throw new InvalidArgumentException('Enter the team member’s date of birth.');
                }
                $password = post_password('password', $id === 0, 8);
                $confirmPassword = post_password('confirm_password', $id === 0, 8);
                if ($password !== $confirmPassword) {
                    throw new InvalidArgumentException('Password and confirm password must match.');
                }
                if ($id > 0) {
                    $existingUserQuery = $pdo->prepare('SELECT role,user_status FROM tbl_user WHERE user_id=?');
                    $existingUserQuery->execute([$id]);
                    $existingUser = $existingUserQuery->fetch();
                    if (!$existingUser) {
                        throw new InvalidArgumentException('User account not found.');
                    }
                    if ($id === (int) current_user()['user_id'] && ($status === 'Inactive' || $role !== current_user()['role'])) {
                        throw new InvalidArgumentException('You cannot deactivate or change the role of your own account.');
                    }
                    if ($existingUser['role'] === 'Admin' && $existingUser['user_status'] === 'Active' && ($role !== 'Admin' || $status !== 'Active')) {
                        $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM tbl_user WHERE role='Admin' AND user_status='Active'")->fetchColumn();
                        if ($activeAdmins <= 1) {
                            throw new InvalidArgumentException('At least one active administrator account must remain.');
                        }
                    }
                    if ($password !== '') {
                        $statement = $pdo->prepare('UPDATE tbl_user SET first_name=?,middle_name=?,last_name=?,sex=?,date_of_birth=?,contact_no=?,email=?,role=?,username=?,user_status=?,password=? WHERE user_id=?');
                        $statement->execute([$firstName,$middleName,$lastName,$sex,$dateOfBirth,$contact,$email,$role,$username,$status,password_hash($password, PASSWORD_DEFAULT),$id]);
                    } else {
                        $statement = $pdo->prepare('UPDATE tbl_user SET first_name=?,middle_name=?,last_name=?,sex=?,date_of_birth=?,contact_no=?,email=?,role=?,username=?,user_status=? WHERE user_id=?');
                        $statement->execute([$firstName,$middleName,$lastName,$sex,$dateOfBirth,$contact,$email,$role,$username,$status,$id]);
                    }
                    if ($id === (int) current_user()['user_id']) {
                        $_SESSION['user'] = array_merge(current_user(), [
                            'first_name' => $firstName,
                            'middle_name' => $middleName,
                            'last_name' => $lastName,
                            'date_of_birth' => $dateOfBirth,
                            'contact_no' => $contact,
                            'email' => $email,
                            'role' => $role,
                            'username' => $username,
                            'user_status' => $status,
                        ]);
                    }
                } else {
                    $statement = $pdo->prepare('INSERT INTO tbl_user (first_name,middle_name,last_name,sex,date_of_birth,contact_no,email,role,username,user_status,password) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $statement->execute([$firstName,$middleName,$lastName,$sex,$dateOfBirth,$contact,$email,$role,$username,$status,password_hash($password, PASSWORD_DEFAULT)]);
                }
                flash($id > 0 ? 'User account updated.' : 'User account created.');
                redirect('users');

            case 'settings_save':
                require_admin();
                $reservationFee = post_whole_amount('reservation_fee', 0);
                $lateFee = post_whole_amount('late_fee_per_day', 0);
                $pdo->beginTransaction();
                $statement = $pdo->prepare('INSERT INTO tbl_setting (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
                $statement->execute(['reservation_fee', (string) $reservationFee]);
                $statement->execute(['late_fee_per_day', (string) $lateFee]);
                $pdo->commit();
                flash('Billing settings saved.');
                redirect('settings');

            default:
                http_response_code(400);
                exit('Unknown action.');
        }
    } catch (InvalidArgumentException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash($exception->getMessage(), 'error');
        $returnPage = match ($action) {
            'login' => 'login',
            'customer_save' => 'customers',
            'vehicle_save', 'vehicle_status' => 'vehicles',
            'rental_save', 'rental_pickup' => 'rentals',
            'return_save' => 'returns',
            'payment_save' => 'billing',
            'user_save' => 'users',
            'settings_save' => 'settings',
            default => 'dashboard',
        };
        redirect($returnPage, $action === 'rental_save' ? ['new' => 1] : []);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            flash('That value is already in use, or the selected record is still referenced.', 'error');
        } else {
            error_log($exception->getMessage());
            flash('The change could not be saved. Check the data and try again.', 'error');
        }
        $returnPage = match ($action) {
            'customer_save' => 'customers',
            'vehicle_save', 'vehicle_status' => 'vehicles',
            'rental_save', 'rental_pickup' => 'rentals',
            'return_save' => 'returns',
            'payment_save' => 'billing',
            'user_save' => 'users',
            'settings_save' => 'settings',
            default => 'dashboard',
        };
        redirect($returnPage, $action === 'rental_save' ? ['new' => 1] : []);
    }
}
