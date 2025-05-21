<?php
/**
 * Common functions for the MySihat Appointment System
 */

// Load database connection
require_once 'config.php';
require_once 'db.php';

/**
 * Sanitize user input
 * @param string $data - Input data to sanitize
 * @return string - Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to a specified page
 * @param string $location - URL to redirect to
 */
//function redirect($location) {
//    header("Location: $location");
//    exit;
//}
function redirect($location) {
    // Automatically prefix relative paths with BASE_URL
    if (strpos($location, 'http') !== 0 && strpos($location, BASE_URL) !== 0) {
        $location = BASE_URL . ltrim($location, '/');
    }
    header("Location: $location");
    exit;
}

/**
 * Set a flash message to be displayed once
 * @param string $name - Message identifier
 * @param string $message - The message content
 * @param string $type - Message type (success, error, warning, info)
 */
function setFlashMessage($name, $message, $type = 'info') {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][$name] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and display flash messages
 * @param string $name - Message identifier (optional, display all if null)
 * @return string - HTML for the flash messages
 */
function getFlashMessage($name = null) {
    if (!isset($_SESSION['flash_messages'])) {
        return '';
    }
    
    $output = '';
    
    if ($name !== null) {
        if (isset($_SESSION['flash_messages'][$name])) {
            $message = $_SESSION['flash_messages'][$name];
            $output .= '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">';
            $output .= $message['message'];
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $output .= '</div>';
            unset($_SESSION['flash_messages'][$name]);
        }
    } else {
        foreach ($_SESSION['flash_messages'] as $name => $message) {
            $output .= '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">';
            $output .= $message['message'];
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $output .= '</div>';
            unset($_SESSION['flash_messages'][$name]);
        }
    }
    
    return $output;
}

/**
 * Check if user is logged in
 * @return bool - True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null - User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 * @return string|null - User role if logged in, null otherwise
 */
function getCurrentUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Check if current user has a specific role
 * @param string|array $roles - Role(s) to check
 * @return bool - True if user has the role, false otherwise
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}

/**
 * Get current user information
 * @return array|null - User data if logged in, null otherwise
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = Database::getInstance();
    return $db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Format date for display
 * @param string $date - MySQL date string
 * @param string $format - PHP date format (default: 'M j, Y')
 * @return string - Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 * @param string $time - MySQL time string
 * @param string $format - PHP date format (default: 'g:i A')
 * @return string - Formatted time
 */
function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}

/**
 * Get current date in database format (YYYY-MM-DD)
 * For SQLite compatibility (replacement for MySQL's CURDATE())
 * @return string - Current date in YYYY-MM-DD format
 */
function getCurrentDate() {
    return date('Y-m-d');
}

/**
 * Get available time slots for a doctor on a given date
 * @param int $doctorId - Doctor user ID
 * @param string $date - Date in Y-m-d format
 * @return array - Available time slots
 */
function getAvailableTimeSlots($doctorId, $date) {
    $db = Database::getInstance();
    $dayOfWeek = date('l', strtotime($date)); // Get day name (Monday, Tuesday, etc.)
    
    // Get doctor's availability for the day
    $availability = $db->fetchOne(
        "SELECT * FROM doctor_availability 
         WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1",
        [$doctorId, $dayOfWeek]
    );
    
    if (!$availability) {
        return []; // Doctor not available on this day
    }
    
    // Get existing appointments for the doctor on this date
    $bookedSlots = $db->fetchAll(
        "SELECT appointment_time FROM appointments 
         WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'",
        [$doctorId, $date]
    );
    
    $bookedTimes = [];
    foreach ($bookedSlots as $slot) {
        $bookedTimes[] = $slot['appointment_time'];
    }
    
    // Generate available time slots based on start/end times and appointment duration
    $startTime = strtotime($availability['start_time']);
    $endTime = strtotime($availability['end_time']);
    $slotDuration = APPOINTMENT_DURATION_MINUTES * 60; // Convert minutes to seconds
    
    $availableSlots = [];
    for ($time = $startTime; $time < $endTime; $time += $slotDuration) {
        $timeStr = date('H:i:s', $time);
        
        // Skip if the slot is already booked
        if (!in_array($timeStr, $bookedTimes)) {
            $availableSlots[] = [
                'value' => $timeStr,
                'display' => date('g:i A', $time)
            ];
        }
    }
    
    return $availableSlots;
}

/**
 * Send email notification
 * Note: This function will need PHPMailer library to work properly
 * @param string $to - Recipient email
 * @param string $subject - Email subject
 * @param string $body - Email body content (HTML)
 * @return bool - True if email sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body) {
    try {
        // This is a placeholder. In a real implementation, PHPMailer would be used.
        // For now, we'll just log the email and return true
        error_log("Email to: $to, Subject: $subject, Body: $body");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification in the database
 * @param int $userId - User ID to notify
 * @param string $message - Notification message
 * @return int|false - Notification ID on success, false on failure
 */
function createNotification($userId, $message) {
    $db = Database::getInstance();
    try {
        return $db->insert(
            "INSERT INTO notifications (user_id, message) VALUES (?, ?)",
            [$userId, $message]
        );
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}
?>

