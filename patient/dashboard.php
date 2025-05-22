<?php
// Patient Dashboard
$pageTitle = 'Patient Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
requireLogin(ROLE_PATIENT, BASE_URL . 'login.php');
$patientName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Patient';

$userId = getCurrentUserId();
$db = Database::getInstance();

try {
    // Get current date for SQLite
    $currentDate = getCurrentDate();
    
    // Get upcoming appointments
    $upcomingAppointments = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name, d.specialization 
         FROM appointments a 
         JOIN users u ON a.doctor_id = u.id 
         JOIN doctor_details d ON u.id = d.user_id 
         WHERE a.patient_id = ? AND a.appointment_date >= ? AND a.status IN ('pending', 'confirmed') 
         ORDER BY a.appointment_date, a.appointment_time 
         LIMIT 5",
        [$userId, $currentDate]
    );
    
    // Get recent appointments
    $recentAppointments = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name, d.specialization 
         FROM appointments a 
         JOIN users u ON a.doctor_id = u.id 
         JOIN doctor_details d ON u.id = d.user_id 
         WHERE a.patient_id = ? AND (a.appointment_date < ? OR a.status = 'completed') 
         ORDER BY a.appointment_date DESC, a.appointment_time DESC 
         LIMIT 5",
        [$userId, $currentDate]
    );
    
    // Get notifications
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 5",
        [$userId]
    );
    
    // Get medical records counts
    $medicalRecords = $db->fetchOne(
        "SELECT COUNT(*) as count FROM medical_records WHERE patient_id = ?",
        [$userId]
    );
} catch (Exception $e) {
    error_log("Patient dashboard error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading dashboard data.', 'danger');
    
    // Set defaults
    $upcomingAppointments = [];
    $recentAppointments = [];
    $notifications = [];
    $medicalRecords = ['count' => 0];
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><?php echo htmlspecialchars($patientName);?>'s Dashboard</h1>
        
        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Upcoming Appointments</h5>
                        <div class="stat-value"><?php echo count($upcomingAppointments); ?></div>
                        <p class="card-text">Scheduled appointments</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Completed Visits</h5>
                        <div class="stat-value"><?php echo count($recentAppointments); ?></div>
                        <p class="card-text">Previous appointments</p>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Medical Records</h5>
                        <div class="stat-value"><?php echo $medicalRecords['count']; ?></div>
                        <p class="card-text">Health history entries</p>
                        <i class="fas fa-notes-medical stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-warning text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Notifications</h5>
                        <div class="stat-value"><?php echo count($notifications); ?></div>
                        <p class="card-text">Recent alerts</p>
                        <i class="fas fa-bell stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Upcoming Appointments -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-day text-primary me-2"></i>
                            Upcoming Appointments
                        </h5>
                        <a href="manage_appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($upcomingAppointments) > 0): ?>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="appointment-card p-3 mb-3 border rounded status-<?php echo $appointment['status']; ?>">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5>Dr. <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></h5>
                                            <p class="text-muted mb-1"><?php echo $appointment['specialization']; ?></p>
                                            <p class="mb-1">
                                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                                <?php echo formatDate($appointment['appointment_date']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-clock text-primary me-2"></i>
                                                <?php echo formatTime($appointment['appointment_time']); ?>
                                            </p>
                                            <?php if (!empty($appointment['symptoms'])): ?>
                                                <p class="mb-1">
                                                    <i class="fas fa-file-medical text-primary me-2"></i>
                                                    <?php echo $appointment['symptoms']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <span class="badge bg-<?php echo $appointment['status'] === 'pending' ? 'warning' : 'success'; ?> mb-2">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <div class="btn-group-vertical w-100">
                                                <a href="manage_appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary mb-1">
                                                    <i class="fas fa-edit me-1"></i> Manage
                                                </a>
                                                <?php if ($appointment['status'] !== 'cancelled'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $appointment['id']; ?>">
                                                        <i class="fas fa-times-circle me-1"></i> Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Cancel Modal -->
                                <div class="modal fade" id="cancelModal<?php echo $appointment['id']; ?>" tabindex="-1" aria-labelledby="cancelModalLabel<?php echo $appointment['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="cancelModalLabel<?php echo $appointment['id']; ?>">Cancel Appointment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to cancel this appointment?</p>
                                                <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?><br>
                                                <strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?><br>
                                                <strong>Doctor:</strong> Dr. <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></p>
                                                <p class="text-danger">Note: Cancellations made less than <?php echo CANCEL_DEADLINE_HOURS; ?> hours before the appointment may be subject to a cancellation fee.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <a href="cancel_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-danger">Cancel Appointment</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                                <p>You don't have any upcoming appointments.</p>
                                <a href="book_appointment.php" class="btn btn-primary">Book an Appointment</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notifications and Recent Appointments -->
            <div class="col-md-6">
                <!-- Notifications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bell text-primary me-2"></i>
                            Notifications
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($notifications) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <p class="mb-1"><?php echo $notification['message']; ?></p>
                                            <small><?php echo formatDate($notification['created_at'], 'M j, g:i A'); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <p>You don't have any notifications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Appointments -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-primary me-2"></i>
                            Recent Appointments
                        </h5>
                        <a href="medical_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentAppointments) > 0): ?>
                            <?php foreach ($recentAppointments as $appointment): ?>
                                <div class="appointment-card p-3 mb-3 border rounded status-<?php echo $appointment['status']; ?>">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5>Dr. <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></h5>
                                            <p class="text-muted mb-1"><?php echo $appointment['specialization']; ?></p>
                                            <p class="mb-0">
                                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                                <?php echo formatDate($appointment['appointment_date']); ?>
                                            </p>
                                            <p class="mb-0">
                                                <i class="fas fa-clock text-primary me-2"></i>
                                                <?php echo formatTime($appointment['appointment_time']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] === 'completed' ? 'success' : 
                                                    ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary'); 
                                            ?> mb-2">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <a href="medical_history.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                <i class="fas fa-file-medical me-1"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p>You don't have any past appointments.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

