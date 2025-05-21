<?php
// Doctor Dashboard
$pageTitle = 'Doctor Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is a doctor
requireLogin(ROLE_DOCTOR, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

try {
    // Get doctor details
    $doctorDetails = $db->fetchOne(
        "SELECT d.*, u.first_name, u.last_name, u.email, u.phone 
         FROM doctor_details d 
         JOIN users u ON d.user_id = u.id 
         WHERE d.user_id = ?",
        [$userId]
    );
    
    // Get current date for SQLite
    $currentDate = getCurrentDate();
    
    // Get today's appointments
    $todayAppointments = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name, u.phone, u.email 
         FROM appointments a 
         JOIN users u ON a.patient_id = u.id 
         WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status IN ('confirmed', 'pending') 
         ORDER BY a.appointment_time",
        [$userId, $currentDate]
    );
    
    // Get upcoming appointments (excluding today)
    $upcomingAppointments = $db->fetchAll(
        "SELECT a.*, u.first_name, u.last_name 
         FROM appointments a 
         JOIN users u ON a.patient_id = u.id 
         WHERE a.doctor_id = ? AND a.appointment_date > ? AND a.status IN ('confirmed', 'pending') 
         ORDER BY a.appointment_date, a.appointment_time 
         LIMIT 5",
        [$userId, $currentDate]
    );
    
    // Get appointment statistics
    $stats = [
        'today' => $db->fetchOne(
            "SELECT COUNT(*) as count FROM appointments 
             WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'",
            [$userId, $currentDate]
        ),
        'tomorrow' => $db->fetchOne(
            "SELECT COUNT(*) as count FROM appointments 
             WHERE doctor_id = ? AND appointment_date = date(?, '+1 day') AND status != 'cancelled'",
            [$userId, $currentDate]
        ),
        'this_week' => $db->fetchOne(
            "SELECT COUNT(*) as count FROM appointments 
             WHERE doctor_id = ? AND appointment_date >= ? AND appointment_date <= date(?, '+6 days') AND status != 'cancelled'",
            [$userId, $currentDate, $currentDate]
        ),
        'pending' => $db->fetchOne(
            "SELECT COUNT(*) as count FROM appointments 
             WHERE doctor_id = ? AND status = 'pending'",
            [$userId]
        )
    ];
    
    // Get notifications
    $notifications = $db->fetchAll(
        "SELECT * FROM notifications 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 5",
        [$userId]
    );
} catch (Exception $e) {
    error_log("Doctor dashboard error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading dashboard data.', 'danger');
    
    // Set defaults
    $doctorDetails = [];
    $todayAppointments = [];
    $upcomingAppointments = [];
    $stats = [
        'today' => ['count' => 0],
        'tomorrow' => ['count' => 0],
        'this_week' => ['count' => 0],
        'pending' => ['count' => 0]
    ];
    $notifications = [];
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Doctor Dashboard</h1>
        
        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Today's Appointments</h5>
                        <div class="stat-value"><?php echo $stats['today']['count']; ?></div>
                        <p class="card-text">Scheduled for today</p>
                        <i class="fas fa-calendar-day stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Tomorrow</h5>
                        <div class="stat-value"><?php echo $stats['tomorrow']['count']; ?></div>
                        <p class="card-text">Appointments for tomorrow</p>
                        <i class="fas fa-calendar-alt stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">This Week</h5>
                        <div class="stat-value"><?php echo $stats['this_week']['count']; ?></div>
                        <p class="card-text">Total for current week</p>
                        <i class="fas fa-calendar-week stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-warning text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Pending Approvals</h5>
                        <div class="stat-value"><?php echo $stats['pending']['count']; ?></div>
                        <p class="card-text">Awaiting confirmation</p>
                        <i class="fas fa-hourglass-half stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Today's Appointments -->
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-day text-primary me-2"></i>
                            Today's Appointments
                        </h5>
                        <span class="badge bg-primary"><?php echo count($todayAppointments); ?> Appointment(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($todayAppointments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Patient</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todayAppointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                                                <td>
                                                    <?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?>
                                                    <?php if (!empty($appointment['phone'])): ?>
                                                        <br><small><i class="fas fa-phone-alt"></i> <?php echo $appointment['phone']; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $appointment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($appointment['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-outline-success" 
                                                                    onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <a href="add_medical_record.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-outline-info">
                                                            <i class="fas fa-notes-medical"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <p>You don't have any appointments scheduled for today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="appointments.php" class="btn btn-outline-primary btn-sm">View All Appointments</a>
                    </div>
                </div>
            </div>
            
            <!-- Notifications and Profile Summary -->
            <div class="col-md-5">
                <!-- Doctor Profile Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-md text-primary me-2"></i>
                            Profile Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-3">Dr. <?php echo $doctorDetails['first_name'] . ' ' . $doctorDetails['last_name']; ?></h4>
                        <p><strong>Specialization:</strong> <?php echo $doctorDetails['specialization']; ?></p>
                        <p><strong>Qualification:</strong> <?php echo $doctorDetails['qualification']; ?></p>
                        <p><strong>Experience:</strong> <?php echo $doctorDetails['experience_years']; ?> years</p>
                        <p><strong>Consultation Fee:</strong> $<?php echo number_format($doctorDetails['consultation_fee'], 2); ?></p>
                        
                        <div class="mt-3">
                            <a href="profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                            <a href="availability.php" class="btn btn-outline-success btn-sm">Manage Availability</a>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bell text-primary me-2"></i>
                            Recent Notifications
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($notifications) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <p class="mb-1"><?php echo $notification['message']; ?></p>
                                            <small><?php echo formatDate($notification['created_at'], 'M j, g:i A'); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <p>You don't have any notifications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upcoming Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>
                            Upcoming Appointments
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($upcomingAppointments) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($upcomingAppointments as $appointment): ?>
                                    <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $appointment['first_name'] . ' ' . $appointment['last_name']; ?></h6>
                                            <small>
                                                <span class="badge bg-<?php echo $appointment['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <i class="fas fa-calendar-day text-primary me-1"></i>
                                            <?php echo formatDate($appointment['appointment_date']); ?>
                                            <i class="fas fa-clock text-primary ms-2 me-1"></i>
                                            <?php echo formatTime($appointment['appointment_time']); ?>
                                        </p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                                <p>You don't have any upcoming appointments.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Add custom scripts
$extraScripts = <<<EOT
<script>
    // Function to update appointment status
    function updateStatus(appointmentId, status) {
        if (confirm('Are you sure you want to ' + (status === 'confirmed' ? 'confirm' : 'update') + ' this appointment?')) {
            $.ajax({
                url: '../includes/update_appointment_status.php',
                method: 'POST',
                data: { appointment_id: appointmentId, status: status },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appointment status updated successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to update appointment status. Please try again.');
                }
            });
        }
    }
</script>
EOT;

include '../includes/footer.php';
?>

