<?php
// AJAX endpoint to get available time slots for a doctor on a given date
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
//if (!isLoggedIn() || !hasRole(ROLE_PATIENT)) {
//    echo json_encode([
//        'success' => false,
//        'message' => 'Unauthorized access'
//    ]);
//    exit;
//}
if (!isLoggedIn() || ($currentUserRole !== ROLE_PATIENT && $currentUserRole !== ROLE_ADMIN)) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. You must be a patient or an administrator to view time slots.'
    ]);
    exit;
}

// Check if required parameters are provided
if (empty($_POST['doctor_id']) || empty($_POST['date'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Doctor ID and date are required'
    ]);
    exit;
}

$doctorId = sanitize($_POST['doctor_id']);
$date = sanitize($_POST['date']);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format. Use YYYY-MM-DD.'
    ]);
    exit;
}

// Validate doctor exists
try {
    $db = Database::getInstance();
    $doctor = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = ?", [$doctorId, ROLE_DOCTOR]);
    
    if (!$doctor) {
        echo json_encode([
            'success' => false,
            'message' => 'Doctor not found'
        ]);
        exit;
    }
    
    // Get available time slots
    $availableSlots = getAvailableTimeSlots($doctorId, $date);
    
    echo json_encode([
        'success' => true,
        'slots' => $availableSlots
    ]);
} catch (Exception $e) {
    error_log("Error getting available slots: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving available time slots. Please try again later.'
    ]);
}
?>

