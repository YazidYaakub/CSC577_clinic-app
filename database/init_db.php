<?php
/**
 * Database Initialization Script
 * Initializes the SQLite database with the schema
 */

require_once '../includes/config.php';

// Create database connection
try {
    echo "Creating database at: " . DB_PATH . "\n";
    
    // Create directory if it doesn't exist
    $dbDir = dirname(DB_PATH);
    if (!file_exists($dbDir)) {
        echo "Creating database directory...\n";
        mkdir($dbDir, 0755, true);
    }
    
    // Connect to SQLite database
    $db = new PDO("sqlite:" . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign keys
    $db->exec('PRAGMA foreign_keys = ON;');
    
    echo "Database connection established.\n";
    
    // Read and execute the schema
    echo "Executing schema...\n";
    $schemaFile = __DIR__ . '/schema_sqlite.sql';
    $schemaSQL = file_get_contents($schemaFile);
    
    // We'll execute each statement individually
    // First, create tables
    echo "Creating tables...\n";
    
    // Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
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
    )");
    
    // Doctor details table
    $db->exec("CREATE TABLE IF NOT EXISTS doctor_details (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        specialization TEXT NOT NULL,
        qualification TEXT NOT NULL,
        experience_years INTEGER,
        consultation_fee REAL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Doctor availability table
    $db->exec("CREATE TABLE IF NOT EXISTS doctor_availability (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        doctor_id INTEGER NOT NULL,
        day_of_week TEXT NOT NULL CHECK(day_of_week IN ('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')),
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        is_available INTEGER DEFAULT 1,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(doctor_id, day_of_week, start_time)
    )");
    
    // Appointments table
    $db->exec("CREATE TABLE IF NOT EXISTS appointments (
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
    )");
    
    // Medical records table
    $db->exec("CREATE TABLE IF NOT EXISTS medical_records (
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
    )");
    
    // Prescriptions table
    $db->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        medical_record_id INTEGER NOT NULL,
        medication_name TEXT NOT NULL,
        dosage TEXT NOT NULL,
        frequency TEXT NOT NULL,
        duration TEXT NOT NULL,
        instructions TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE
    )");
    
    // Notifications table
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        is_read INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create triggers
    echo "Creating triggers...\n";
    $db->exec("CREATE TRIGGER IF NOT EXISTS update_users_timestamp 
    AFTER UPDATE ON users
    BEGIN
      UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END");
    
    $db->exec("CREATE TRIGGER IF NOT EXISTS update_appointments_timestamp 
    AFTER UPDATE ON appointments
    BEGIN
      UPDATE appointments SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END");
    
    $db->exec("CREATE TRIGGER IF NOT EXISTS update_medical_records_timestamp 
    AFTER UPDATE ON medical_records
    BEGIN
      UPDATE medical_records SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END");
    
    // Insert sample data
    echo "Inserting sample data...\n";
    
    // Insert admin user
    $db->exec("INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role, phone)
    VALUES ('admin', '\$2y\$10\$8Y5ORDEYUaXBRZdDCCY2wuZVfgfLbUxUiKZAGQj.tRfFO/PqW1xj2', 'admin@healthcare.com', 'System', 'Administrator', 'admin', '1234567890')");
    
    // Insert sample doctor
    $db->exec("INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role, phone, gender)
    VALUES ('doctor', '\$2y\$10\$8Y5ORDEYUaXBRZdDCCY2wuZVfgfLbUxUiKZAGQj.tRfFO/PqW1xj2', 'doctor@example.com', 'John', 'Smith', 'doctor', '5551234567', 'male')");
    
    // Insert doctor details
    $db->exec("INSERT OR IGNORE INTO doctor_details (user_id, specialization, qualification, experience_years, consultation_fee)
    VALUES (2, 'Cardiology', 'MD, FACC - Johns Hopkins University', 10, 150.00)");
    
    // Insert sample patient
    $db->exec("INSERT OR IGNORE INTO users (username, password, email, first_name, last_name, role, phone, gender)
    VALUES ('patient', '\$2y\$10\$8Y5ORDEYUaXBRZdDCCY2wuZVfgfLbUxUiKZAGQj.tRfFO/PqW1xj2', 'patient@example.com', 'Jane', 'Doe', 'patient', '5557654321', 'female')");
    
    // Insert sample doctor availability
    $db->exec("INSERT OR IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available)
    VALUES (2, 'Monday', '09:00:00', '17:00:00', 1)");
    
    $db->exec("INSERT OR IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available)
    VALUES (2, 'Tuesday', '09:00:00', '17:00:00', 1)");
    
    $db->exec("INSERT OR IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available)
    VALUES (2, 'Wednesday', '09:00:00', '17:00:00', 1)");
    
    $db->exec("INSERT OR IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available)
    VALUES (2, 'Thursday', '09:00:00', '17:00:00', 1)");
    
    $db->exec("INSERT OR IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available)
    VALUES (2, 'Friday', '09:00:00', '15:00:00', 1)");
    
    echo "Schema executed successfully.\n";
    echo "Database initialized successfully!\n";
    
    // Verify tables were created
    $tablesQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Created tables: " . implode(', ', $tables) . "\n";
    
    // Verify sample data
    $usersCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users count: " . $usersCount . "\n";
    
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}
