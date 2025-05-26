<?php
/**
 * Update Appointment Status AJAX Handler
 * Handles AJAX requests to update appointment status
 */

header('Content-Type: application/json');

require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';
require_once 'auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
$newStatus = isset($_POST['status']) ? sanitize($_POST['status']) : '';

// Validate inputs
if ($appointmentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

$validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $db = Database::getInstance();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();
    
    // Get appointment details to verify permissions
    $appointment = $db->fetchOne(
        "SELECT * FROM appointments WHERE id = ?",
        [$appointmentId]
    );
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    
    // Check permissions based on user role
    $canUpdate = false;
    
    if ($userRole === ROLE_ADMIN) {
        // Admins can update any appointment
        $canUpdate = true;
    } elseif ($userRole === ROLE_DOCTOR && $appointment['doctor_id'] == $userId) {
        // Doctors can update their own appointments
        $canUpdate = true;
    } elseif ($userRole === ROLE_PATIENT && $appointment['patient_id'] == $userId) {
        // Patients can only cancel their own appointments
        $canUpdate = ($newStatus === 'cancelled');
    }
    
    if (!$canUpdate) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this appointment']);
        exit;
    }
    
    // Additional validation for status transitions
    $currentStatus = $appointment['status'];
    
    // Prevent certain status changes
    if ($currentStatus === 'completed' && $newStatus !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Cannot change status of completed appointment']);
        exit;
    }
    
    if ($currentStatus === 'cancelled' && $newStatus !== 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Cannot change status of cancelled appointment']);
        exit;
    }
    
    // Check if appointment date has passed for certain status changes
    $appointmentDate = $appointment['appointment_date'];
    $currentDate = getCurrentDate();
    
    if ($appointmentDate < $currentDate && $newStatus === 'confirmed') {
        echo json_encode(['success' => false, 'message' => 'Cannot confirm past appointments']);
        exit;
    }
    
    // Update the appointment status
    $updated = $db->update(
        "UPDATE appointments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$newStatus, $appointmentId]
    );
    
    if ($updated) {
        // Create notifications based on status change
        $message = '';
        $notifyUserId = null;
        
        switch ($newStatus) {
            case 'confirmed':
                if ($userRole === ROLE_DOCTOR) {
                    $message = "Your appointment on " . formatDate($appointment['appointment_date']) . " at " . formatTime($appointment['appointment_time']) . " has been confirmed.";
                    $notifyUserId = $appointment['patient_id'];
                }
                break;
                
            case 'cancelled':
                if ($userRole === ROLE_DOCTOR) {
                    $message = "Your appointment on " . formatDate($appointment['appointment_date']) . " at " . formatTime($appointment['appointment_time']) . " has been cancelled by the doctor.";
                    $notifyUserId = $appointment['patient_id'];
                } elseif ($userRole === ROLE_PATIENT) {
                    $message = "Patient has cancelled their appointment on " . formatDate($appointment['appointment_date']) . " at " . formatTime($appointment['appointment_time']) . ".";
                    $notifyUserId = $appointment['doctor_id'];
                }
                break;
                
            case 'completed':
                $message = "Your appointment on " . formatDate($appointment['appointment_date']) . " at " . formatTime($appointment['appointment_time']) . " has been marked as completed.";
                $notifyUserId = $appointment['patient_id'];
                break;
        }
        
        // Send notification if applicable
        if ($notifyUserId && !empty($message)) {
            createNotification($notifyUserId, $message);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment status updated successfully',
            'new_status' => $newStatus,
            'status_badge' => getStatusBadge($newStatus)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment status']);
    }
    
} catch (Exception $e) {
    error_log("Update appointment status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the appointment']);
}

/**
 * Get Bootstrap badge class for status
 */
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">Pending</span>';
        case 'confirmed':
            return '<span class="badge bg-success">Confirmed</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">Cancelled</span>';
        case 'completed':
            return '<span class="badge bg-secondary">Completed</span>';
        default:
            return '<span class="badge bg-light text-dark">' . ucfirst($status) . '</span>';
    }
}
?>
