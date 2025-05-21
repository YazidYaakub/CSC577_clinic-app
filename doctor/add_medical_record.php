<?php
// Add Medical Record Page
$pageTitle = 'Add Medical Record';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a doctor
requireLogin(ROLE_DOCTOR, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Check if appointment ID is provided
if (!isset($_GET['appointment_id']) || !is_numeric($_GET['appointment_id'])) {
    setFlashMessage('error', 'Invalid appointment ID.', 'danger');
    redirect('appointments.php');
}

$appointmentId = (int) $_GET['appointment_id'];

try {
    // Get appointment details
    $appointment = $db->fetchOne(
        "SELECT a.*, u.first_name, u.last_name 
         FROM appointments a 
         JOIN users u ON a.patient_id = u.id 
         WHERE a.id = ? AND a.doctor_id = ?",
        [$appointmentId, $userId]
    );
    
    // Check if appointment exists and belongs to the current doctor
    if (!$appointment) {
        setFlashMessage('error', 'Appointment not found or you do not have permission to access it.', 'danger');
        redirect('appointments.php');
    }
    
    // Check if there's already a medical record for this appointment
    $existingRecord = $db->fetchOne(
        "SELECT id FROM medical_records WHERE appointment_id = ?",
        [$appointmentId]
    );
    
} catch (Exception $e) {
    error_log("Medical record error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving appointment data.', 'danger');
    redirect('appointments.php');
}

$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $diagnosis = sanitize($_POST['diagnosis'] ?? '');
    $treatment = sanitize($_POST['treatment'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Medications data
    $medications = [];
    $medicationNames = $_POST['medication_name'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $frequencies = $_POST['frequency'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $instructions = $_POST['instructions'] ?? [];
    
    // Validate required fields
    if (empty($diagnosis)) {
        $errors['diagnosis'] = 'Diagnosis is required';
    }
    
    if (empty($treatment)) {
        $errors['treatment'] = 'Treatment is required';
    }
    
    // Validate medications if provided
    for ($i = 0; $i < count($medicationNames); $i++) {
        if (!empty($medicationNames[$i])) {
            if (empty($dosages[$i])) {
                $errors["dosage_$i"] = 'Dosage is required';
            }
            
            if (empty($frequencies[$i])) {
                $errors["frequency_$i"] = 'Frequency is required';
            }
            
            if (empty($durations[$i])) {
                $errors["duration_$i"] = 'Duration is required';
            }
            
            // Add valid medication to array
            if (empty($errors["dosage_$i"]) && empty($errors["frequency_$i"]) && empty($errors["duration_$i"])) {
                $medications[] = [
                    'name' => $medicationNames[$i],
                    'dosage' => $dosages[$i],
                    'frequency' => $frequencies[$i],
                    'duration' => $durations[$i],
                    'instructions' => $instructions[$i] ?? ''
                ];
            }
        }
    }
    
    // If no errors, save the medical record
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert medical record
            $medicalRecordId = $db->insert(
                "INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, treatment, notes) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $appointment['patient_id'],
                    $userId,
                    $appointmentId,
                    $diagnosis,
                    $treatment,
                    $notes
                ]
            );
            
            // Insert prescriptions
            foreach ($medications as $medication) {
                $db->insert(
                    "INSERT INTO prescriptions (medical_record_id, medication_name, dosage, frequency, duration, instructions) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $medicalRecordId,
                        $medication['name'],
                        $medication['dosage'],
                        $medication['frequency'],
                        $medication['duration'],
                        $medication['instructions']
                    ]
                );
            }
            
            // Update appointment status to completed
            $db->update(
                "UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?",
                [$appointmentId]
            );
            
            // Create notification for patient
            createNotification(
                $appointment['patient_id'],
                "Medical record added for your appointment on " . formatDate($appointment['appointment_date']) . 
                ". You can view it in your medical history."
            );
            
            $db->commit();
            
            setFlashMessage('success', 'Medical record added successfully.', 'success');
            redirect('appointments.php?id=' . $appointmentId);
        } catch (Exception $e) {
            $db->rollback();
            error_log("Medical record save error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while saving the medical record. Please try again.';
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Add Medical Record</h1>
            <a href="appointments.php?id=<?php echo $appointmentId; ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Back to Appointment
            </a>
        </div>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $errors['general']; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($existingRecord)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                A medical record already exists for this appointment. Creating a new one will not replace the existing record.
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-injured text-primary me-2"></i>
                    Patient Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Patient:</strong> <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Appointment Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?> at <?php echo formatTime($appointment['appointment_time']); ?></p>
                    </div>
                </div>
                <?php if (!empty($appointment['symptoms'])): ?>
                    <div class="mt-2">
                        <p><strong>Reported Symptoms:</strong></p>
                        <p class="mb-0"><?php echo $appointment['symptoms']; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-notes-medical text-primary me-2"></i>
                    Medical Record Details
                </h5>
            </div>
            <div class="card-body">
                <form id="medical_record_form" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?appointment_id=' . $appointmentId; ?>">
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis*</label>
                        <textarea class="form-control <?php echo isset($errors['diagnosis']) ? 'is-invalid' : ''; ?>" id="diagnosis" name="diagnosis" rows="3" required><?php echo $diagnosis ?? ''; ?></textarea>
                        <?php if (isset($errors['diagnosis'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['diagnosis']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="treatment" class="form-label">Treatment*</label>
                        <textarea class="form-control <?php echo isset($errors['treatment']) ? 'is-invalid' : ''; ?>" id="treatment" name="treatment" rows="3" required><?php echo $treatment ?? ''; ?></textarea>
                        <?php if (isset($errors['treatment'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['treatment']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo $notes ?? ''; ?></textarea>
                        <div class="form-text">Include any relevant information not covered in diagnosis or treatment.</div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Prescriptions</h5>
                    
                    <div id="prescriptions_container">
                        <div class="prescription-item p-3 mb-3 border rounded">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="medication_name_0" class="form-label">Medication Name</label>
                                    <input type="text" class="form-control medication-name" id="medication_name_0" name="medication_name[]" value="<?php echo $medications[0]['name'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="dosage_0" class="form-label">Dosage</label>
                                    <input type="text" class="form-control medication-dosage" id="dosage_0" name="dosage[]" value="<?php echo $medications[0]['dosage'] ?? ''; ?>" placeholder="e.g., 500mg">
                                    <?php if (isset($errors['dosage_0'])): ?>
                                        <div class="text-danger"><?php echo $errors['dosage_0']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="frequency_0" class="form-label">Frequency</label>
                                    <input type="text" class="form-control medication-frequency" id="frequency_0" name="frequency[]" value="<?php echo $medications[0]['frequency'] ?? ''; ?>" placeholder="e.g., 3 times a day">
                                    <?php if (isset($errors['frequency_0'])): ?>
                                        <div class="text-danger"><?php echo $errors['frequency_0']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="duration_0" class="form-label">Duration</label>
                                    <input type="text" class="form-control medication-duration" id="duration_0" name="duration[]" value="<?php echo $medications[0]['duration'] ?? ''; ?>" placeholder="e.g., 7 days">
                                    <?php if (isset($errors['duration_0'])): ?>
                                        <div class="text-danger"><?php echo $errors['duration_0']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="instructions_0" class="form-label">Special Instructions</label>
                                <textarea class="form-control" id="instructions_0" name="instructions[]" rows="2"><?php echo $medications[0]['instructions'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <button type="button" id="add_prescription" class="btn btn-outline-primary">
                            <i class="fas fa-plus-circle me-1"></i> Add Another Medication
                        </button>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="appointments.php?id=<?php echo $appointmentId; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Medical Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

