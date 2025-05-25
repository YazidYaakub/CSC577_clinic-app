<?php
// Book Appointment Page
$pageTitle = 'Book Appointment';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
requireLogin(ROLE_PATIENT, BASE_URL . 'login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

$errors = [];
$formData = [
    'doctor_id' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'symptoms' => ''
];

// Get list of doctors
try {
    $doctors = $db->fetchAll(
        "SELECT u.id, u.first_name, u.last_name, d.specialization, d.consultation_fee 
         FROM users u 
         JOIN doctor_details d ON u.id = d.user_id 
         WHERE u.role = ? 
         ORDER BY u.first_name, u.last_name",
        [ROLE_DOCTOR]
    );
} catch (Exception $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
    $doctors = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $formData = [
        'doctor_id' => sanitize($_POST['doctor_id'] ?? ''),
        'appointment_date' => sanitize($_POST['appointment_date'] ?? ''),
        'appointment_time' => sanitize($_POST['selected_time'] ?? ''),
        'symptoms' => sanitize($_POST['symptoms'] ?? '')
    ];
    
    // Validate doctor selection
    if (empty($formData['doctor_id'])) {
        $errors['doctor_id'] = 'Please select a doctor';
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
            
            // Check if the time slot is still available
            $existingAppointment = $db->fetchOne(
                "SELECT id FROM appointments 
                 WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'",
                [$formData['doctor_id'], $formData['appointment_date'], $formData['appointment_time']]
            );
            
            if ($existingAppointment) {
                throw new Exception("This time slot has just been booked by another patient. Please select a different time.");
            }
            
            // Insert the appointment
            $appointmentId = $db->insert(
                "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms, status) 
                 VALUES (?, ?, ?, ?, ?, 'pending')",
                [
                    $userId,
                    $formData['doctor_id'],
                    $formData['appointment_date'],
                    $formData['appointment_time'],
                    $formData['symptoms']
                ]
            );
            
            // Get doctor name for notification
            $doctor = $db->fetchOne(
                "SELECT first_name, last_name FROM users WHERE id = ?",
                [$formData['doctor_id']]
            );
            
            // Create notification for doctor
            createNotification(
                $formData['doctor_id'],
                "New appointment scheduled for " . formatDate($formData['appointment_date']) . " at " . formatTime($formData['appointment_time'])
            );
            
            // Create notification for patient
            createNotification(
                $userId,
                "Your appointment with Dr. " . $doctor['first_name'] . " " . $doctor['last_name'] . " has been scheduled for " . 
                formatDate($formData['appointment_date']) . " at " . formatTime($formData['appointment_time'])
            );
            
            $db->commit();
            
            setFlashMessage('success', 'Appointment booked successfully! Your appointment is pending confirmation.', 'success');
            redirect('manage_appointments.php');
        } catch (Exception $e) {
            $db->rollback();
            error_log("Appointment booking error: " . $e->getMessage());
            $errors['general'] = $e->getMessage();
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Book an Appointment</h1>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form id="booking_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
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
                               placeholder="YYYY-MM-DD" required <?php echo empty($formData['doctor_id']) ? 'disabled' : ''; ?>>
                        <?php if (isset($errors['appointment_date'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['appointment_date']; ?></div>
                        <?php endif; ?>
                        <div class="form-text">Appointments can be booked up to <?php echo ADVANCE_BOOKING_DAYS; ?> days in advance.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available Time Slots*</label>
                        <div id="time_slots" class="mb-2">
                            <p class="text-muted">Please select a doctor and date to see available time slots.</p>
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
                        <div class="form-text">Briefly describe your symptoms or reason for the appointment. This helps the doctor prepare for your visit.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">I understand that I should arrive 15 minutes before my scheduled appointment time*</label>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Book Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Add custom script
$advanceDays = ADVANCE_BOOKING_DAYS;
$extraScripts = <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time_slots');
    const timeInput = document.getElementById('selected_time');
    const timeError = document.getElementById('time_slots_error');

    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        startDate: '+1d',
        endDate: '+{$advanceDays}d'
    });

    function toggleDateField() {
        if (doctorSelect.value) {
            dateInput.removeAttribute('disabled');
            \$(dateInput).datepicker('update');
        } else {
            dateInput.setAttribute('disabled', 'disabled');
            dateInput.value = '';
            timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a doctor and date to see available time slots.</p>';
            timeInput.value = '';
        }
    }

    function loadAvailableSlots() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;

        if (doctorId && date) {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Loading available time slots...</p>';
            timeError.classList.add('d-none');

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
                            html += `<button type="button" class="btn btn-outline-primary m-1 slot-btn" data-time="\${slot.value}">\${slot.display}</button>`;
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
            });
        }
    }

    doctorSelect.addEventListener('change', function () {
        toggleDateField();
        timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a doctor and date to see available time slots.</p>';
        timeInput.value = '';
    });

    dateInput.addEventListener('change', loadAvailableSlots);

    toggleDateField(); // Initial
});
</script>
EOT;
include '../includes/footer.php';
?>

