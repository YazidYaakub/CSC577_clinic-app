<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>patient/dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'book_appointment.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>patient/book_appointment.php">
        <i class="fas fa-calendar-plus"></i> Book Appointment
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_appointments.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>patient/manage_appointments.php">
        <i class="fas fa-calendar-check"></i> My Appointments
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'medical_history.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>patient/medical_history.php">
        <i class="fas fa-notes-medical"></i> Medical History
    </a>
</li>

