-- ============================================================
-- FIX: إضافة جداول نظام الإنقاذ (Rescue) المفقودة لقاعدة bloodbank2_db
-- السبب: الخطأ "Table 'bloodbank2_db.transfers' doesn't exist"
-- التشغيل: من phpMyAdmin ← اختر قاعدة bloodbank2_db ← تبويب SQL والصق الملف كاملاً
-- ============================================================

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

-- بيانات الجهات الافتراضية
INSERT IGNORE INTO Facilities (facility_id, name, type, city, distance_km, phone) VALUES
(1, 'مستشفى الأمل العام', 'Hospital', 'الخرطوم', 3.5, '0111000111'),
(2, 'مستشفى الشفاء التخصصي', 'Hospital', 'أم درمان', 8.2, '0122000222'),
(3, 'بنك دم المنطقة الوسطى', 'BloodBank', 'بحري', 12.0, '0133000333');
