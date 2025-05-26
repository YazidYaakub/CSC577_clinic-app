<?php
// Doctor Appointments Management
$pageTitle = 'Manage Appointments';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a doctor
requireLogin(ROLE_DOCTOR, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Determine if we're viewing all appointments or a specific one
$viewingSpecific = isset($_GET['id']) && is_numeric($_GET['id']);
$appointmentId = $viewingSpecific ? (int) $_GET['id'] : 0;

// Get appointment filter
$filter = sanitize($_GET['filter'] ?? 'upcoming');
$date = sanitize($_GET['date'] ?? '');
$searchTerm = sanitize($_GET['search'] ?? '');
$today = date('Y-m-d'); 
try {
    if ($viewingSpecific) {
        // Get details of a specific appointment
        $appointment = $db->fetchOne(
            "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.gender, u.date_of_birth 
             FROM appointments a 
             JOIN users u ON a.patient_id = u.id 
             WHERE a.id = ? AND a.doctor_id = ?",
            [$appointmentId, $userId]
        );
        
        if (!$appointment) {
            setFlashMessage('error', 'Appointment not found or you do not have permission to view it.', 'danger');
            redirect('appointments.php');
        }
        
        // Get patient's medical history
        $medicalHistory = $db->fetchAll(
            "SELECT m.*, a.appointment_date, a.appointment_time 
             FROM medical_records m 
             JOIN appointments a ON m.appointment_id = a.id 
             WHERE m.patient_id = ? AND m.doctor_id = ? 
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$appointment['patient_id'], $userId]
        );
        
        // Get prescriptions for each medical record
        foreach ($medicalHistory as &$record) {
            $record['prescriptions'] = $db->fetchAll(
                "SELECT * FROM prescriptions WHERE medical_record_id = ?",
                [$record['id']]
            );
        }
        unset($record); // Break the reference
    } else {
        // Build query for appointments list
        $query = "SELECT a.*, u.first_name, u.last_name, u.phone 
                 FROM appointments a 
                 JOIN users u ON a.patient_id = u.id 
                 WHERE a.doctor_id = ? ";
        
        $params = [$userId];
        
        // Apply date filter if provided
        if (!empty($date)) {
            $query .= "AND a.appointment_date = ? ";
            $params[] = $date;
        }
        // Apply status filter
        else {
            switch ($filter) {
                case 'today':
                    $query .= "AND a.appointment_date = ? ";
                    $params[] = $today;
                    break;
                case 'upcoming':
                    $query .= "AND a.appointment_date >= ? AND a.status IN ('pending', 'confirmed') ";
                    $params[] = $today;
                    break;
                case 'pending':
                    $query .= "AND a.status = 'pending' ";
                    break;
                case 'completed':
                    $query .= "AND a.status = 'completed' ";
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
                    $params[] = $today;
            }
        }
        
        // Apply search filter if provided
        if (!empty($searchTerm)) {
            $query .= "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ? OR a.symptoms LIKE ?) ";
            $searchParam = "%$searchTerm%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        $query .= "ORDER BY a.appointment_date, a.appointment_time";
        $appointments = $db->fetchAll($query, $params);
    }
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving appointments.', 'danger');
    
    // Set defaults
    $appointments = [];
    $appointment = null;
    $medicalHistory = [];
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
                <div>
                    <a href="add_medical_record.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success me-2">
                        <i class="fas fa-notes-medical me-2"></i> Add Medical Record
                    </a>
                    <a href="appointments.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Back to All Appointments
                    </a>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <!-- Appointment Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-check text-primary me-2"></i>
                                Appointment Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
                                    <p><strong>Time:</strong> <?php echo formatTime($appointment['appointment_time']); ?></p>
                                    <p>
                                        <strong>Status:</strong> 
                                        <span class="badge bg-<?php 
                                            echo $appointment['status'] === 'pending' ? 'warning' : 
                                                ($appointment['status'] === 'confirmed' ? 'success' : 
                                                    ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Created:</strong> <?php echo formatDate($appointment['created_at'], 'M j, Y g:i A'); ?></p>
                                    <p><strong>Last Updated:</strong> <?php echo formatDate($appointment['updated_at'], 'M j, Y g:i A'); ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($appointment['symptoms'])): ?>
                                <div class="mb-3">
                                    <h6>Reason for Visit / Symptoms:</h6>
                                    <p class="mb-0"><?php echo $appointment['symptoms']; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                <div class="mt-4">
                                    <h6>Update Appointment Status:</h6>
                                    <div class="btn-group" role="group">
                                        <?php if ($appointment['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-success" 
                                                    onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')">
                                                <i class="fas fa-check-circle me-1"></i> Confirm
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')">
                                            <i class="fas fa-times-circle me-1"></i> Cancel
                                        </button>
                                        <?php if ($appointment['appointment_date'] === date('Y-m-d')): ?>
                                            <button type="button" class="btn btn-info" 
                                                    onclick="updateStatus(<?php echo $appointment['id']; ?>, 'completed')">
                                                <i class="fas fa-check-double me-1"></i> Mark as Completed
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Patient Information -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user text-primary me-2"></i>
                                Patient Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <h4 class="mb-3"><?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <?php if (!empty($appointment['email'])): ?>
                                        <p><strong>Email:</strong> <?php echo $appointment['email']; ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appointment['phone'])): ?>
                                        <p><strong>Phone:</strong> <?php echo $appointment['phone']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($appointment['gender'])): ?>
                                        <p><strong>Gender:</strong> <?php echo ucfirst($appointment['gender']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appointment['date_of_birth'])): ?>
                                        <p>
                                            <strong>Date of Birth:</strong> <?php echo formatDate($appointment['date_of_birth']); ?>
                                            (<?php 
                                                $age = date_diff(date_create($appointment['date_of_birth']), date_create('today'))->y;
                                                echo $age . ' years';
                                            ?>)
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="patient_records.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-history me-1"></i> View Complete Records
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Medical History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-notes-medical text-primary me-2"></i>
                                Medical History
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($medicalHistory)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                    <p>No medical records found for this patient.</p>
                                    <a href="add_medical_record.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                                        Create Medical Record
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="medicalHistoryAccordion">
                                    <?php foreach ($medicalHistory as $index => $record): ?>
                                        <div class="accordion-item mb-3">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                                    <div class="d-flex justify-content-between w-100">
                                                        <span>Visit: <?php echo formatDate($record['appointment_date']); ?></span>
                                                        <span class="badge bg-info"><?php echo formatTime($record['appointment_time']); ?></span>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#medicalHistoryAccordion">
                                                <div class="accordion-body">
                                                    <h6>Diagnosis:</h6>
                                                    <p><?php echo $record['diagnosis']; ?></p>
                                                    
                                                    <h6>Treatment:</h6>
                                                    <p><?php echo $record['treatment']; ?></p>
                                                    
                                                    <?php if (!empty($record['notes'])): ?>
                                                        <h6>Notes:</h6>
                                                        <p><?php echo $record['notes']; ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($record['prescriptions'])): ?>
                                                        <h6>Prescriptions:</h6>
                                                        <?php foreach ($record['prescriptions'] as $prescription): ?>
                                                            <div class="prescription-item p-2 mb-2 border rounded">
                                                                <p class="mb-1"><strong><?php echo $prescription['medication_name']; ?></strong></p>
                                                                <p class="mb-1">Dosage: <?php echo $prescription['dosage']; ?></p>
                                                                <p class="mb-1">Frequency: <?php echo $prescription['frequency']; ?></p>
                                                                <p class="mb-1">Duration: <?php echo $prescription['duration']; ?></p>
                                                                <?php if (!empty($prescription['instructions'])): ?>
                                                                    <p class="mb-0">Instructions: <?php echo $prescription['instructions']; ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- All Appointments View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Manage Appointments</h1>
            </div>
            
            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo $searchTerm; ?>" placeholder="Patient name, phone, symptoms...">
                        </div>
                        <div class="col-md-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="text" class="form-control datepicker-availability" id="date" name="date" value="<?php echo $date; ?>" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-3">
                            <label for="filter" class="form-label">Status</label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (count($appointments) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            <?php 
                                if (!empty($date)) {
                                    echo 'Appointments for ' . formatDate($date);
                                } elseif ($filter === 'today') {
                                    echo 'Today\'s Appointments';
                                } elseif ($filter === 'upcoming') {
                                    echo 'Upcoming Appointments';
                                } elseif ($filter === 'pending') {
                                    echo 'Pending Appointments';
                                } elseif ($filter === 'completed') {
                                    echo 'Completed Appointments';
                                } elseif ($filter === 'cancelled') {
                                    echo 'Cancelled Appointments';
                                } else {
                                    echo 'All Appointments';
                                }
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                            <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                                            <td><?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></td>
                                            <td><?php echo $appointment['phone'] ?? 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $appointment['status'] === 'pending' ? 'warning' : 
                                                        ($appointment['status'] === 'confirmed' ? 'success' : 
                                                            ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($appointment['status'] === 'pending'): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (($appointment['status'] === 'confirmed' || $appointment['status'] === 'pending') 
                                                              && $appointment['appointment_date'] === date('Y-m-d')): ?>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="updateStatus(<?php echo $appointment['id']; ?>, 'completed')">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <a href="add_medical_record.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-outline-secondary">
                                                        <i class="fas fa-notes-medical"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No appointments found matching your criteria.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Add custom scripts
$extraScripts = <<<EOT
<script>
    // Function to update appointment status
    function updateStatus(appointmentId, status) {
        if (confirm('Are you sure you want to change the appointment status to ' + status + '?')) {
            $.ajax({
                url: '../includes/update_appointment_status.php',
                method: 'POST',
                data: { appointment_id: appointmentId, status: status },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appointment status updated successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to update appointment status. Please try again.');
                }
            });
        }
    }
</script>
EOT;

include '../includes/footer.php';
?>

