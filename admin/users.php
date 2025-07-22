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
$newUser = []; // Initialize newUser array to store form data on failed submission

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
    } else {
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$newUser['email']]);
        if ($existingUser) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    if (empty($newUser['first_name']) || empty($newUser['last_name'])) {
        $errors['name'] = 'First and last name are required.';
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $newUser['password'])) {
        $errors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.';
    }

    if ($newUser['password'] !== $newUser['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (!in_array($newUser['role'], [ROLE_PATIENT, ROLE_DOCTOR, ROLE_ADMIN])) {
        $errors['role'] = 'Invalid user role.';
    }

    // Insert user if no errors
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $deleteUserId = (int) $_POST['delete_user'];
    try {
        // Add cascading deletes or checks if necessary (e.g., appointments)
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
        $doctorDetails = null;
        if ($user['role'] === ROLE_DOCTOR) {
            $doctorDetails = $db->fetchOne(
                "SELECT * FROM doctor_details WHERE user_id = ?",
                [$specificUserId]
            );
        }

        // Get appointments count
        $appointmentsCount = null;
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

        // Process user update
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

            if ($user['role'] === ROLE_DOCTOR) {
                $formData['specialization'] = sanitize($_POST['specialization'] ?? '');
                $formData['qualification'] = sanitize($_POST['qualification'] ?? '');
                $formData['experience_years'] = (int)($_POST['experience_years'] ?? 0);
                $formData['consultation_fee'] = (float)($_POST['consultation_fee'] ?? 0);
            }
            $formData['role'] = $user['role']; // Keep original role

            // Validation for update
            if (empty($formData['email'])) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            } else {
                $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$formData['email'], $specificUserId]);
                if ($existingUser) {
                    $errors['email'] = 'This email is already in use by another account.';
                }
            }

            if (empty($formData['first_name'])) $errors['first_name'] = 'First name is required';
            if (empty($formData['last_name'])) $errors['last_name'] = 'Last name is required';

            if (empty($errors)) {
                try {
                    $result = updateProfile($specificUserId, $formData);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                        // Refresh data after update
                        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$specificUserId]);
                        if ($user['role'] === ROLE_DOCTOR) {
                            $doctorDetails = $db->fetchOne("SELECT * FROM doctor_details WHERE user_id = ?", [$specificUserId]);
                        }
                    } else {
                        $errors['general'] = $result['message'];
                    }
                } catch (Exception $e) {
                    error_log("User update error: " . $e->getMessage());
                    $errors['general'] = 'An error occurred while updating the user.';
                }
            }
        }

        // Process password reset
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $newPassword)) {
                $errors['new_password'] = 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.';
            }
            if ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match';
            }

            if (empty($errors)) {
                try {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->update("UPDATE users SET password = ? WHERE id = ?", [$passwordHash, $specificUserId]);
                    $successMessage = 'Password reset successfully.';
                    createNotification($specificUserId, "Your password has been reset by an administrator.");
                } catch (Exception $e) {
                    error_log("Password reset error: " . $e->getMessage());
                    $errors['general'] = 'An error occurred while resetting the password.';
                }
            }
        }
    } else {
        // Build query for users list
        $query = "SELECT u.*, d.specialization
                  FROM users u
                  LEFT JOIN doctor_details d ON u.id = d.user_id
                  WHERE 1=1 ";
        $params = [];

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
    $users = [];
    $user = null;
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($viewingSpecific && $user): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>User Details</h1>
                <a href="users.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Users List
                </a>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-user text-primary me-2"></i> User Information</h5>
                            <span class="badge bg-<?php echo $user['role'] === ROLE_PATIENT ? 'info' : ($user['role'] === ROLE_DOCTOR ? 'success' : 'secondary'); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $specificUserId; ?>">
                                <button type="submit" name="update_user" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Update User</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    </div>
            </div>

        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-plus text-primary me-2"></i> Add New User</h5>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm" aria-expanded="<?php echo (isset($_POST['create_user']) && !empty($errors)) ? 'true' : 'false'; ?>" aria-controls="addUserForm">
                        Add New User
                    </button>
                </div>
                <div id="addUserForm" class="collapse <?php echo (isset($_POST['create_user']) && !empty($errors)) ? 'show' : ''; ?>">
                    <div class="card-body">
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                        <?php endif; ?>
                        <form method="post" action="users.php">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="add-username" class="form-label">Username*</label>
                                    <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="add-username" name="username" value="<?php echo htmlspecialchars($newUser['username'] ?? '', ENT_QUOTES); ?>" required>
                                    <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?php echo $errors['username']; ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="add-email" class="form-label">Email*</label>
                                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="add-email" name="email" value="<?php echo htmlspecialchars($newUser['email'] ?? '', ENT_QUOTES); ?>" required>
                                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="add-role" class="form-label">Role*</label>
                                    <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" name="role" id="add-role" required>
                                        <option value="">Select role</option>
                                        <option value="patient" <?php echo (isset($newUser['role']) && $newUser['role'] == 'patient') ? 'selected' : ''; ?>>Patient</option>
                                        <option value="doctor" <?php echo (isset($newUser['role']) && $newUser['role'] == 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                                        <option value="admin" <?php echo (isset($newUser['role']) && $newUser['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <?php if (isset($errors['role'])): ?><div class="invalid-feedback"><?php echo $errors['role']; ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="add-first-name" class="form-label">First Name*</label>
                                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="add-first-name" name="first_name" value="<?php echo htmlspecialchars($newUser['first_name'] ?? '', ENT_QUOTES); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="add-last-name" class="form-label">Last Name*</label>
                                    <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="add-last-name" name="last_name" value="<?php echo htmlspecialchars($newUser['last_name'] ?? '', ENT_QUOTES); ?>" required>
                                    <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?php echo $errors['name']; ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="add-password" class="form-label">Password*</label>
                                    <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="add-password" name="password" required>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                    <?php else: ?>
                                        <div class="form-text">8+ characters, with uppercase, lowercase, number & special character.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="add-confirm-password" class="form-label">Confirm Password*</label>
                                    <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="add-confirm-password" name="confirm_password" required>
                                    <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-4 d-grid">
                                <button type="submit" name="create_user" class="btn btn-success"><i class="fas fa-user-plus me-2"></i> Create User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Manage Users</h1>
            </div>
            <?php endif; ?>
    </div>
</div>

<script>
    // Your JavaScript function like toggleDoctorFields if needed
</script>

<?php include '../includes/footer.php'; ?>
