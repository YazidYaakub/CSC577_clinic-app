<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>doctor/dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>doctor/appointments.php">
        <i class="fas fa-calendar-day"></i> Appointments
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'availability.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>doctor/availability.php">
        <i class="fas fa-clock"></i> Manage Availability
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patient_records.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>doctor/patient_records.php">
        <i class="fas fa-user-injured"></i> Patient Records
    </a>
</li>

