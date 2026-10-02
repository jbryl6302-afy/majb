-- Blood Bank 2 Database Schema

CREATE DATABASE IF NOT EXISTS bloodbank2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bloodbank2_db;

CREATE TABLE IF NOT EXISTS Donors (
    donor_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    national_id VARCHAR(20) UNIQUE,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    bag_number VARCHAR(50) UNIQUE,
    age INT,
    weight DECIMAL(5,2),
    last_donation DATE,
    health_status VARCHAR(50) DEFAULT 'جيد',
    smoker ENUM('Yes','No') DEFAULT 'No',
    last_smoke_time DATETIME,
    prescreen_sample ENUM('Taken','NotTaken') DEFAULT 'NotTaken',
    is_eligible BOOLEAN DEFAULT TRUE,
    rejection_reason TEXT,
    hiv_screening ENUM('Negative','Positive') DEFAULT 'Negative',
    hepatitis_screening ENUM('Negative','Positive') DEFAULT 'Negative',
    syphilis_screening ENUM('Negative','Positive') DEFAULT 'Negative',
    malaria_screening ENUM('Negative','Positive') DEFAULT 'Negative',
    screening_status ENUM('Approved','Rejected','Pending') DEFAULT 'Pending',
    screened_at DATETIME,
    screened_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS StorageUnits (
    unit_id INT AUTO_INCREMENT PRIMARY KEY,
    unit_name VARCHAR(50) NOT NULL,
    location VARCHAR(100),
    capacity INT DEFAULT 100,
    current_temp DECIMAL(4,2) DEFAULT 4.00,
    humidity DECIMAL(4,2) DEFAULT 50.00,
    status ENUM('Active','Maintenance','Full') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin','Doctor','LabTechnician','StorageOfficer') NOT NULL,
    phone VARCHAR(20),
    hospital_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS BloodBags (
    bag_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT,
    qr_code VARCHAR(255) UNIQUE NOT NULL,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    volume_ml INT NOT NULL DEFAULT 450,
    donation_date DATETIME NOT NULL,
    expiry_date DATETIME NOT NULL,
    status ENUM('Available','Reserved','Used','Transferred','Expired','Discarded') DEFAULT 'Available',
    storage_unit_id INT,
    current_temp DECIMAL(4,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES Donors(donor_id) ON DELETE SET NULL,
    FOREIGN KEY (storage_unit_id) REFERENCES StorageUnits(unit_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS TestResults (
    test_id INT AUTO_INCREMENT PRIMARY KEY,
    bag_id INT UNIQUE,
    hiv_result ENUM('Negative','Positive','Pending') DEFAULT 'Pending',
    hepatitis_result ENUM('Negative','Positive','Pending') DEFAULT 'Pending',
    syphilis_result ENUM('Negative','Positive','Pending') DEFAULT 'Pending',
    malaria_result ENUM('Negative','Positive','Pending') DEFAULT 'Pending',
    overall_status ENUM('Approved','Rejected','Pending') DEFAULT 'Pending',
    test_date DATETIME,
    tested_by INT,
    FOREIGN KEY (bag_id) REFERENCES BloodBags(bag_id) ON DELETE CASCADE,
    FOREIGN KEY (tested_by) REFERENCES Users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS BloodRequests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT,
    patient_name VARCHAR(100),
    patient_blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units_needed INT NOT NULL DEFAULT 1,
    urgency ENUM('Normal','Urgent','Critical') DEFAULT 'Normal',
    status ENUM('Pending','Approved','Fulfilled','Cancelled') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fulfilled_date DATETIME,
    FOREIGN KEY (doctor_id) REFERENCES Users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS RequestAllocations (
    allocation_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT,
    bag_id INT,
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES BloodRequests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (bag_id) REFERENCES BloodBags(bag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS TrackingLog (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    bag_id INT,
    action VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    performed_by INT,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bag_id) REFERENCES BloodBags(bag_id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES Users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Predictions (
    prediction_id INT AUTO_INCREMENT PRIMARY KEY,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    predicted_units INT NOT NULL,
    confidence DECIMAL(5,2),
    prediction_for_month DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO StorageUnits (unit_name, location, capacity) VALUES
('وحدة أ - ثلاجة 1', 'دور المختبر 1', 100),
('وحدة ب - ثلاجة 2', 'دور المختبر 1', 80),
('وحدة ج - طوارئ', 'غرفة الطوارئ', 50);

INSERT IGNORE INTO Users (full_name, email, password_hash, role, phone) VALUES
('مدير النظام', 'admin@bloodbank2.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', '0123456789'),
('د. أحمد حسن', 'ahmed@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Doctor', '0111111111'),
('فني المختبر سامي', 'sami@bloodbank2.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'LabTechnician', '0122222222'),
('أخصائي التخزين عمر', 'omar@bloodbank2.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'StorageOfficer', '0133333333');


-- ==================== 🚑 نظام الإنقاذ الذكي لفائض الدم ====================

CREATE TABLE IF NOT EXISTS Facilities (
    facility_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('BloodBank','Hospital') NOT NULL,
    city VARCHAR(100),
    distance_km DECIMAL(6,2) DEFAULT 0,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS FacilityNeeds (
    need_id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units_needed INT DEFAULT 0,
    urgency ENUM('Normal','Urgent','Critical') DEFAULT 'Normal',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES Facilities(facility_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS DonorScreeningLog (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT,
    smoker ENUM('Yes','No') DEFAULT 'No',
    hiv_result ENUM('Negative','Positive') DEFAULT 'Negative',
    hepatitis_result ENUM('Negative','Positive') DEFAULT 'Negative',
    syphilis_result ENUM('Negative','Positive') DEFAULT 'Negative',
    malaria_result ENUM('Negative','Positive') DEFAULT 'Negative',
    overall_status ENUM('Approved','Rejected') DEFAULT 'Approved',
    screened_by INT,
    notes TEXT,
    screened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES Donors(donor_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    bag_id INT,
    to_facility_id INT,
    priority_score DECIMAL(5,1) DEFAULT 0,
    status ENUM('Suggested','Approved','Rejected','InTransit','Completed','Cancelled') DEFAULT 'Suggested',
    qr_token VARCHAR(255),
    notes TEXT,
    suggested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decided_by INT,
    decided_at DATETIME,
    FOREIGN KEY (bag_id) REFERENCES BloodBags(bag_id) ON DELETE CASCADE,
    FOREIGN KEY (to_facility_id) REFERENCES Facilities(facility_id) ON DELETE SET NULL,
    FOREIGN KEY (decided_by) REFERENCES Users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO Facilities (facility_id, name, type, city, distance_km, phone) VALUES
(1, 'مستشفى الأمل العام', 'Hospital', 'الخرطوم', 3.5, '0111000111'),
(2, 'مستشفى الشفاء التخصصي', 'Hospital', 'أم درمان', 8.2, '0122000222'),
(3, 'بنك دم المنطقة الوسطى', 'BloodBank', 'بحري', 12.0, '0133000333');

INSERT IGNORE INTO FacilityNeeds (facility_id, blood_type, units_needed, urgency) VALUES
(1, 'O-', 4, 'Critical'), (1, 'A+', 2, 'Urgent'),
(2, 'B+', 5, 'Urgent'),  (2, 'O+', 3, 'Normal'),
(3, 'AB-', 2, 'Normal'), (3, 'A-', 6, 'Critical');
