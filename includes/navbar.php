<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?php 
            echo (isLoggedIn() && hasRole(ROLE_PATIENT)) 
                ? BASE_URL . 'dashboard.php' 
                : BASE_URL; 
        ?>">
            <i class="fas fa-hospital-alt me-2"></i>
            <?php echo SITE_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (!isLoggedIn() || !hasRole(ROLE_PATIENT)): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isLoggedIn()): ?>
                    <?php // Navigation for logged-in users (Patient, Doctor, Admin) ?>
                    <?php if (hasRole(ROLE_PATIENT)): ?>
                        <?php include 'patient_nav.php'; ?>
                    <?php elseif (hasRole(ROLE_DOCTOR)): ?>
                        <?php include 'doctor_nav.php'; ?>
                    <?php elseif (hasRole(ROLE_ADMIN)): ?>
                        <?php include 'admin_nav.php'; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php // Navigation for users NOT logged in ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>contact.php">
                            <i class="fas fa-envelope"></i> Contact Us
                        </a>
                    </li>
                    <?php // "Services" and "Our Doctors" links are removed for non-logged-in users ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); // Good practice: escape output ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <?php if (hasRole(ROLE_PATIENT)): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>patient/profile.php">
                                        <i class="fas fa-id-card"></i> My Profile
                                    </a>
                                </li> 
                            <?php elseif (hasRole(ROLE_DOCTOR)): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>doctor/profile.php">
                                        <i class="fas fa-id-card"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>doctor/availability.php">
                                        <i class="fas fa-calendar-alt"></i> My Availability
                                    </a>
                                </li>
                            <?php elseif (hasRole(ROLE_ADMIN)): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/profile.php">
                                        <i class="fas fa-id-card"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/settings.php">
                                        <i class="fas fa-cog"></i> System Settings
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <?php // Login and Register links for users NOT logged in ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
