<?php
// Login page
$pageTitle = 'Login';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (hasRole(ROLE_PATIENT)) {
        redirect(BASE_URL . 'patient/dashboard.php');
    } elseif (hasRole(ROLE_DOCTOR)) {
        redirect(BASE_URL . 'doctor/dashboard.php');
    } elseif (hasRole(ROLE_ADMIN)) {
        redirect(BASE_URL . 'admin/dashboard.php');
    }
}

$errors = [];
$username = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username)) {
        $errors['username'] = 'Username or email is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        $result = loginUser($username, $password);
        
        if ($result['success']) {
            // Redirect based on user role
            if (hasRole(ROLE_PATIENT)) {
                redirect(BASE_URL . 'patient/dashboard.php');
            } elseif (hasRole(ROLE_DOCTOR)) {
                redirect(BASE_URL . 'doctor/dashboard.php');
            } elseif (hasRole(ROLE_ADMIN)) {
                redirect(BASE_URL . 'admin/dashboard.php');
            } else {
                redirect(BASE_URL . 'index.php');
            }
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card auth-card">
            <div class="card-body">
                <h2 class="auth-title">Login to Your Account</h2>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                <?php endif; ?>
                
                <form id="login_form" method="post" action="<?php echo BASE_URL; ?>login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo $username; ?>" required>
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                        <a href="#" class="float-end">Forgot password?</a>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

