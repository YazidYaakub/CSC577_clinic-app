<?php
header("Location: login.php");
exit;
?>

<?php
// Home page for the MySihat Appointment System
$pageTitle = 'Home';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get stats for the homepage
$db = Database::getInstance();

try {
    // Get total doctors
    $doctorsCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_DOCTOR]);
    
    // Get total patients
    $patientsCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_PATIENT]);
    
    // Get upcoming appointments
    $currentDate = getCurrentDate();
    $upcomingAppointments = $db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments 
         WHERE appointment_date >= ? AND status IN ('pending', 'confirmed')",
        [$currentDate]
    );
} catch (Exception $e) {
    error_log("Home page error: " . $e->getMessage());
    // Set default values in case of database error
    $doctorsCount = ['count' => 0];
    $patientsCount = ['count' => 0];
    $upcomingAppointments = ['count' => 0];
}

// Include header
include 'includes/header.php';
?>

<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold">MySihat Appointment System</h1>
                <p class="lead">Simplifying healthcare appointments for doctors and patients. </p>
                <div class="mt-4">
                    <a href="#features" class="btn btn-light btn-lg me-3">Learn More</a> 
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-outline-light btn-lg">Demo</a>
                </div>
            </div>
            <div class="col-md-6 d-none d-md-block">
                <div class="text-center">
                    <svg width="400" height="300" viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg">
                        <rect x="150" y="100" width="500" height="400" rx="20" fill="#ffffff" opacity="0.2"/>
                        <circle cx="400" cy="250" r="100" fill="#ffffff" opacity="0.3"/>
                        <path d="M320 250 L360 250 L360 210 L440 210 L440 250 L480 250 L480 330 L440 330 L440 370 L360 370 L360 330 L320 330 Z" fill="#ffffff"/>
                        <rect x="200" y="180" width="120" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="200" y="220" width="80" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="200" y="260" width="100" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="200" y="300" width="60" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="200" y="340" width="90" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="200" y="380" width="70" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="500" y="180" width="120" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="500" y="220" width="80" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="500" y="260" width="100" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="500" y="300" width="60" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="500" y="340" width="90" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                        <rect x="500" y="380" width="70" height="20" rx="10" fill="#ffffff" opacity="0.4"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card stat-card h-100 bg-light">
                <div class="card-body">
                    <h5 class="card-title">Expert Doctors</h5>
                    <div class="stat-value"><?php echo number_format($doctorsCount['count']); ?></div>
                    <p class="card-text">Professional healthcare experts at your service</p>
                    <i class="fas fa-user-md stat-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100 bg-light">
                <div class="card-body">
                    <h5 class="card-title">Registered Patients</h5>
                    <div class="stat-value"><?php echo number_format($patientsCount['count']); ?></div>
                    <p class="card-text">Growing community of satisfied patients</p>
                    <i class="fas fa-users stat-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card h-100 bg-light">
                <div class="card-body">
                    <h5 class="card-title">Upcoming Appointments</h5>
                    <div class="stat-value"><?php echo number_format($upcomingAppointments['count']); ?></div>
                    <p class="card-text">Active appointments scheduled in our system</p>
                    <i class="fas fa-calendar-check stat-icon text-primary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="features" class="bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">Our Features</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Easy Appointments</h5>
                        <p class="card-text">Book, reschedule, or cancel appointments with just a few clicks. No more waiting on phone calls.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-user-md fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Doctor Selection</h5>
                        <p class="card-text">Choose from our wide range of healthcare professionals based on specialization and availability.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-notes-medical fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Medical Records</h5>
                        <p class="card-text">Access your complete medical history, prescriptions, and visit details in one secure place.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-bell fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Notifications</h5>
                        <p class="card-text">Get timely reminders about upcoming appointments and important healthcare updates.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Mobile Friendly</h5>
                        <p class="card-text">Use our system on any device - desktop, tablet, or smartphone, with the same great experience.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                        <h5 class="card-title">Secure & Private</h5>
                        <p class="card-text">Your personal and medical data is protected with industry-standard security measures.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <h2 class="text-center mb-5">How It Works</h2>
    
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <span class="text-white fw-bold fs-1">1</span>
                        </div>
                        <h4 class="mt-3">Create an Account</h4>
                        <p>Register as a patient to access our healthcare services or as a doctor to offer your expertise.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <span class="text-white fw-bold fs-1">2</span>
                        </div>
                        <h4 class="mt-3">Book Appointment</h4>
                        <p>Browse available doctors, select your preferred date and time slot that fits your schedule.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <span class="text-white fw-bold fs-1">3</span>
                        </div>
                        <h4 class="mt-3">Get Care</h4>
                        <p>Visit the doctor at the scheduled time and access your medical records anytime afterward.</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary btn-lg">Get Started Now</a>
                <?php else: ?>
                    <?php if (hasRole(ROLE_PATIENT)): ?>
                        <a href="<?php echo BASE_URL; ?>patient/book_appointment.php" class="btn btn-primary btn-lg">Book Your Appointment</a>
                    <?php endif; ?>
                    <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
