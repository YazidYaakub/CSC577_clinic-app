<?php
// Admin Book Appointment for Patient Page
$pageTitle = 'Book Appointment for Patient';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is an admin
requireLogin(ROLE_ADMIN, BASE_URL . 'login.php');

// $adminUserId = getCurrentUserId(); // No longer needed if not tracking who booked
$db = Database::getInstance();

$errors = [];
$formData = [
    'patient_id' => sanitize($_GET['patient_id'] ?? ''), // Pre-fill if coming from a patient's profile
    'doctor_id' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'symptoms' => ''
];

// Get lists of patients and doctors
try {
    $patients = $db->fetchAll(
        "SELECT id, first_name, last_name FROM users WHERE role = ? ORDER BY first_name, last_name",
        [ROLE_PATIENT]
    );
    $doctors = $db->fetchAll(
        "SELECT u.id, u.first_name, u.last_name, d.specialization FROM users u JOIN doctor_details d ON u.id = d.user_id WHERE u.role = ? ORDER BY u.first_name, u.last_name",
        [ROLE_DOCTOR]
    );
} catch (Exception $e) {
    error_log("Error fetching users/doctors for admin booking: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while fetching user data. Please try again.', 'danger');
    $patients = [];
    $doctors = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $formData = [
        'patient_id' => sanitize($_POST['patient_id'] ?? ''),
        'doctor_id' => sanitize($_POST['doctor_id'] ?? ''),
        'appointment_date' => sanitize($_POST['appointment_date'] ?? ''),
        'appointment_time' => sanitize($_POST['selected_time'] ?? ''), // This comes from hidden input set by JS
        'symptoms' => sanitize($_POST['symptoms'] ?? '')
    ];
    
    // Validate patient selection
    if (empty($formData['patient_id'])) {
        $errors['patient_id'] = 'Please select a patient';
    } else {
        // Verify patient_id exists and is a patient role
        $checkPatient = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = ?", [$formData['patient_id'], ROLE_PATIENT]);
        if (!$checkPatient) {
            $errors['patient_id'] = 'Invalid patient selected.';
        }
    }

    // Validate doctor selection
    if (empty($formData['doctor_id'])) {
        $errors['doctor_id'] = 'Please select a doctor';
    } else {
        // Verify doctor_id exists and is a doctor role
        $checkDoctor = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = ?", [$formData['doctor_id'], ROLE_DOCTOR]);
        if (!$checkDoctor) {
            $errors['doctor_id'] = 'Invalid doctor selected.';
        }
    }
    
    // Validate appointment date
    if (empty($formData['appointment_date'])) {
        $errors['appointment_date'] = 'Please select an appointment date';
    } else {
        // Check if date is in the future
        $today = date('Y-m-d');
        if ($formData['appointment_date'] <= $today) {
            $errors['appointment_date'] = 'Appointment date must be in the future';
        }
        
        // Check if date is within allowed booking range
        $maxDate = date('Y-m-d', strtotime("+".ADVANCE_BOOKING_DAYS." days"));
        if ($formData['appointment_date'] > $maxDate) {
            $errors['appointment_date'] = 'Appointments can only be booked up to '.ADVANCE_BOOKING_DAYS.' days in advance';
        }
    }
    
    // Validate appointment time
    if (empty($formData['appointment_time'])) {
        $errors['appointment_time'] = 'Please select an appointment time';
    }
    
    // If no errors, book the appointment
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Check if the time slot is still available (critical for concurrency)
            $existingAppointment = $db->fetchOne(
                "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'",
                [$formData['doctor_id'], $formData['appointment_date'], $formData['appointment_time']]
            );
            
            if ($existingAppointment) {
                throw new Exception("This time slot has just been booked. Please select a different time.");
            }
            
            // Insert the appointment - set status to 'confirmed' directly
            $appointmentId = $db->insert(
                "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status) 
                 VALUES (?, ?, ?, ?, ?, 'confirmed')",
                [
                    $formData['patient_id'],
                    $formData['doctor_id'],
                    $formData['appointment_date'],
                    $formData['appointment_time'],
                    $formData['symptoms']
                ]
            );
            
            // Get patient and doctor names for notifications
            $patient = $db->fetchOne(
                "SELECT first_name, last_name FROM users WHERE id = ?",
                [$formData['patient_id']]
            );
            $doctor = $db->fetchOne(
                "SELECT first_name, last_name FROM users WHERE id = ?",
                [$formData['doctor_id']]
            );
            
            // Create notification for patient
            createNotification(
                $formData['patient_id'],
                "Your appointment with Dr. " . $doctor['first_name'] . " " . $doctor['last_name'] . " has been scheduled and confirmed for " . 
                formatDate($formData['appointment_date']) . " at " . formatTime($formData['appointment_time']) . " by an administrator."
            );
            
            // Create notification for doctor
            createNotification(
                $formData['doctor_id'],
                "A new appointment with patient " . $patient['first_name'] . " " . $patient['last_name'] . " has been scheduled and confirmed for " . 
                formatDate($formData['appointment_date']) . " at " . formatTime($formData['appointment_time']) . " by an administrator."
            );
            
            $db->commit();
            
            setFlashMessage('success', 'Appointment for ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' booked and confirmed successfully!', 'success');
            redirect('appointments.php');
        } catch (Exception $e) {
            $db->rollback();
            error_log("Admin appointment booking error: " . $e->getMessage());
            $errors['general'] = $e->getMessage();
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Book Appointment for Patient 📅</h1>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form id="admin_booking_form" method="post" action="book_appointment_for_patient.php">
                    <div class="mb-3">
                        <label for="patient_id" class="form-label">Select Patient*</label>
                        <select class="form-select <?php echo isset($errors['patient_id']) ? 'is-invalid' : ''; ?>" id="patient_id" name="patient_id" required>
                            <option value="">-- Select a patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $formData['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                                    <?php echo $patient['first_name'] . ' ' . $patient['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['patient_id'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['patient_id']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="doctor_id" class="form-label">Select Doctor*</label>
                        <select class="form-select <?php echo isset($errors['doctor_id']) ? 'is-invalid' : ''; ?>" id="doctor_id" name="doctor_id" required>
                            <option value="">-- Select a doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" <?php echo $formData['doctor_id'] == $doctor['id'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?> - <?php echo $doctor['specialization']; ?>  
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['doctor_id'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['doctor_id']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="appointment_date" class="form-label">Appointment Date*</label>
                        <input type="text" class="form-control datepicker <?php echo isset($errors['appointment_date']) ? 'is-invalid' : ''; ?>" 
                               id="appointment_date" name="appointment_date" value="<?php echo $formData['appointment_date']; ?>" 
                               placeholder="YYYY-MM-DD" required <?php echo (empty($formData['patient_id']) || empty($formData['doctor_id'])) ? 'disabled' : ''; ?>>
                        <?php if (isset($errors['appointment_date'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['appointment_date']; ?></div>
                        <?php endif; ?>
                        <div class="form-text">Appointments can be booked up to <?php echo ADVANCE_BOOKING_DAYS; ?> days in advance.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available Time Slots*</label>
                        <div id="time_slots" class="mb-2">
                            <p class="text-muted">Please select a patient, doctor, and date to see available time slots.</p>
                        </div>
                        <div id="time_slots_error" class="text-danger d-none"></div>
                        <input type="hidden" id="selected_time" name="selected_time" value="<?php echo $formData['appointment_time']; ?>">
                        <?php if (isset($errors['appointment_time'])): ?>
                            <div class="text-danger"><?php echo $errors['appointment_time']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="symptoms" class="form-label">Reason for Visit / Symptoms</label>
                        <textarea class="form-control" id="symptoms" name="symptoms" rows="3"><?php echo $formData['symptoms']; ?></textarea>
                        <div class="form-text">Briefly describe the symptoms or reason for the appointment.</div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Book Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Add custom script for date picker and slot loading
$advanceDays = ADVANCE_BOOKING_DAYS; // Assuming ADVANCE_BOOKING_DAYS is defined in config.php
$extraScripts = <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {
    const patientSelect = document.getElementById('patient_id');
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time_slots');
    const timeInput = document.getElementById('selected_time');
    const timeError = document.getElementById('time_slots_error');

    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        startDate: '+1d', // Appointments must be in the future
        endDate: '+{$advanceDays}d' // Limit booking to ADVANCE_BOOKING_DAYS
    });

    function toggleDateField() {
        // Enable date field only if both patient and doctor are selected
        if (doctorSelect.value && patientSelect.value) {
            dateInput.removeAttribute('disabled');
            \$(dateInput).datepicker('update'); // Force datepicker to re-evaluate its min/max dates
        } else {
            dateInput.setAttribute('disabled', 'disabled');
            dateInput.value = ''; // Clear date if doctor or patient is unselected
            timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a patient, doctor, and date to see available time slots.</p>';
            timeInput.value = '';
        }
    }

    function loadAvailableSlots() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;

        if (doctorId && date) {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Loading available time slots...</p>';
            timeError.classList.add('d-none'); // Hide previous error

            fetch('get_available_slots.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `doctor_id=\${doctorId}&date=\${date}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.slots.length > 0) {
                        let html = '<div class="btn-group w-100 flex-wrap" role="group">';
                        data.slots.forEach(slot => {
                            // Check if this slot was previously selected (in case of validation error)
                            const isSelected = timeInput.value === slot.value ? ' active' : '';
                            html += `<button type="button" class="btn btn-outline-primary m-1 slot-btn\${isSelected}" data-time="\${slot.value}">\${slot.display}</button>`;
                        });
                        html += '</div>';
                        timeSlotsContainer.innerHTML = html;

                        document.querySelectorAll('.slot-btn').forEach(btn => {
                            btn.addEventListener('click', function() {
                                document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
                                this.classList.add('active');
                                timeInput.value = this.getAttribute('data-time');
                            });
                        });
                    } else {
                        timeSlotsContainer.innerHTML = '<p class="text-muted">No available slots for this date.</p>';
                        timeInput.value = '';
                    }
                } else {
                    timeSlotsContainer.innerHTML = '';
                    timeError.textContent = data.message || 'Unable to load time slots.';
                    timeError.classList.remove('d-none');
                    timeInput.value = '';
                }
            })
            .catch(() => {
                timeSlotsContainer.innerHTML = '';
                timeError.textContent = 'Error loading time slots.';
                timeError.classList.remove('d-none');
                timeInput.value = '';
            });
        } else {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a patient, doctor, and date to see available time slots.</p>';
            timeInput.value = '';
        }
    }

    // Event listeners for changes in doctor, patient, and date selections
    patientSelect.addEventListener('change', function () {
        toggleDateField();
        // If doctor and date are already selected, reload slots
        if (doctorSelect.value && dateInput.value) {
            loadAvailableSlots();
        }
    });

    doctorSelect.addEventListener('change', function () {
        toggleDateField();
        // If patient and date are already selected, reload slots
        if (patientSelect.value && dateInput.value) {
            loadAvailableSlots();
        }
    });

    dateInput.addEventListener('change', loadAvailableSlots);

    // Initial call to set the correct state of the date field and load slots if data is pre-filled
    toggleDateField();
    if (doctorSelect.value && dateInput.value && patientSelect.value) {
        loadAvailableSlots();
    }
});
</script>
EOT;
include '../includes/footer.php';
?>

