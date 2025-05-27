<?php
// Admin User Management
$pageTitle = 'Manage Users';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is an admin
requireLogin(ROLE_ADMIN, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Get view parameters
$viewMode = sanitize($_GET['view'] ?? 'all');
$searchTerm = sanitize($_GET['search'] ?? '');
$viewingSpecific = isset($_GET['id']) && is_numeric($_GET['id']);
$specificUserId = $viewingSpecific ? (int) $_GET['id'] : 0;

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $deleteUserId = (int) $_POST['delete_user'];
    try {
        $db->delete("DELETE FROM users WHERE id = ?", [$deleteUserId]);
        setFlashMessage('success', 'User deleted successfully.', 'success');
        redirect('users.php');
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        setFlashMessage('error', 'Failed to delete user.', 'danger');
        redirect('users.php');
    }
}

try {
    if ($viewingSpecific) {
        // Get specific user
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$specificUserId]);
        
        if (!$user) {
            setFlashMessage('error', 'User not found.', 'danger');
            redirect('users.php');
        }
        
        // Get additional details based on role
        if ($user['role'] === ROLE_DOCTOR) {
            $doctorDetails = $db->fetchOne(
                "SELECT * FROM doctor_details WHERE user_id = ?",
                [$specificUserId]
            );
        }
        
        // Get appointments count
        if ($user['role'] === ROLE_PATIENT) {
            $appointmentsCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?",
                [$specificUserId]
            );
        } elseif ($user['role'] === ROLE_DOCTOR) {
            $appointmentsCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?",
                [$specificUserId]
            );
        }
        
        // Process user update if form is submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
            $formData = [
                'email' => sanitize($_POST['email'] ?? ''),
                'first_name' => sanitize($_POST['first_name'] ?? ''),
                'last_name' => sanitize($_POST['last_name'] ?? ''),
                'phone' => sanitize($_POST['phone'] ?? ''),
                'address' => sanitize($_POST['address'] ?? ''),
                'gender' => sanitize($_POST['gender'] ?? ''),
                'date_of_birth' => sanitize($_POST['date_of_birth'] ?? '')
            ];
            
            // Add doctor-specific fields if applicable
            if ($user['role'] === ROLE_DOCTOR) {
                $formData['specialization'] = sanitize($_POST['specialization'] ?? '');
                $formData['qualification'] = sanitize($_POST['qualification'] ?? '');
                $formData['experience_years'] = (int) sanitize($_POST['experience_years'] ?? 0);
                $formData['consultation_fee'] = (float) sanitize($_POST['consultation_fee'] ?? 0);
            }
            
            // Add role to formData
            $formData['role'] = $user['role'];
            
            // Validate email
            if (empty($formData['email'])) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            
            // Validate first name
            if (empty($formData['first_name'])) {
                $errors['first_name'] = 'First name is required';
            }
            
            // Validate last name
            if (empty($formData['last_name'])) {
                $errors['last_name'] = 'Last name is required';
            }
            
            // Validate doctor-specific fields
            if ($user['role'] === ROLE_DOCTOR) {
                if (empty($formData['specialization'])) {
                    $errors['specialization'] = 'Specialization is required';
                }
                
                if (empty($formData['qualification'])) {
                    $errors['qualification'] = 'Qualification is required';
                }
            }
            
            // If no errors, update user
            if (empty($errors)) {
                try {
                    $result = updateProfile($specificUserId, $formData);
                    
                    if ($result['success']) {
                        $successMessage = $result['message'];
                        
                        // Refresh user data
                        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$specificUserId]);
                        
                        if ($user['role'] === ROLE_DOCTOR) {
                            $doctorDetails = $db->fetchOne(
                                "SELECT * FROM doctor_details WHERE user_id = ?",
                                [$specificUserId]
                            );
                        }
                    } else {
                        $errors['general'] = $result['message'];
                    }
                } catch (Exception $e) {
                    error_log("User update error: " . $e->getMessage());
                    $errors['general'] = 'An error occurred while updating the user. Please try again.';
                }
            }
        }
        
        // Process password reset if requested
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
            $newPassword = sanitize($_POST['new_password'] ?? '');
            $confirmPassword = sanitize($_POST['confirm_password'] ?? '');
            
            // Validate password
            if (empty($newPassword)) {
                $errors['new_password'] = 'New password is required';
            } elseif (strlen($newPassword) < 8) {
                $errors['new_password'] = 'New password must be at least 8 characters';
            }
            
            // Validate confirm password
            if ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match';
            }
            
            // If no errors, reset password
            if (empty($errors)) {
                try {
                    // Hash new password
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update password
                    $updated = $db->update(
                        "UPDATE users SET password = ? WHERE id = ?",
                        [$passwordHash, $specificUserId]
                    );
                    
                    if ($updated) {
                        $successMessage = 'Password reset successfully.';
                        
                        // Create notification for user
                        createNotification(
                            $specificUserId,
                            "Your password has been reset by an administrator. Please use your new password to login."
                        );
                    } else {
                        $errors['general'] = 'Failed to reset password.';
                    }
                } catch (Exception $e) {
                    error_log("Password reset error: " . $e->getMessage());
                    $errors['general'] = 'An error occurred while resetting the password. Please try again.';
                }
            }
        }
    } else {
        // Build query for users list
        //$query = "SELECT u.*, ";
        
        // Add doctor details subquery if needed
        //if ($viewMode === 'doctors') {
            //$query .= "(SELECT specialization FROM doctor_details WHERE user_id = u.id) as specialization ";
        //} else {
            //$query .= "NULL as specialization ";
        //}
        $query = "SELECT u.*, d.specialization
                  FROM users u
                  LEFT JOIN doctor_details d ON u.id = d.user_id
                  WHERE 1=1 ";
        
        //$query .= "FROM users u WHERE 1=1 ";
        
        $params = [];
        
        // Apply role filter
        if ($viewMode === 'patients') {
            $query .= "AND u.role = ? ";
            $params[] = ROLE_PATIENT;
        } elseif ($viewMode === 'doctors') {
            $query .= "AND u.role = ? ";
            $params[] = ROLE_DOCTOR;
        } elseif ($viewMode === 'admins') {
            $query .= "AND u.role = ? ";
            $params[] = ROLE_ADMIN;
        }
        
        // Apply search if provided
        if (!empty($searchTerm)) {
            $query .= "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ?) ";
            $searchParam = "%$searchTerm%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        $query .= "ORDER BY u.created_at DESC";
        
        $users = $db->fetchAll($query, $params);
    }
} catch (Exception $e) {
    error_log("User management error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving user data.', 'danger');
    
    // Set defaults
    $users = [];
    $user = null;
    $doctorDetails = [];
    $appointmentsCount = ['count' => 0];
}
// Add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $newUser = [
        'username' => sanitize($_POST['username'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'role' => sanitize($_POST['role'] ?? '')
    ];

    // Validation
    if (empty($newUser['username'])) {
        $errors['username'] = 'Username is required.';
    }
    if (!filter_var($newUser['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required.';
    }
    if (empty($newUser['first_name']) || empty($newUser['last_name'])) {
        $errors['name'] = 'First and last name are required.';
    }
    if (strlen($newUser['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($newUser['password'] !== $newUser['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (!in_array($newUser['role'], [ROLE_PATIENT, ROLE_DOCTOR, ROLE_ADMIN])) {
        $errors['role'] = 'Invalid user role.';
    }

    // Insert user
    if (empty($errors)) {
        try {
            $passwordHash = password_hash($newUser['password'], PASSWORD_DEFAULT);
            $db->insert(
                "INSERT INTO users (username, email, password, first_name, last_name, role, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))",
                [
                    $newUser['username'],
                    $newUser['email'],
                    $passwordHash,
                    $newUser['first_name'],
                    $newUser['last_name'],
                    $newUser['role']
                ]
            );
            setFlashMessage('success', 'New user account created.', 'success');
            redirect('users.php');
        } catch (Exception $e) {
            error_log("Add user error: " . $e->getMessage());
            $errors['general'] = 'Failed to create new user.';
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($viewingSpecific): ?>
            <!-- Single User View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>User Details</h1>
                <a href="users.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Users List
                </a>
            </div>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger">
                    <?php echo $errors['general']; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <!-- User Information -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-user text-primary me-2"></i>
                                User Information
                            </h5>
                            <span class="badge bg-<?php 
                                echo $user['role'] === ROLE_PATIENT ? 'info' : 
                                    ($user['role'] === ROLE_DOCTOR ? 'success' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $specificUserId; ?>">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email*</label>
                                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name*</label>
                                        <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                                        <?php if (isset($errors['first_name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Last Name*</label>
                                        <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                                        <?php if (isset($errors['last_name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>">
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="text" class="form-control datepicker-dob" id="date_of_birth" name="date_of_birth" value="<?php echo $user['date_of_birth']; ?>" placeholder="YYYY-MM-DD">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select gender</option>
                                            <option value="male" <?php echo $user['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $user['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo $user['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo $user['address']; ?></textarea>
                                </div>
                                
                                <?php if ($user['role'] === ROLE_DOCTOR && isset($doctorDetails)): ?>
                                    <hr>
                                    <h5 class="mb-3">Doctor Details</h5>
                                    
                                    <div class="mb-3">
                                        <label for="specialization" class="form-label">Specialization*</label>
                                        <input type="text" class="form-control <?php echo isset($errors['specialization']) ? 'is-invalid' : ''; ?>" id="specialization" name="specialization" value="<?php echo $doctorDetails['specialization']; ?>" required>
                                        <?php if (isset($errors['specialization'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['specialization']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="qualification" class="form-label">Qualification*</label>
                                        <textarea class="form-control <?php echo isset($errors['qualification']) ? 'is-invalid' : ''; ?>" id="qualification" name="qualification" rows="3" required><?php echo $doctorDetails['qualification']; ?></textarea>
                                        <?php if (isset($errors['qualification'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['qualification']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="experience_years" class="form-label">Years of Experience</label>
                                            <input type="number" class="form-control" id="experience_years" name="experience_years" value="<?php echo $doctorDetails['experience_years']; ?>" min="0">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="consultation_fee" class="form-label">Consultation Fee ($)</label>
                                            <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" value="<?php echo $doctorDetails['consultation_fee']; ?>" min="0" step="0.01">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid mt-4">
                                    <input type="hidden" name="update_user" value="1">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Update User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Account Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                Account Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
                            <p><strong>Username:</strong> <?php echo $user['username']; ?></p>
                            <p><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></p>
                            <p><strong>Registered On:</strong> <?php echo formatDate($user['created_at'], 'F j, Y g:i A'); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo formatDate($user['updated_at'], 'F j, Y g:i A'); ?></p>
                            
                            <?php if (isset($appointmentsCount)): ?>
                                <p>
                                    <strong>Total Appointments:</strong> 
                                    <?php echo number_format($appointmentsCount['count']); ?>
                                    
                                    <?php if ($appointmentsCount['count'] > 0): ?>
                                        <?php if ($user['role'] === ROLE_PATIENT): ?>
                                            <a href="appointments.php?patient_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                View Appointments
                                            </a>
                                        <?php elseif ($user['role'] === ROLE_DOCTOR): ?>
                                            <a href="appointments.php?doctor_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary ms-2">
                                                View Appointments
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Password Reset -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-key text-primary me-2"></i>
                                Reset Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $specificUserId; ?>">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password*</label>
                                    <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password" required>
                                    <?php if (isset($errors['new_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                    <?php endif; ?>
                                    <div class="form-text">Password must be at least 8 characters and include uppercase, lowercase, number, and special character.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password*</label>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-grid">
                                    <input type="hidden" name="reset_password" value="1">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to reset this user\'s password?');">
                                        <i class="fas fa-key me-2"></i> Reset Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cogs text-primary me-2"></i>
                                Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="appointments.php?<?php echo $user['role'] === ROLE_PATIENT ? 'patient_id=' : 'doctor_id='; ?><?php echo $user['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-calendar-alt me-2"></i> 
                                    <?php echo $user['role'] === ROLE_PATIENT ? 'View Patient Appointments' : 'View Doctor Appointments'; ?>
                                </a>
                                <form method="post" class="d-grid" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                    <input type="hidden" name="delete_user" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fas fa-trash-alt me-2"></i> Delete User
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="if(confirm('Are you sure you want to deactivate this account? This action cannot be undone.')) alert('Account deactivation feature will be implemented in future updates.');">
                                    <i class="fas fa-user-slash me-2"></i> Deactivate Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- All Users View --> 
            <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-user-plus text-primary me-2"></i> Add New User
        </h5>
        <button class="btn btn-sm btn-outline-primary"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#addUserForm"
                aria-expanded="false"
                aria-controls="addUserForm">
            Add New User
        </button>
    </div>
    <div id="addUserForm" class="collapse">
        <div class="card-body"><form method="post" action="">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Username*</label>
            <input type="text" class="form-control" name="username" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Email*</label>
            <input type="email" class="form-control" name="email" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Role*</label>
            <select class="form-select" name="role" id="roleSelect" required onchange="toggleDoctorFields(this.value)">
                <option value="">Select role</option>
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">First Name*</label>
            <input type="text" class="form-control" name="first_name" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Last Name*</label>
            <input type="text" class="form-control" name="last_name" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Password*</label>
            <input type="password" class="form-control" name="password" required>
            <div class="form-text text-muted">
                Password must be at least 8 characters and include uppercase, lowercase, number, and special character.
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Confirm Password*</label>
            <input type="password" class="form-control" name="confirm_password" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="tel" class="form-control" name="phone">
        </div>
        <div class="col-md-6">
            <label class="form-label">Date of Birth</label>
            <input type="date" class="form-control" name="date_of_birth">
        </div>
        <div class="col-md-6">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender">
                <option value="">Select gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Address</label>
            <textarea class="form-control" name="address" rows="2"></textarea>
        </div>

        <!-- Doctor fields -->
        <div id="doctorFields" class="row g-3" style="display: none;">
            <div class="col-md-6">
                <label class="form-label">Specialization*</label>
                <input type="text" class="form-control" name="specialization">
            </div>
            <div class="col-md-6">
                <label class="form-label">Qualification*</label>
                <input type="text" class="form-control" name="qualification">
            </div>
            <div class="col-md-6">
                <label class="form-label">Years of Experience</label>
                <input type="number" class="form-control" name="experience_years" min="0">
            </div>
            <div class="col-md-6">
                <label class="form-label">Consultation Fee (RM)</label>
                <input type="number" class="form-control" name="consultation_fee" min="0" step="0.01">
            </div>
        </div>
    </div>

    <div class="mt-4 d-grid">
        <button type="submit" name="create_user" class="btn btn-success">
            <i class="fas fa-user-plus me-2"></i> Create User
        </button>
    </div>
</form>
</div>
    </div>
</div>      
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Manage Users</h1>
            </div>
            
            <!-- Search and Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $searchTerm; ?>" placeholder="Search by name, email, phone, or username...">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="view" name="view" onchange="this.form.submit()">
                                <option value="all" <?php echo $viewMode === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="patients" <?php echo $viewMode === 'patients' ? 'selected' : ''; ?>>Patients Only</option>
                                <option value="doctors" <?php echo $viewMode === 'doctors' ? 'selected' : ''; ?>>Doctors Only</option>
                                <option value="admins" <?php echo $viewMode === 'admins' ? 'selected' : ''; ?>>Admins Only</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-primary me-2"></i>
                        <?php
                            if ($viewMode === 'patients') {
                                echo 'Patients';
                            } elseif ($viewMode === 'doctors') {
                                echo 'Doctors';
                            } elseif ($viewMode === 'admins') {
                                echo 'Administrators';
                            } else {
                                echo 'All Users';
                            }
                        ?>
                        <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Contact</th>
                                        <?php if ($viewMode === 'doctors' || $viewMode === 'all'): ?>
                                            <th>Specialization</th>
                                        <?php endif; ?>
                                        <th>Registered</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                            <td><?php echo $user['username']; ?></td>
                                            <td>
                                                <?php if (!empty($user['email'])): ?>
                                                    <div><i class="fas fa-envelope text-muted me-1"></i> <?php echo $user['email']; ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($user['phone'])): ?>
                                                    <div><i class="fas fa-phone text-muted me-1"></i> <?php echo $user['phone']; ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($viewMode === 'doctors' || $viewMode === 'all'): ?>
                                                <td>
                                                    <?php if ($user['role'] === ROLE_DOCTOR): ?>
                                                        <?php echo $user['specialization'] ?? 'N/A'; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td><?php echo formatDate($user['created_at'], 'M j, Y'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['role'] === ROLE_PATIENT ? 'info' : 
                                                        ($user['role'] === ROLE_DOCTOR ? 'success' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="users.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="appointments.php?<?php echo $user['role'] === ROLE_PATIENT ? 'patient_id=' : 'doctor_id='; ?><?php echo $user['id']; ?>" class="btn btn-outline-info">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No users found matching your criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDoctorFields(role) {
    const docFields = document.getElementById('doctorFields');
    docFields.style.display = role === 'doctor' ? 'flex' : 'none';
}
</script>

<?php include '../includes/footer.php'; ?>

