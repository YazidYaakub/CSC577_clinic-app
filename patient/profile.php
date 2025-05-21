<?php
// Patient Profile Page
$pageTitle = 'My Profile';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a patient
requireLogin(ROLE_PATIENT, BASE_URL . 'login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

$errors = [];
$successMessage = '';

// Get user data
try {
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while retrieving your profile.', 'danger');
    redirect('../index.php');
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Collect and sanitize form data
    $formData = [
        'email' => sanitize($_POST['email'] ?? ''),
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'address' => sanitize($_POST['address'] ?? '')
    ];
    
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
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            $result = updateProfile($userId, $formData);
            
            if ($result['success']) {
                $successMessage = $result['message'];
                
                // Refresh user data
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            } else {
                $errors['general'] = $result['message'];
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while updating your profile. Please try again.';
        }
    }
}

// Process password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate current password
    if (empty($currentPassword)) {
        $errors['current_password'] = 'Current password is required';
    }
    
    // Validate new password
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters';
    }
    
    // Validate confirm password
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // If no errors, update password
    if (empty($errors)) {
        try {
            $result = updatePassword($userId, $currentPassword, $newPassword);
            
            if ($result['success']) {
                $successMessage = $result['message'];
            } else {
                $errors['general'] = $result['message'];
            }
        } catch (Exception $e) {
            error_log("Password update error: " . $e->getMessage());
            $errors['general'] = 'An error occurred while updating your password. Please try again.';
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">My Profile</h1>
        
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
            <!-- Profile Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user text-primary me-2"></i>
                            Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" disabled>
                                <div class="form-text">Username cannot be changed.</div>
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
                            
                            <div class="d-grid">
                                <input type="hidden" name="update_profile" value="1">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lock text-primary me-2"></i>
                            Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password*</label>
                                <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" id="current_password" name="current_password" required>
                                <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password*</label>
                                <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" id="new_password" name="new_password" required>
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                <?php else: ?>
                                    <div class="form-text">Password must be at least 8 characters and include uppercase, lowercase, number, and special character.</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password*</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid">
                                <input type="hidden" name="update_password" value="1">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Account Type:</strong> Patient</p>
                        <p><strong>Registered On:</strong> <?php echo formatDate($user['created_at'], 'F j, Y'); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo formatDate($user['updated_at'], 'F j, Y g:i A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

