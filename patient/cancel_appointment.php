<?php
// Cancel Appointment Page
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
requireLogin(ROLE_PATIENT, BASE_URL . 'login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('error', 'Invalid appointment ID.', 'danger');
    redirect('manage_appointments.php');
}

$appointmentId = (int) $_GET['id'];

try {
    // Get the appointment details
    $appointment = $db->fetchOne(
        "SELECT a.*, u.first_name, u.last_name 
         FROM appointments a 
         JOIN users u ON a.doctor_id = u.id 
         WHERE a.id = ? AND a.patient_id = ?",
        [$appointmentId, $userId]
    );
    
    // Check if appointment exists and belongs to the current user
    if (!$appointment) {
        setFlashMessage('error', 'Appointment not found or you do not have permission to cancel it.', 'danger');
        redirect('manage_appointments.php');
    }
    
    // Check if appointment can be cancelled (not already cancelled or completed)
    if ($appointment['status'] === 'cancelled') {
        setFlashMessage('info', 'This appointment is already cancelled.', 'info');
        redirect('manage_appointments.php');
    }
    
    if ($appointment['status'] === 'completed') {
        setFlashMessage('error', 'Completed appointments cannot be cancelled.', 'danger');
        redirect('manage_appointments.php');
    }
    
    // Check if appointment is within cancellation deadline
    $appointmentDateTime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    $appointmentTimestamp = strtotime($appointmentDateTime);
    $cancellationDeadline = time() + (CANCEL_DEADLINE_HOURS * 3600);
    
    $lateCancellation = $appointmentTimestamp < $cancellationDeadline;
    
    // Update appointment status to cancelled
    $db->beginTransaction();
    
    $db->update(
        "UPDATE appointments SET status = 'cancelled', updated_at = datetime('now') WHERE id = ?",
        [$appointmentId]
    );
    
    // Create notification for doctor
    createNotification(
        $appointment['doctor_id'],
        "Appointment scheduled for " . formatDate($appointment['appointment_date']) . " at " . 
        formatTime($appointment['appointment_time']) . " has been cancelled by the patient."
    );
    
    // Create notification for patient
    createNotification(
        $userId,
        "Your appointment with Dr. " . $appointment['first_name'] . " " . $appointment['last_name'] . 
        " on " . formatDate($appointment['appointment_date']) . " at " . formatTime($appointment['appointment_time']) . 
        " has been cancelled."
    );
    
    $db->commit();
    
    // Show appropriate message based on cancellation timing
    if ($lateCancellation) {
        setFlashMessage(
            'warning', 
            'Your appointment has been cancelled. Please note that cancellations made less than ' . 
            CANCEL_DEADLINE_HOURS . ' hours before the scheduled time may be subject to a cancellation fee.', 
            'warning'
        );
    } else {
        setFlashMessage('success', 'Your appointment has been cancelled successfully.', 'success');
    }
    
    redirect('manage_appointments.php');
} catch (Exception $e) {
    $db->rollback();
    error_log("Appointment cancellation error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while cancelling the appointment. Please try again.', 'danger');
    redirect('manage_appointments.php');
}
?>

