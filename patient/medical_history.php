<?php
// Medical History Page for Patients
$pageTitle = 'Medical History';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
requireLogin(ROLE_PATIENT, BASE_URL . 'login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Check if viewing specific appointment
$appointmentId = isset($_GET['appointment_id']) && is_numeric($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : 0;

try {
    if ($appointmentId > 0) {
        // Get specific medical record for the specified appointment
        $medicalRecords = $db->fetchAll(
            "SELECT m.*, a.appointment_date, a.appointment_time, 
                    u.first_name, u.last_name, d.specialization 
             FROM medical_records m 
             JOIN appointments a ON m.appointment_id = a.id 
             JOIN users u ON m.doctor_id = u.id 
             JOIN doctor_details d ON u.id = d.user_id 
             WHERE m.patient_id = ? AND m.appointment_id = ?",
            [$userId, $appointmentId]
        );
        
        // Get prescriptions for each medical record
        if (!empty($medicalRecords)) {
            foreach ($medicalRecords as &$record) {
                $record['prescriptions'] = $db->fetchAll(
                    "SELECT * FROM prescriptions WHERE medical_record_id = ?",
                    [$record['id']]
                );
            }
            unset($record); // Break the reference
        }
    } else {
        // Get all medical records for the patient
        $medicalRecords = $db->fetchAll(
            "SELECT m.*, a.appointment_date, a.appointment_time, 
                    u.first_name, u.last_name, d.specialization 
             FROM medical_records m 
             JOIN appointments a ON m.appointment_id = a.id 
             JOIN users u ON m.doctor_id = u.id 
             JOIN doctor_details d ON u.id = d.user_id 
             WHERE m.patient_id = ? 
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$userId]
        );
        
        // Get prescriptions for each medical record
        if (!empty($medicalRecords)) {
            foreach ($medicalRecords as &$record) {
                $record['prescriptions'] = $db->fetchAll(
                    "SELECT * FROM prescriptions WHERE medical_record_id = ?",
                    [$record['id']]
                );
            }
            unset($record); // Break the reference
        }
    }
} catch (Exception $e) {
    error_log("Medical history error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving medical records.', 'danger');
    $medicalRecords = [];
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Medical History</h1>
            <?php if ($appointmentId > 0): ?>
                <a href="medical_history.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to All Records
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($medicalRecords)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo $appointmentId > 0 ? 'No medical records found for this appointment.' : 'You don\'t have any medical records yet.'; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-notes-medical text-primary me-2"></i>
                        <?php echo $appointmentId > 0 ? 'Medical Record for Selected Appointment' : 'All Medical Records'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="medicalRecordsAccordion">
                        <?php foreach ($medicalRecords as $index => $record): ?>
                            <div class="accordion-item mb-3 border">
                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                    <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                        <div class="d-flex flex-column flex-md-row justify-content-between w-100">
                                            <div>
                                                <span class="fw-bold">Date: <?php echo formatDate($record['appointment_date']); ?> at <?php echo formatTime($record['appointment_time']); ?></span>
                                                <span class="ms-md-3 d-block d-md-inline">Dr. <?php echo $record['first_name'] . ' ' . $record['last_name']; ?> (<?php echo $record['specialization']; ?>)</span>
                                            </div>
                                            <div class="text-muted small d-none d-md-block">
                                                <?php echo formatDate($record['created_at'], 'M j, Y'); ?>
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
                                        <div class="text-end mt-3">
                                            <button class="btn btn-sm btn-outline-primary print-record" data-record-id="<?php echo $record['id']; ?>">
                                                <i class="fas fa-print me-1"></i> Print Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Add custom print script
$siteName = htmlspecialchars($SITE_NAME);
$extraScripts = <<<EOT
<script>
    // Print individual medical record
    $('.print-record').on('click', function() {
        const recordId = $(this).data('record-id');
        const recordContent = $(this).closest('.accordion-item').find('.accordion-body').html();
        
        // Create a new window for printing
        const siteName = "{$siteName}";
        const printWindow = window.open('', '_blank');
      
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Medical Record</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
                <style>
                    body { padding: 20px; }
                    @media print {
                        .no-print { display: none !important; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="row mb-4">
                        <div class="col-12">
                            <h2 class="text-center">${siteName}</h2>
                            <h3 class="text-center">Medical Record</h3>
                        </div>
                    </div>
                    ${recordContent}
                    <div class="row mt-5">
                        <div class="col-12 text-center no-print">
                            <button class="btn btn-primary" onclick="window.print()">Print</button>
                        </div>
                    </div>
                </div>
                <script>
                    // Auto print
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                        }, 500);
                    };
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    });
</script>
EOT;

include '../includes/footer.php';


