<?php
// Admin Appointments Management
$pageTitle = 'Manage Appointments';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is an admin
requireLogin(ROLE_ADMIN, BASE_URL . '/login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Get view parameters
$viewMode = sanitize($_GET['view'] ?? 'all');
$searchTerm = sanitize($_GET['search'] ?? '');
$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');
$doctorId = isset($_GET['doctor_id']) && is_numeric($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : 0;
$patientId = isset($_GET['patient_id']) && is_numeric($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
$viewingSpecific = isset($_GET['id']) && is_numeric($_GET['id']);
$specificAppointmentId = $viewingSpecific ? (int) $_GET['id'] : 0;

try {
    if ($viewingSpecific) {
        // Get specific appointment
        $appointment = $db->fetchOne(
            "SELECT a.*, 
                    p.first_name as patient_first_name, p.last_name as patient_last_name, 
                    p.email as patient_email, p.phone as patient_phone, 
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name, 
                    dd.specialization 
             FROM appointments a 
             JOIN users p ON a.patient_id = p.id 
             JOIN users d ON a.doctor_id = d.id 
             JOIN doctor_details dd ON d.id = dd.user_id 
             WHERE a.id = ?",
            [$specificAppointmentId]
        );
        
        if (!$appointment) {
            setFlashMessage('error', 'Appointment not found.', 'danger');
            redirect('appointments.php');
        }
        
        // Get medical records for this appointment
        $medicalRecords = $db->fetchAll(
            "SELECT m.*, u.first_name, u.last_name 
             FROM medical_records m 
             JOIN users u ON m.doctor_id = u.id 
             WHERE m.appointment_id = ?",
            [$specificAppointmentId]
        );
        
        // Get prescriptions for each medical record
        foreach ($medicalRecords as &$record) {
            $record['prescriptions'] = $db->fetchAll(
                "SELECT * FROM prescriptions WHERE medical_record_id = ?",
                [$record['id']]
            );
        }
        unset($record); // Break the reference
    } else {
        // Build query for appointments list
        $query = "SELECT a.*, 
                          p.first_name as patient_first_name, p.last_name as patient_last_name, 
                          d.first_name as doctor_first_name, d.last_name as doctor_last_name, 
                          dd.specialization 
                   FROM appointments a 
                   JOIN users p ON a.patient_id = p.id 
                   JOIN users d ON a.doctor_id = d.id 
                   JOIN doctor_details dd ON d.id = dd.user_id 
                   WHERE 1=1 ";
        
        $params = [];
        
        // Apply status filter
        if ($viewMode !== 'all') {
            $query .= "AND a.status = ? ";
            $params[] = $viewMode;
        }
        
        // Apply date range filter
        if (!empty($startDate)) {
            $query .= "AND a.appointment_date >= ? ";
            $params[] = $startDate;
        }
        
        if (!empty($endDate)) {
            $query .= "AND a.appointment_date <= ? ";
            $params[] = $endDate;
        }
        
        // Apply doctor filter
        if ($doctorId > 0) {
            $query .= "AND a.doctor_id = ? ";
            $params[] = $doctorId;
        }
        
        // Apply patient filter
        if ($patientId > 0) {
            $query .= "AND a.patient_id = ? ";
            $params[] = $patientId;
        }
        
        // Apply search filter
        if (!empty($searchTerm)) {
            $query .= "AND (p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR a.symptoms LIKE ?) ";
            $searchParam = "%$searchTerm%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        $query .= "ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        $appointments = $db->fetchAll($query, $params);
        
        // If filtering by doctor or patient, get their details
        if ($doctorId > 0) {
            $doctor = $db->fetchOne(
                "SELECT u.*, dd.specialization 
                 FROM users u 
                 JOIN doctor_details dd ON u.id = dd.user_id 
                 WHERE u.id = ? AND u.role = ?",
                [$doctorId, ROLE_DOCTOR]
            );
        }
        
        if ($patientId > 0) {
            $patient = $db->fetchOne(
                "SELECT * FROM users WHERE id = ? AND role = ?",
                [$patientId, ROLE_PATIENT]
            );
        }
    }
} catch (Exception $e) {
    error_log("Appointments management error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving appointment data.', 'danger');
    
    // Set defaults
    $appointments = [];
    $appointment = null;
    $medicalRecords = [];
    $doctor = null;
    $patient = null;
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
                <a href="appointments.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Appointments List
                </a>
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
                            <p><strong>Appointment ID:</strong> <?php echo $appointment['id']; ?></p>
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
                            <p><strong>Created:</strong> <?php echo formatDate($appointment['created_at'], 'M j, Y g:i A'); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo formatDate($appointment['updated_at'], 'M j, Y g:i A'); ?></p>
                            
                            <?php if (!empty($appointment['symptoms'])): ?>
                                <div class="mt-3">
                                    <h6>Reason for Visit / Symptoms:</h6>
                                    <p class="mb-0"><?php echo $appointment['symptoms']; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            // Display "Update Appointment Status" section only if the appointment is not 'completed'
                            if ($appointment['status'] !== 'completed'): 
                            ?>
                            <div class="mt-4">
                                <h6>Update Appointment Status:</h6>
                                <button type="button" class="btn btn-danger" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled')">
                                    <i class="fas fa-times-circle me-1"></i> Cancel Appointment
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Doctor Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-md text-primary me-2"></i>
                                Doctor Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <h4 class="mb-3">Dr. <?php echo $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']; ?></h4>
                            <p><strong>Specialization:</strong> <?php echo $appointment['specialization']; ?></p>
                            
                            <div class="mt-3">
                                <a href="users.php?id=<?php echo $appointment['doctor_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-user me-1"></i> View Doctor Profile
                                </a>
                                <a href="appointments.php?doctor_id=<?php echo $appointment['doctor_id']; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-calendar-alt me-1"></i> View Doctor Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Patient Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user text-primary me-2"></i>
                                Patient Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <h4 class="mb-3"><?php echo $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']; ?></h4>
                            
                            <?php if (!empty($appointment['patient_email'])): ?>
                                <p><strong>Email:</strong> <?php echo $appointment['patient_email']; ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($appointment['patient_phone'])): ?>
                                <p><strong>Phone:</strong> <?php echo $appointment['patient_phone']; ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="users.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-user me-1"></i> View Patient Profile
                                </a>
                                <a href="appointments.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-calendar-alt me-1"></i> View Patient Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Records -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-notes-medical text-primary me-2"></i>
                                Medical Records
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($medicalRecords) > 0): ?>
                                <div class="accordion" id="medicalRecordsAccordion">
                                    <?php foreach ($medicalRecords as $index => $record): ?>
                                        <div class="accordion-item mb-3">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                                    <div class="d-flex justify-content-between w-100">
                                                        <span>Record by Dr. <?php echo $record['first_name'] . ' ' . $record['last_name']; ?></span>
                                                        <span class="badge bg-info"><?php echo formatDate($record['created_at'], 'M j, Y'); ?></span>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#medicalRecordsAccordion">
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
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                    <p>No medical records found for this appointment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- All Appointments View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>
                    <?php if (isset($doctor)): ?>
                        Appointments for Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?>
                    <?php elseif (isset($patient)): ?>
                        Appointments for <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?>
                    <?php else: ?>
                        Manage Appointments
                    <?php endif; ?>
                </h1>
                
                <div>
                    <a href="book_appointment_for_patient.php" class="btn btn-success me-2">
                        <i class="fas fa-plus-circle me-2"></i> Book New Appointment
                    </a>
                    <?php if (isset($doctor) || isset($patient)): ?>
                        <a href="appointments.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i> Back to All Appointments
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo $searchTerm; ?>" placeholder="Patient or doctor name...">
                        </div>
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="text" class="form-control datepicker-availability" id="start_date" name="start_date" value="<?php echo $startDate; ?>" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="text" class="form-control datepicker-availability" id="end_date" name="end_date" value="<?php echo $endDate; ?>" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-2">
                            <label for="view" class="form-label">Status</label>
                            <select class="form-select" id="view" name="view">
                                <option value="all" <?php echo $viewMode === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $viewMode === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $viewMode === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $viewMode === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $viewMode === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                            
                            <?php if (isset($doctorId) && $doctorId > 0): ?>
                                <input type="hidden" name="doctor_id" value="<?php echo $doctorId; ?>">
                            <?php endif; ?>
                            
                            <?php if (isset($patientId) && $patientId > 0): ?>
                                <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Filter information -->
            <?php if (isset($doctor) || isset($patient) || !empty($startDate) || !empty($endDate) || $viewMode !== 'all'): ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-filter me-2"></i>
                    <strong>Filters applied:</strong>
                    <?php if (isset($doctor)): ?>
                        <span class="badge bg-primary me-2">Doctor: Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?></span>
                    <?php endif; ?>
                    
                    <?php if (isset($patient)): ?>
                        <span class="badge bg-primary me-2">Patient: <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></span>
                    <?php endif; ?>
                    
                    <?php if (!empty($startDate)): ?>
                        <span class="badge bg-primary me-2">From: <?php echo formatDate($startDate); ?></span>
                    <?php endif; ?>
                    
                    <?php if (!empty($endDate)): ?>
                        <span class="badge bg-primary me-2">To: <?php echo formatDate($endDate); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($viewMode !== 'all'): ?>
                        <span class="badge bg-primary me-2">Status: <?php echo ucfirst($viewMode); ?></span>
                    <?php endif; ?>
                    
                    <?php if (!empty($searchTerm)): ?>
                        <span class="badge bg-primary me-2">Search: "<?php echo $searchTerm; ?>"</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Appointments Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        Appointments
                        <span class="badge bg-primary ms-2"><?php echo count($appointments); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($appointments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                            <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                                            <td><?php echo $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']; ?></td>
                                            <td>Dr. <?php echo $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $appointment['status'] === 'pending' ? 'warning' : 
                                                        ($appointment['status'] === 'confirmed' ? 'success' : 
                                                            ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($appointment['created_at'], 'M j, Y'); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php 
                                                    // Display Cancel button only if the appointment is not 'completed'
                                                    if ($appointment['status'] !== 'completed'): 
                                                    ?>
                                                    <a class="btn btn-outline-danger" href="#" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'cancelled'); return false;">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No appointments found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
    
    // Handle appointment status select change (this part is for the single appointment view dropdown)
    // This event listener is no longer needed since the select dropdown is replaced by a button.
    // Keeping it commented out for reference in case the select is re-introduced.
    /*
    $('.appointment-status-select').on('change', function() {
        const appointmentId = $(this).data('appointment-id');
        const newStatus = $(this).val();
        
        updateStatus(appointmentId, newStatus);
    });
    */
</script>
EOT;

include '../includes/footer.php';
?>
