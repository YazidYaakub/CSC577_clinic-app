<?php
// Manage Appointments Page for Patients
$pageTitle = 'My Appointments';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
requireLogin(ROLE_PATIENT, BASE_URL . 'login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Determine if we're viewing all appointments or a specific one
$viewingSpecific = isset($_GET['id']) && is_numeric($_GET['id']);
$appointmentId = $viewingSpecific ? (int) $_GET['id'] : 0;

// Get appointment filter
$filter = sanitize($_GET['filter'] ?? 'upcoming');

try {
    if ($viewingSpecific) {
        // Get details of a specific appointment
        $appointment = $db->fetchOne(
            "SELECT a.*, u.first_name, u.last_name, d.specialization 
             FROM appointments a 
             JOIN users u ON a.doctor_id = u.id 
             JOIN doctor_details d ON u.id = d.user_id 
             WHERE a.id = ? AND a.patient_id = ?",
            [$appointmentId, $userId]
        );
        
        if (!$appointment) {
            setFlashMessage('error', 'Appointment not found or you do not have permission to view it.', 'danger');
            redirect('manage_appointments.php');
        }
        
        // Get related medical records if appointment is completed
        $medicalRecords = [];
        if ($appointment['status'] === 'completed') {
            $medicalRecords = $db->fetchAll(
                "SELECT m.*, p.medication_name, p.dosage, p.frequency, p.duration, p.instructions 
                 FROM medical_records m 
                 LEFT JOIN prescriptions p ON m.id = p.medical_record_id 
                 WHERE m.appointment_id = ? AND m.patient_id = ?",
                [$appointmentId, $userId]
            );
        }
    } else {
        // Get current date for SQLite
        $currentDate = getCurrentDate();
        
        // Get list of appointments based on filter
        $query = "SELECT a.*, u.first_name, u.last_name, d.specialization 
                 FROM appointments a 
                 JOIN users u ON a.doctor_id = u.id 
                 JOIN doctor_details d ON u.id = d.user_id 
                 WHERE a.patient_id = ? ";
        
        $params = [$userId];
        
        switch ($filter) {
            case 'upcoming':
                $query .= "AND a.appointment_date >= ? AND a.status IN ('pending', 'confirmed') ";
                $params[] = $currentDate;
                break;
            case 'past':
                $query .= "AND (a.appointment_date < ? OR a.status = 'completed') ";
                $params[] = $currentDate;
                break;
            case 'cancelled':
                $query .= "AND a.status = 'cancelled' ";
                break;
            case 'all':
                // No additional condition
                break;
            default:
                $filter = 'upcoming'; // Default to upcoming
                $query .= "AND a.appointment_date >= ? AND a.status IN ('pending', 'confirmed') ";
                $params[] = $currentDate;
        }
        
        $query .= "ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $appointments = $db->fetchAll($query, $params);
    }
} catch (Exception $e) {
    error_log("Manage appointments error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving appointments.', 'danger');
    
    // Set defaults
    $appointments = [];
    $appointment = null;
    $medicalRecords = [];
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($viewingSpecific): ?>
            <!-- Single Appointment View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Appointment Details</h1>
                <a href="manage_appointments.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to All Appointments
                </a>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check text-primary me-2"></i>
                        Appointment Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Doctor:</strong> Dr. <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></p>
                            <p><strong>Specialization:</strong> <?php echo $appointment['specialization']; ?></p>
                            <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                            <p><strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo $appointment['status'] === 'pending' ? 'warning' : 
                                        ($appointment['status'] === 'confirmed' ? 'success' : 
                                            ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </p>
                            <p><strong>Booked On:</strong> <?php echo formatDate($appointment['created_at'], 'M j, Y g:i A'); ?></p>
                            <?php if (!empty($appointment['symptoms'])): ?>
                                <p><strong>Reason for Visit / Symptoms:</strong> <?php echo $appointment['symptoms']; ?></p>
                            <?php endif; ?>
                            
                            <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                <div class="mt-3">
                                    <a href="cancel_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                        <i class="fas fa-times-circle me-2"></i> Cancel Appointment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($appointment['status'] === 'completed' && !empty($medicalRecords)): ?>
                <!-- Medical Records for this appointment -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-notes-medical text-primary me-2"></i>
                            Medical Records
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($medicalRecords as $record): ?>
                            <div class="medical-record p-3 mb-3 border rounded">
                                <h5>Diagnosis</h5>
                                <p><?php echo $record['diagnosis']; ?></p>
                                
                                <h5>Treatment</h5>
                                <p><?php echo $record['treatment']; ?></p>
                                
                                <?php if (!empty($record['notes'])): ?>
                                    <h5>Notes</h5>
                                    <p><?php echo $record['notes']; ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($record['medication_name'])): ?>
                                    <h5>Prescribed Medications</h5>
                                    <div class="prescription-item p-3 border rounded mb-2">
                                        <p><strong>Medication:</strong> <?php echo $record['medication_name']; ?></p>
                                        <p><strong>Dosage:</strong> <?php echo $record['dosage']; ?></p>
                                        <p><strong>Frequency:</strong> <?php echo $record['frequency']; ?></p>
                                        <p><strong>Duration:</strong> <?php echo $record['duration']; ?></p>
                                        <?php if (!empty($record['instructions'])): ?>
                                            <p><strong>Instructions:</strong> <?php echo $record['instructions']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="text-muted mt-2">
                                    <small>Record created on <?php echo formatDate($record['created_at'], 'M j, Y g:i A'); ?></small>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- All Appointments View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>My Appointments</h1>
                <a href="book_appointment.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Book New Appointment
                </a>
            </div>
            
            <!-- Filter tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" href="?filter=upcoming">
                        <i class="fas fa-calendar-day me-1"></i> Upcoming
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'past' ? 'active' : ''; ?>" href="?filter=past">
                        <i class="fas fa-history me-1"></i> Past
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" href="?filter=cancelled">
                        <i class="fas fa-times-circle me-1"></i> Cancelled
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">
                        <i class="fas fa-list me-1"></i> All
                    </a>
                </li>
            </ul>
            
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $appointment): ?>
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
                                        <i class="fas fa-comment-medical text-primary me-2"></i>
                                        <?php echo substr($appointment['symptoms'], 0, 100) . (strlen($appointment['symptoms']) > 100 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="badge bg-<?php 
                                    echo $appointment['status'] === 'pending' ? 'warning' : 
                                        ($appointment['status'] === 'confirmed' ? 'success' : 
                                            ($appointment['status'] === 'cancelled' ? 'danger' : 
                                                ($appointment['status'] === 'completed' ? 'secondary' : 'primary'))); 
                                ?> mb-2">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                                
                                <div class="btn-group-vertical w-100">
                                    <a href="manage_appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary mb-1">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </a>
                                    
                                    <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                        <a href="cancel_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                            <i class="fas fa-times-circle me-1"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php 
                        if ($filter === 'upcoming') {
                            echo 'You don\'t have any upcoming appointments.';
                        } elseif ($filter === 'past') {
                            echo 'You don\'t have any past appointments.';
                        } elseif ($filter === 'cancelled') {
                            echo 'You don\'t have any cancelled appointments.';
                        } else {
                            echo 'You don\'t have any appointments.';
                        }
                    ?>
                    <a href="book_appointment.php" class="alert-link ms-2">Book an appointment now</a>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

