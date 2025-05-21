-- HealthCare Appointment System Database Schema (SQLite Version)

-- Users Table - Stores all users (patients, doctors, admins)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('patient', 'doctor', 'admin')),
    phone TEXT,
    address TEXT,
    date_of_birth TEXT,
    gender TEXT CHECK(gender IN ('male', 'female', 'other')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor Details Table - Additional info for doctors
CREATE TABLE IF NOT EXISTS doctor_details (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    specialization TEXT NOT NULL,
    qualification TEXT NOT NULL,
    experience_years INTEGER,
    consultation_fee REAL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Doctor Availability Table
CREATE TABLE IF NOT EXISTS doctor_availability (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    doctor_id INTEGER NOT NULL,
    day_of_week TEXT NOT NULL CHECK(day_of_week IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')),
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    is_available INTEGER DEFAULT 1,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(doctor_id, day_of_week, start_time)
);

-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_date TEXT NOT NULL,
    appointment_time TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'confirmed', 'cancelled', 'completed')),
    symptoms TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Medical Records Table
CREATE TABLE IF NOT EXISTS medical_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id INTEGER NOT NULL,
    doctor_id INTEGER NOT NULL,
    appointment_id INTEGER,
    diagnosis TEXT NOT NULL,
    notes TEXT,
    treatment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Prescriptions Table
CREATE TABLE IF NOT EXISTS prescriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    medical_record_id INTEGER NOT NULL,
    medication_name TEXT NOT NULL,
    dosage TEXT NOT NULL,
    frequency TEXT NOT NULL,
    duration TEXT NOT NULL,
    instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role, phone)
VALUES ('admin', '$2y$10$8Y5ORDEYUaXBRZdDCCY2wuZVfgfLbUxUiKZAGQj.tRfFO/PqW1xj2', 'admin@healthcare.com', 'System', 'Administrator', 'admin', '1234567890');
-- Default password is 'admin123' (hashed with bcrypt)

-- Insert sample doctor
INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role, phone, gender)
VALUES ('doctor', '$2y$10$8Y5ORDEYUaXBRZdDCCY2wuZVfgfLbUxUiKZAGQj.tRfFO/PqW1xj2', 'doctor@example.com', 'John', 'Smith', 'doctor', '5551234567', 'male');
-- Default password is 'admin123' (hashed with bcrypt)

-- Insert doctor details
INSERT OR IGNORE INTO doctor_details (user_id, specialization, qualification, experience_years, consultation_fee)
VALUES (2, 'Cardiology', 'MD, FACC - Johns Hopkins University', 10, 150.00);

-- Insert sample patient
INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role, phone, gender)
VALUES ('patient', '$2y$10$8Y5ORDEYUaXBRZdDCCY2wuZVfgfLbUxUiKZAGQj.tRfFO/PqW1xj2', 'patient@example.com', 'Jane', 'Doe', 'patient', '5557654321', 'female');
-- Default password is 'admin123' (hashed with bcrypt)

-- Insert sample doctor availability (Doctor ID 2)
INSERT OR IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available)
VALUES 
(2, 'Monday', '09:00:00', '17:00:00', 1),
(2, 'Tuesday', '09:00:00', '17:00:00', 1),
(2, 'Wednesday', '09:00:00', '17:00:00', 1),
(2, 'Thursday', '09:00:00', '17:00:00', 1),
(2, 'Friday', '09:00:00', '15:00:00', 1);

-- Create a trigger to update the 'updated_at' field
CREATE TRIGGER IF NOT EXISTS update_users_timestamp 
AFTER UPDATE ON users
BEGIN
  UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_appointments_timestamp 
AFTER UPDATE ON appointments
BEGIN
  UPDATE appointments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_medical_records_timestamp 
AFTER UPDATE ON medical_records
BEGIN
  UPDATE medical_records SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;
