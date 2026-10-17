CREATE DATABASE IF NOT EXISTS crms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crms_db;

CREATE TABLE IF NOT EXISTS tbl_user (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    sex ENUM('Male','Female','Other') NOT NULL,
    date_of_birth DATE NULL,
    contact_no VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    role ENUM('Admin','Staff') NOT NULL DEFAULT 'Staff',
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    sex ENUM('Male','Female','Other') NOT NULL,
    date_of_birth DATE NULL,
    contact_no VARCHAR(20) NOT NULL,
    email VARCHAR(100) NULL,
    address VARCHAR(255) NULL,
    driver_license MEDIUMBLOB NULL,
    secondary_id MEDIUMBLOB NULL,
    customer_photo MEDIUMBLOB NULL,
    customer_status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_name (last_name, first_name),
    INDEX idx_customer_status (customer_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_car (
    car_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_no VARCHAR(20) NOT NULL UNIQUE,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    car_type ENUM('Sedan','SUV','Hatchback','MPV','Van','Pickup') NOT NULL,
    year YEAR NOT NULL,
    color VARCHAR(30) NOT NULL,
    transmission_type ENUM('Automatic','Manual') NOT NULL,
    fuel_type ENUM('Gasoline','Diesel','Electric','Hybrid') NOT NULL,
    seat_cap INT NOT NULL,
    mileage INT NOT NULL DEFAULT 0,
    daily_rate DECIMAL(10,2) NOT NULL,
    car_photo MEDIUMBLOB NULL,
    car_status ENUM('Available','Reserved','Rented','Maintenance') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_car_status (car_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_rental (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    car_id INT NOT NULL,
    processed_by INT NOT NULL,
    start_date DATETIME NOT NULL,
    expected_return_date DATETIME NOT NULL,
    rental_status ENUM('Pending','Active','Late','Completed','Dismissed') NOT NULL DEFAULT 'Pending',
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rental_customer FOREIGN KEY (customer_id) REFERENCES tbl_customer(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rental_car FOREIGN KEY (car_id) REFERENCES tbl_car(car_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rental_user FOREIGN KEY (processed_by) REFERENCES tbl_user(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_rental_status_dates (rental_status, start_date, expected_return_date),
    INDEX idx_rental_customer (customer_id),
    INDEX idx_rental_car (car_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_return (
    return_id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL UNIQUE,
    processed_by INT NOT NULL,
    return_date DATETIME NOT NULL,
    mileage_out INT NOT NULL,
    mileage_in INT NOT NULL,
    damage_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    vehicle_condition VARCHAR(100) NOT NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_rental FOREIGN KEY (rental_id) REFERENCES tbl_rental(rental_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_return_user FOREIGN KEY (processed_by) REFERENCES tbl_user(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_invoice (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    invoice_amount DECIMAL(10,2) NOT NULL,
    invoice_status ENUM('Unpaid','Partial','Paid') NOT NULL DEFAULT 'Unpaid',
    due_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_rental FOREIGN KEY (rental_id) REFERENCES tbl_rental(rental_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_invoice_status (invoice_status),
    INDEX idx_invoice_rental (rental_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    processed_by INT NOT NULL,
    pay_method ENUM('Cash','Card','GCash','BankTransfer') NOT NULL,
    reference_no VARCHAR(100) NULL,
    pay_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES tbl_invoice(invoice_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_payment_user FOREIGN KEY (processed_by) REFERENCES tbl_user(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_payment_invoice (invoice_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbl_setting (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO tbl_setting (setting_key, setting_value) VALUES
    ('reservation_fee', '1000.00'),
    ('late_fee_per_day', '0.00'),
    ('currency', 'PHP');
