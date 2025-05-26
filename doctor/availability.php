<?php
// Doctor Availability Management
$pageTitle = 'Manage Availability';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a doctor
requireLogin(ROLE_DOCTOR, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

$errors = [];
$successMessage = '';

// Get current availability
try {
    $availability = $db->fetchAll(
        "SELECT * FROM doctor_availability WHERE doctor_id = ?", 
        //ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')",
        [$userId]
    );
    // Define standard order
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // Reorder fetched rows manually
    usort($availability, function ($a, $b) use ($daysOfWeek) {
        return array_search($a['day_of_week'], $daysOfWeek) <=> array_search($b['day_of_week'], $daysOfWeek);
    });
    
    // Create an array for easier access
    $availabilityByDay = [];
    foreach ($availability as $slot) {
        $availabilityByDay[$slot['day_of_week']] = $slot;
    }
    
    // Days of week
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
} catch (Exception $e) {
    error_log("Availability error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving your availability schedule.', 'danger');
    $availability = [];
    $availabilityByDay = [];
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Delete existing availability
        $db->delete("DELETE FROM doctor_availability WHERE doctor_id = ?", [$userId]);
        
        // Insert new availability for each day
        foreach ($daysOfWeek as $day) {
            $isAvailable = isset($_POST["available_$day"]) ? 1 : 0;
            
            if ($isAvailable) {
                $startTime = sanitize($_POST["start_time_$day"] ?? '09:00:00');
                $endTime = sanitize($_POST["end_time_$day"] ?? '17:00:00');
                
                // Validate time inputs
                if (empty($startTime) || empty($endTime)) {
                    throw new Exception("Start and end time are required for $day");
                }
                
                // Ensure end time is after start time
                if ($startTime >= $endTime) {
                    throw new Exception("End time must be after start time for $day");
                }
                
                // Insert new availability
                $db->insert(
                    "INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) 
                     VALUES (?, ?, ?, ?, ?)",
                    [$userId, $day, $startTime, $endTime, $isAvailable]
                );
            } else {
                // Insert as not available
                $db->insert(
                    "INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) 
                     VALUES (?, ?, '00:00:00', '00:00:00', ?)",
                    [$userId, $day, $isAvailable]
                );
            }
        }
        
        $db->commit();
        
        setFlashMessage('success', 'Availability schedule updated successfully.', 'success');
        redirect($_SERVER['PHP_SELF']); // Redirect to refresh the page
    } catch (Exception $e) {
        $db->rollback();
        error_log("Availability update error: " . $e->getMessage());
        $errors['general'] = $e->getMessage();
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Availability</h1>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $errors['general']; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock text-primary me-2"></i>
                    Set Your Weekly Schedule
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Use this form to set your regular weekly availability. Patients will only be able to book appointments during these hours.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered availability-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Day</th>
                                    <th>Available</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daysOfWeek as $day): ?>
                                    <?php 
                                    $dayAvailable = isset($availabilityByDay[$day]) && $availabilityByDay[$day]['is_available'] == 1;
                                    $startTime = $dayAvailable ? substr($availabilityByDay[$day]['start_time'], 0, 5) : '09:00';
                                    $endTime = $dayAvailable ? substr($availabilityByDay[$day]['end_time'], 0, 5) : '17:00';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $day; ?></strong></td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input availability-toggle" type="checkbox" 
                                                       id="available_<?php echo $day; ?>" 
                                                       name="available_<?php echo $day; ?>" 
                                                       data-day-id="<?php echo $day; ?>"
                                                       <?php echo $dayAvailable ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="available_<?php echo $day; ?>">
                                                    <?php echo $dayAvailable ? 'Available' : 'Not Available'; ?>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div id="time_range_<?php echo $day; ?>" class="<?php echo $dayAvailable ? '' : 'd-none'; ?>">
                                                <input type="time" class="form-control" 
                                                       id="start_time_<?php echo $day; ?>" 
                                                       name="start_time_<?php echo $day; ?>" 
                                                       value="<?php echo $startTime; ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div id="time_range_<?php echo $day; ?>" class="<?php echo $dayAvailable ? '' : 'd-none'; ?>">
                                                <input type="time" class="form-control" 
                                                       id="end_time_<?php echo $day; ?>" 
                                                       name="end_time_<?php echo $day; ?>" 
                                                       value="<?php echo $endTime; ?>">
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Availability
                        </button>
                        <button type="reset" class="btn btn-secondary ms-2">
                            <i class="fas fa-undo me-2"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    Availability Guidelines
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Set your regular working hours for each day of the week.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Appointments will be scheduled in <?php echo APPOINTMENT_DURATION_MINUTES; ?>-minute intervals during your available hours.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        You can update your availability at any time, but changes won't affect existing appointments.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        For specific date unavailability (like vacations), please contact the administrator.
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Ensure your availability allows for enough appointment slots based on expected patient demand.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

