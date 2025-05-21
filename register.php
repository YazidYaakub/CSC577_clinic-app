<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Registration page
$pageTitle = 'Register';
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
$formData = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'role' => '',
    'phone' => '',
    'gender' => '',
    'date_of_birth' => '',
    'address' => '',
    'specialization' => '',
    'qualification' => '',
    'experience_years' => '',
    'consultation_fee' => ''
];

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $formData = [
        'username' => sanitize($_POST['username'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'role' => sanitize($_POST['role'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'date_of_birth' => sanitize($_POST['date_of_birth'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'specialization' => sanitize($_POST['specialization'] ?? ''),
        'qualification' => sanitize($_POST['qualification'] ?? ''),
        'experience_years' => sanitize($_POST['experience_years'] ?? ''),
        'consultation_fee' => sanitize($_POST['consultation_fee'] ?? '')
    ];
    
    // Server-side validation
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($formData['username']) < 4) {
        $errors['username'] = 'Username must be at least 4 characters';
    }
    
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($formData['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    if ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    if (empty($formData['role'])) {
        $errors['role'] = 'Role is required';
    } elseif (!in_array($formData['role'], [ROLE_PATIENT, ROLE_DOCTOR])) {
        $errors['role'] = 'Invalid role selected';
    }
    
    // Doctor-specific validations
    if ($formData['role'] === ROLE_DOCTOR) {
        if (empty($formData['specialization'])) {
            $errors['specialization'] = 'Specialization is required for doctors';
        }
        
        if (empty($formData['qualification'])) {
            $errors['qualification'] = 'Qualification is required for doctors';
        }
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $result = registerUser($formData);
        
        if ($result['success']) {
            setFlashMessage('success', $result['message'], 'success');
            redirect(BASE_URL . 'login.php');
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card auth-card">
            <div class="card-body">
                <h2 class="auth-title">Create an Account</h2>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                <?php endif; ?>
                
                <form id="register_form" method="post" action="<?php echo BASE_URL; ?>register.php">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name*</label>
                            <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo $formData['first_name']; ?>" required>
                            <?php if (isset($errors['first_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name*</label>
                            <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo $formData['last_name']; ?>" required>
                            <?php if (isset($errors['last_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username*</label>
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo $formData['username']; ?>" required>
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email*</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $formData['email']; ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password*</label>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                            <div class="form-text">Password must be at least 8 characters and include uppercase, lowercase, number, and special character.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password*</label>
                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Register as*</label>
                        <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" id="role" name="role" required>
                            <option value="" selected disabled>Select role</option>
                            <option value="<?php echo ROLE_PATIENT; ?>" <?php echo $formData['role'] === ROLE_PATIENT ? 'selected' : ''; ?>>Patient</option>
                            <option value="<?php echo ROLE_DOCTOR; ?>" <?php echo $formData['role'] === ROLE_DOCTOR ? 'selected' : ''; ?>>Doctor</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['role']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $formData['phone']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="text" class="form-control datepicker-dob" id="date_of_birth" name="date_of_birth" value="<?php echo $formData['date_of_birth']; ?>" placeholder="YYYY-MM-DD">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">Select gender</option>
                            <option value="male" <?php echo $formData['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $formData['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $formData['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo $formData['address']; ?></textarea>
                    </div>
                    
                    <!-- Doctor-specific fields -->
                    <div id="doctor_fields" class="<?php echo $formData['role'] === ROLE_DOCTOR ? '' : 'd-none'; ?>">
                        <hr>
                        <h5>Doctor Information</h5>
                        
                        <div class="mb-3">
                            <label for="specialization" class="form-label">Specialization*</label>
                            <input type="text" class="form-control <?php echo isset($errors['specialization']) ? 'is-invalid' : ''; ?>" id="specialization" name="specialization" value="<?php echo $formData['specialization']; ?>">
                            <?php if (isset($errors['specialization'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['specialization']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification*</label>
                            <textarea class="form-control <?php echo isset($errors['qualification']) ? 'is-invalid' : ''; ?>" id="qualification" name="qualification" rows="2"><?php echo $formData['qualification']; ?></textarea>
                            <?php if (isset($errors['qualification'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['qualification']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="experience_years" name="experience_years" value="<?php echo $formData['experience_years']; ?>" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="consultation_fee" class="form-label">Consultation Fee ($)</label>
                                <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" value="<?php echo $formData['consultation_fee']; ?>" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>*</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Terms and conditions content -->
                <h6>1. Acceptance of Terms</h6>
                <p>By accessing and using the Healthcare Appointment System, you agree to be bound by these Terms and Conditions and our Privacy Policy.</p>
                
                <h6>2. User Accounts</h6>
                <p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                
                <h6>3. Appointment Booking</h6>
                <p>Appointments are subject to doctor availability. Cancellations must be made at least 24 hours in advance.</p>
                
                <h6>4. Medical Information</h6>
                <p>The system stores medical information for healthcare purposes. This information is protected and handled according to applicable healthcare privacy laws.</p>
                
                <h6>5. Limitation of Liability</h6>
                <p>We strive to provide accurate information, but we cannot guarantee the accuracy or completeness of any information on the system.</p>
                
                <h6>6. Modifications</h6>
                <p>We reserve the right to modify these terms at any time. Continued use of the system after such changes constitutes acceptance of the new terms.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Privacy policy content -->
                <h6>1. Information We Collect</h6>
                <p>We collect personal and medical information necessary for healthcare services, including name, contact details, medical history, and appointment details.</p>
                
                <h6>2. How We Use Your Information</h6>
                <p>We use your information to manage appointments, provide healthcare services, communicate with you, and improve our system.</p>
                
                <h6>3. Information Security</h6>
                <p>We implement appropriate security measures to protect your information from unauthorized access, alteration, or disclosure.</p>
                
                <h6>4. Information Sharing</h6>
                <p>We share your information only with healthcare professionals involved in your care and as required by law.</p>
                
                <h6>5. Your Rights</h6>
                <p>You have the right to access, correct, and delete your personal information, subject to legal limitations.</p>
                
                <h6>6. Changes to Privacy Policy</h6>
                <p>We may update our privacy policy. We will notify you of any changes by posting the new policy on this page.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

