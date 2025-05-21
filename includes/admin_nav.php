<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/users.php">
        <i class="fas fa-users"></i> Manage Users
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/appointments.php">
        <i class="fas fa-calendar-alt"></i> All Appointments
    </a>
</li>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/reports.php">
        <i class="fas fa-chart-bar"></i> Reports
    </a>
</li>

