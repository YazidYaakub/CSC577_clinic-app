<?php
/**
 * Authentication functions for the MySihat Appointment System
 */

require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

/**
 * Register a new user
 * @param array $userData - User information
 * @return array - Status and message
 */
function registerUser($userData) {
    $db = Database::getInstance();
    
    // Validate required fields
    $requiredFields = ['username', 'password', 'email', 'first_name', 'last_name', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($userData[$field])) {
            return [
                'success' => false,
                'message' => "Field '$field' is required"
            ];
        }
    }
    
    // Validate email
    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format'
        ];
    }
    
    // Check if username already exists
    $user = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$userData['username']]);
    if ($user) {
        return [
            'success' => false,
            'message' => 'Username already exists'
        ];
    }
    
    // Check if email already exists
    $user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$userData['email']]);
    if ($user) {
        return [
            'success' => false,
            'message' => 'Email already exists'
        ];
    }
    
    // Hash password
    $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    try {
        $db->beginTransaction();
        
        // Insert user
        $userId = $db->insert(
            "INSERT INTO users (username, password, email, first_name, last_name, role, phone, address, date_of_birth, gender) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userData['username'],
                $passwordHash,
                $userData['email'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['role'],
                $userData['phone'] ?? null,
                $userData['address'] ?? null,
                $userData['date_of_birth'] ?? null,
                $userData['gender'] ?? null
            ]
        );
        
        // If registering a doctor, add doctor-specific details
        if ($userData['role'] === ROLE_DOCTOR && isset($userData['specialization']) && isset($userData['qualification'])) {
            $db->insert(
                "INSERT INTO doctor_details (user_id, specialization, qualification, experience_years, consultation_fee) 
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $userId,
                    $userData['specialization'],
                    $userData['qualification'],
                    $userData['experience_years'] ?? 0,
                    $userData['consultation_fee'] ?? 0
                ]
            );
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Registration successful. You can now login.',
            'user_id' => $userId
        ];
    } catch (Exception $e) {
        $db->rollback();
        error_log("Registration error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Registration failed. Please try again later.'
        ];
    }
}

/**
 * Authenticate a user
 * @param string $username - Username or email
 * @param string $password - Password
 * @return array - Status, message and user data if successful
 */
function loginUser($username, $password) {
    $db = Database::getInstance();
    
    // Check if input is email or username
    $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    
    // Get user from database
    $user = $db->fetchOne("SELECT * FROM users WHERE $field = ?", [$username]);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    // Store user data in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

/**
 * Log out the current user
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

/**
 * Require user to be logged in to access a page
 * @param array $allowedRoles - Optional array of roles that can access the page
 * @param string $redirectUrl - URL to redirect to if not authorized
 */
function requireLogin($allowedRoles = null, $redirectUrl = BASE_URL . 'login.php') {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please log in to access this page.', 'warning');
        redirect($redirectUrl);
    }
    
    // If roles are specified, check if user has one of the roles
    if ($allowedRoles !== null) {
        if (!hasRole($allowedRoles)) {
            setFlashMessage('error', 'You do not have permission to access this page.', 'danger');
            redirect($redirectUrl);
        }
    }
}

/**
 * Update user password
 * @param int $userId - User ID
 * @param string $currentPassword - Current password
 * @param string $newPassword - New password
 * @return array - Status and message
 */
function updatePassword($userId, $currentPassword, $newPassword) {
    $db = Database::getInstance();
    
    // Get user from database
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'User not found'
        ];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        return [
            'success' => false,
            'message' => 'Current password is incorrect'
        ];
    }
    
    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updated = $db->update(
        "UPDATE users SET password = ? WHERE id = ?",
        [$passwordHash, $userId]
    );
    
    if ($updated) {
        return [
            'success' => true,
            'message' => 'Password updated successfully'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to update password'
    ];
}

/**
 * Update user profile
 * @param int $userId - User ID
 * @param array $userData - User data to update
 * @return array - Status and message
 */
function updateProfile($userId, $userData) {
    $db = Database::getInstance();
    
    try {
        $db->beginTransaction();
        
        // Update user table
        $updatedUser = $db->update(
            "UPDATE users SET 
             email = ?, 
             first_name = ?, 
             last_name = ?, 
             phone = ?, 
             address = ?, 
             date_of_birth = ?, 
             gender = ? 
             WHERE id = ?",
            [
                $userData['email'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['phone'] ?? null,
                $userData['address'] ?? null,
                $userData['date_of_birth'] ?? null,
                $userData['gender'] ?? null,
                $userId
            ]
        );
        
        // If user is a doctor, update doctor details
        if (isset($userData['role']) && $userData['role'] === ROLE_DOCTOR) {
            // Check if doctor details already exist
            $doctorDetails = $db->fetchOne("SELECT id FROM doctor_details WHERE user_id = ?", [$userId]);
            
            if ($doctorDetails) {
                // Update existing doctor details
                $db->update(
                    "UPDATE doctor_details SET 
                     specialization = ?, 
                     qualification = ?, 
                     experience_years = ?, 
                     consultation_fee = ? 
                     WHERE user_id = ?",
                    [
                        $userData['specialization'],
                        $userData['qualification'],
                        $userData['experience_years'] ?? 0,
                        $userData['consultation_fee'] ?? 0,
                        $userId
                    ]
                );
            } else {
                // Insert new doctor details
                $db->insert(
                    "INSERT INTO doctor_details (user_id, specialization, qualification, experience_years, consultation_fee) 
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $userData['specialization'],
                        $userData['qualification'],
                        $userData['experience_years'] ?? 0,
                        $userData['consultation_fee'] ?? 0
                    ]
                );
            }
        }
        
        $db->commit();
        
        // Update session data if needed
        if (getCurrentUserId() === $userId) {
            $_SESSION['full_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
        }
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully'
        ];
    } catch (Exception $e) {
        $db->rollback();
        error_log("Profile update error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Profile update failed. Please try again later.'
        ];
    }
}
//

