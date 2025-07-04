<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?php 
            echo isLoggedIn()
                ? (hasRole(ROLE_PATIENT) 
                    ? BASE_URL . 'dashboard.php'
                    : (hasRole(ROLE_DOCTOR)
                        ? BASE_URL . 'dashboard.php'
                        : (hasRole(ROLE_ADMIN)
                            ? BASE_URL . 'dashboard.php'
                            : BASE_URL)))
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
                <?php 
                // Get the name of the current PHP file
                $currentPage = basename($_SERVER['PHP_SELF']); 
                ?>

                <?php if (isLoggedIn()): ?>
                    <?php // --- START LOGGED-IN USER NAVIGATION --- ?>

                    <?php // Always show a Home/Dashboard link for logged-in users ?>
                    <?php if (hasRole(ROLE_PATIENT) || hasRole(ROLE_DOCTOR)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" href="<?php 
                                echo hasRole(ROLE_PATIENT) 
                                    ? BASE_URL . 'dashboard.php' 
                                    : BASE_URL . 'dashboard.php'; 
                            ?>">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php // Role-specific navigation includes ?>
                    <?php if (hasRole(ROLE_PATIENT)): ?>
                        <?php include 'patient_nav.php'; ?>
                    <?php elseif (hasRole(ROLE_DOCTOR)): ?>
                        <?php include 'doctor_nav.php'; ?>
                    <?php elseif (hasRole(ROLE_ADMIN)): ?>
                        <?php include 'admin_nav.php'; ?>
                    <?php endif; ?>

                    <?php // --- END LOGGED-IN USER NAVIGATION --- ?>

                <?php else: ?>
                    <?php // --- START GUEST (NOT LOGGED-IN) NAVIGATION --- ?>

                    <?php // Only show Home and Contact Us on pages that are NOT login.php or register.php
                    if ($currentPage != 'login.php' && $currentPage != 'register.php'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>">
                               <i class="fas fa-home"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'contact.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>contact.php">
                                <i class="fas fa-envelope"></i> Contact Us
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php // --- END GUEST (NOT LOGGED-IN) NAVIGATION --- ?>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <?php if (hasRole(ROLE_PATIENT)): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">
                                        <i class="fas fa-id-card"></i> My Profile
                                    </a>
                                </li> 
                            <?php elseif (hasRole(ROLE_DOCTOR)): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">
                                        <i class="fas fa-id-card"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>availability.php">
                                        <i class="fas fa-calendar-alt"></i> My Availability
                                    </a>
                                </li>
                            <?php elseif (hasRole(ROLE_ADMIN)): ?>
                                
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'login.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'register.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
