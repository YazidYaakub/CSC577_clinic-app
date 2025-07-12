<?php
// Patient Records Page for Doctor
$pageTitle = 'Patient Records';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a doctor
requireLogin(ROLE_DOCTOR, '../login.php');

$userId = getCurrentUserId(); // The ID of the logged-in doctor
$db = Database::getInstance();

// Check if specific patient requested
$patientId = isset($_GET['patient_id']) && is_numeric($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
$searchTerm = sanitize($_GET['search'] ?? '');

try {
    if ($patientId > 0) {
        // Get specific patient details
        $patient = $db->fetchOne(
            "SELECT * FROM users WHERE id = ? AND role = ?",
            [$patientId, ROLE_PATIENT]
        );
        
        if (!$patient) {
            setFlashMessage('error', 'Patient not found.', 'danger');
            redirect('patient_records.php');
        }
        
        // Get patient's appointment history with THIS DOCTOR ONLY
        $appointments = $db->fetchAll(
            "SELECT a.*, d.first_name as doc_first_name, d.last_name as doc_last_name 
             FROM appointments a
             JOIN users d ON a.doctor_id = d.id
             WHERE a.patient_id = ? AND a.doctor_id = ? 
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$patientId, $userId] // Filter by patient ID AND logged-in doctor ID
        );
        
        // Get patient's medical records (ALL medical records for this patient, regardless of doctor)
        $medicalRecords = $db->fetchAll(
            "SELECT m.*, a.appointment_date, a.appointment_time, u.first_name, u.last_name as doc_last_name
             FROM medical_records m 
             JOIN appointments a ON m.appointment_id = a.id 
             JOIN users u ON m.doctor_id = u.id -- Join to get doctor's name for the record
             WHERE m.patient_id = ? 
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$patientId] // No doctor_id filter here, as requested, to pull all medical history
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
        // Get list of patients who have had appointments with this doctor
        $query = "SELECT DISTINCT u.* FROM users u 
                  JOIN appointments a ON u.id = a.patient_id 
                  WHERE a.doctor_id = ? AND u.role = ? ";
        
        $params = [$userId, ROLE_PATIENT];
        
        // Apply search if provided
        if (!empty($searchTerm)) {
            $query .= "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?) ";
            $searchParam = "%$searchTerm%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        $query .= "ORDER BY u.last_name, u.first_name";
        $patients = $db->fetchAll($query, $params);
        
        // For each patient, get their last appointment date (with this doctor)
        foreach ($patients as &$patient) {
            $lastAppointment = $db->fetchOne(
                "SELECT appointment_date, status FROM appointments 
                 WHERE patient_id = ? AND doctor_id = ? 
                 ORDER BY appointment_date DESC, appointment_time DESC 
                 LIMIT 1",
                [$patient['id'], $userId]
            );
            
            $patient['last_appointment'] = $lastAppointment['appointment_date'] ?? 'N/A';
            $patient['last_appointment_status'] = $lastAppointment['status'] ?? 'N/A';
            
            // Get count of medical records (only those created by this doctor)
            $recordCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM medical_records 
                 WHERE patient_id = ? AND doctor_id = ?",
                [$patient['id'], $userId]
            );
            
            $patient['record_count'] = $recordCount['count'] ?? 0;
        }
        unset($patient); // Break the reference
    }
} catch (Exception $e) {
    error_log("Patient records error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving patient records.', 'danger');
    
    // Set defaults
    $patients = [];
    $patient = null;
    $appointments = [];
    $medicalRecords = [];
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($patientId > 0): ?>
            <!-- Single Patient View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Patient Records</h1>
                <a href="patient_records.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to All Patients
                </a>
            </div>
            
            <div class="row">
                <div class="col-md-5">
                    <!-- Patient Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user text-primary me-2"></i>
                                Patient Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <h4 class="mb-3"><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <?php if (!empty($patient['email'])): ?>
                                        <p><strong>Email:</strong> <?php echo $patient['email']; ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patient['phone'])): ?>
                                        <p><strong>Phone:</strong> <?php echo $patient['phone']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($patient['gender'])): ?>
                                        <p><strong>Gender:</strong> <?php echo ucfirst($patient['gender']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($patient['date_of_birth'])): ?>
                                        <p>
                                            <strong>Date of Birth:</strong> <?php echo formatDate($patient['date_of_birth']); ?>
                                            (<?php 
                                                $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                                                echo $age . ' years';
                                            ?>)
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($patient['address'])): ?>
                                <p><strong>Address:</strong> <?php echo $patient['address']; ?></p>
                            <?php endif; ?>
                            
                            <p><strong>Registered Since:</strong> <?php echo formatDate($patient['created_at'], 'M j, Y'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Appointment History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                Appointment History (Your Appointments)
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
                                                <th>Doctor</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                                    <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['doc_first_name'] . ' ' . $appointment['doc_last_name']); ?></td>
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
                                                        <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <p>No appointment history found for this patient with you.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-7">
                    <!-- Medical Records -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-notes-medical text-primary me-2"></i>
                                Medical Records (All Records)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($medicalRecords) > 0): ?>
                                <div class="accordion" id="medicalRecordsAccordion">
                                    <?php foreach ($medicalRecords as $index => $record): ?>
                                        <div class="accordion-item mb-3 border rounded">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                                    <div class="d-flex flex-column flex-md-row justify-content-between w-100">
                                                        <div>
                                                            <span class="fw-bold">Visit: <?php echo formatDate($record['appointment_date']); ?></span>
                                                            <span class="ms-md-3 d-block d-md-inline"><?php echo formatTime($record['appointment_time']); ?></span>
                                                        </div>
                                                        <div class="text-muted small d-none d-md-block">
                                                            By Dr. <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['doc_last_name']); ?> on <?php echo formatDate($record['created_at'], 'M j, Y'); ?>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#medicalRecordsAccordion">
                                                <div class="accordion-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h5 class="mb-3">Diagnosis</h5>
                                                            <p><?php echo $record['diagnosis']; ?></p>
                                                            
                                                            <h5 class="mb-3">Treatment</h5>
                                                            <p><?php echo $record['treatment']; ?></p>
                                                            
                                                            <?php if (!empty($record['notes'])): ?>
                                                                <h5 class="mb-3">Additional Notes</h5>
                                                                <p><?php echo $record['notes']; ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h5 class="mb-3">Prescriptions</h5>
                                                            <?php if (!empty($record['prescriptions'])): ?>
                                                                <?php foreach ($record['prescriptions'] as $prescription): ?>
                                                                    <div class="prescription-item p-3 mb-3 border rounded">
                                                                        <h6><?php echo $prescription['medication_name']; ?></h6>
                                                                        <p class="mb-1"><strong>Dosage:</strong> <?php echo $prescription['dosage']; ?></p>
                                                                        <p class="mb-1"><strong>Frequency:</strong> <?php echo $prescription['frequency']; ?></p>
                                                                        <p class="mb-1"><strong>Duration:</strong> <?php echo $prescription['duration']; ?></p>
                                                                        <?php if (!empty($prescription['instructions'])): ?>
                                                                            <p class="mb-0"><strong>Instructions:</strong> <?php echo $prescription['instructions']; ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <p class="text-muted">No prescriptions were issued.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                    <p>No medical records found for this patient.</p>
                                    <?php if (count($appointments) > 0): ?>
                                        <a href="add_medical_record.php?appointment_id=<?php echo $appointments[0]['id']; ?>" class="btn btn-primary">
                                            Create Medical Record
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- All Patients View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Patient Records</h1>
            </div>
            
            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                        <div class="col-md-10">
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo $searchTerm; ?>" placeholder="Search patients by name, email, or phone...">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (count($patients) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users text-primary me-2"></i>
                            Patients (<?php echo count($patients); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Contact</th>
                                        <th>Last Visit</th>
                                        <th>Records</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr class="searchable-item">
                                            <td><?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?></td>
                                            <td>
                                                <?php if (!empty($patient['email'])): ?>
                                                    <div><i class="fas fa-envelope text-muted me-1"></i> <?php echo $patient['email']; ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($patient['phone'])): ?>
                                                    <div><i class="fas fa-phone text-muted me-1"></i> <?php echo $patient['phone']; ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $patient['last_appointment'] !== 'N/A' ? formatDate($patient['last_appointment']) : 'N/A'; ?>
                                                <?php if ($patient['last_appointment_status'] !== 'N/A'): ?>
                                                    <span class="badge bg-<?php 
                                                        echo $patient['last_appointment_status'] === 'pending' ? 'warning' : 
                                                            ($patient['last_appointment_status'] === 'confirmed' ? 'success' : 
                                                                ($patient['last_appointment_status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($patient['last_appointment_status']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $patient['record_count']; ?></span>
                                            </td>
                                            <td>
                                                <a href="patient_records.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-file-medical me-1"></i> View Records
                                                </a>
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
                    <?php echo !empty($searchTerm) ? 'No patients found matching your search criteria.' : 'You have not seen any patients yet.'; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Add custom print script
$siteName = htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8');
$extraScripts = <<<EOT
<script>
    $('.print-record').on('click', function() {
        const siteName = "{$siteName}";
        const recordId = $(this).data('record-id');
        const recordContent = $(this).closest('.accordion-item').find('.accordion-body').html();

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Medical Record</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
                <style>
                    body { padding: 20px; }
                    @media print { .no-print { display: none !important; } }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="row mb-4">
                        <div class="col-12">
                            <h2 class="text-center">\${siteName}</h2>
                            <h3 class="text-center">Medical Record</h3>
                        </div>
                    </div>
                    \${recordContent}
                    <div class="row mt-5">
                        <div class="col-12 text-center no-print">
                            <button class="btn btn-primary" onclick="window.print()">Print</button>
                        </div>
                    </div>
                </div>
                <script>
                    window.onload = function() { setTimeout(function() { window.print(); }, 500); };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    });
</script>
EOT;

include '../includes/footer.php';
?>
