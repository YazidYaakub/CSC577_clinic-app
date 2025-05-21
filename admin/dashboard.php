<?php
// Admin Dashboard
$pageTitle = 'Admin Dashboard';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is an admin
requireLogin(ROLE_ADMIN, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

try {
    // Get system stats
    $stats = [
        'patients' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_PATIENT]),
        'doctors' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_DOCTOR]),
        'appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments"),
        'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'")
    ];
    
    // Get today's appointments
    $todayAppointments = $db->fetchAll(
        "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name, 
                d.first_name as doctor_first_name, d.last_name as doctor_last_name 
         FROM appointments a 
         JOIN users p ON a.patient_id = p.id 
         JOIN users d ON a.doctor_id = d.id 
         WHERE a.appointment_date = CURDATE() 
         ORDER BY a.appointment_time"
    );
    
    // Get recent registrations
    $recentUsers = $db->fetchAll(
        "SELECT * FROM users 
         ORDER BY created_at DESC 
         LIMIT 10"
    );
    
    // Get appointment stats by status
    $appointmentStats = [
        'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'"),
        'confirmed' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'confirmed'"),
        'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'"),
        'cancelled' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'")
    ];
    
    // Get appointment stats by month for the current year
    $currentYear = date('Y');
    $monthlyStats = $db->fetchAll(
        "SELECT MONTH(appointment_date) as month, COUNT(*) as count 
         FROM appointments 
         WHERE YEAR(appointment_date) = ? 
         GROUP BY MONTH(appointment_date) 
         ORDER BY month",
        [$currentYear]
    );
    
    // Format monthly stats for JavaScript chart
    $chartMonths = [];
    $chartData = [];
    
    for ($i = 1; $i <= 12; $i++) {
        $chartMonths[] = date('M', mktime(0, 0, 0, $i, 1));
        $chartData[] = 0; // Default to 0
    }
    
    foreach ($monthlyStats as $stat) {
        $monthIndex = $stat['month'] - 1; // Arrays are 0-indexed
        $chartData[$monthIndex] = (int) $stat['count'];
    }
    
    // Convert to JSON for JavaScript
    $chartMonthsJson = json_encode($chartMonths);
    $chartDataJson = json_encode($chartData);
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading dashboard data.', 'danger');
    
    // Set defaults
    $stats = [
        'patients' => ['count' => 0],
        'doctors' => ['count' => 0],
        'appointments' => ['count' => 0],
        'completed' => ['count' => 0]
    ];
    $todayAppointments = [];
    $recentUsers = [];
    $appointmentStats = [
        'pending' => ['count' => 0],
        'confirmed' => ['count' => 0],
        'completed' => ['count' => 0],
        'cancelled' => ['count' => 0]
    ];
    $chartMonthsJson = json_encode([]);
    $chartDataJson = json_encode([]);
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Admin Dashboard</h1>
        
        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Patients</h5>
                        <div class="stat-value"><?php echo number_format($stats['patients']['count']); ?></div>
                        <p class="card-text">Registered users</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Doctors</h5>
                        <div class="stat-value"><?php echo number_format($stats['doctors']['count']); ?></div>
                        <p class="card-text">Medical professionals</p>
                        <i class="fas fa-user-md stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Appointments</h5>
                        <div class="stat-value"><?php echo number_format($stats['appointments']['count']); ?></div>
                        <p class="card-text">All time bookings</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-warning text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Completed Visits</h5>
                        <div class="stat-value"><?php echo number_format($stats['completed']['count']); ?></div>
                        <p class="card-text">Successful consultations</p>
                        <i class="fas fa-clipboard-check stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Charts -->
            <div class="col-md-8">
                <!-- Appointments Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Appointments by Month (<?php echo date('Y'); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="appointmentsChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Appointment Status Distribution -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie text-primary me-2"></i>
                            Appointment Status Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <canvas id="statusChart" height="200"></canvas>
                            </div>
                            <div class="col-md-8">
                                <div class="table-responsive mt-3 mt-md-0">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalAppts = $stats['appointments']['count'];
                                            foreach ($appointmentStats as $status => $data): 
                                                $percentage = $totalAppts > 0 ? round(($data['count'] / $totalAppts) * 100, 1) : 0;
                                            ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $status === 'pending' ? 'warning' : 
                                                                ($status === 'confirmed' ? 'success' : 
                                                                    ($status === 'cancelled' ? 'danger' : 'secondary')); 
                                                        ?>">
                                                            <?php echo ucfirst($status); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo number_format($data['count']); ?></td>
                                                    <td><?php echo $percentage; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Today's Appointments -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-day text-primary me-2"></i>
                            Today's Appointments
                        </h5>
                        <span class="badge bg-primary"><?php echo count($todayAppointments); ?></span>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (count($todayAppointments) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($todayAppointments as $appointment): ?>
                                    <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo formatTime($appointment['appointment_time']); ?></h6>
                                            <small>
                                                <span class="badge bg-<?php 
                                                    echo $appointment['status'] === 'pending' ? 'warning' : 
                                                        ($appointment['status'] === 'confirmed' ? 'success' : 
                                                            ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </small>
                                        </div>
                                        <p class="mb-1">
                                            <strong>Patient:</strong> <?php echo $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']; ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Doctor:</strong> Dr. <?php echo $appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']; ?>
                                        </p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar-day fa-3x text-muted mb-3"></i>
                                <p>No appointments scheduled for today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="appointments.php" class="btn btn-outline-primary btn-sm w-100">View All Appointments</a>
                    </div>
                </div>
                
                <!-- Recent Registrations -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus text-primary me-2"></i>
                            Recent Registrations
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (count($recentUsers) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($recentUsers as $user): ?>
                                    <a href="users.php?id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h6>
                                            <small><?php echo formatDate($user['created_at'], 'M j'); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === ROLE_PATIENT ? 'info' : 
                                                    ($user['role'] === ROLE_DOCTOR ? 'success' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                            <?php echo $user['email']; ?>
                                        </p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p>No recent registrations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="users.php" class="btn btn-outline-primary btn-sm w-100">Manage Users</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Add Chart.js scripts
$extraScripts = <<<EOT
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Appointments by Month Chart
    const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
    const appointmentsChart = new Chart(appointmentsCtx, {
        type: 'bar',
        data: {
            labels: {$chartMonthsJson},
            datasets: [{
                label: 'Number of Appointments',
                data: {$chartDataJson},
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
            datasets: [{
                data: [
                    {$appointmentStats['pending']['count']}, 
                    {$appointmentStats['confirmed']['count']}, 
                    {$appointmentStats['completed']['count']}, 
                    {$appointmentStats['cancelled']['count']}
                ],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.7)',
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(108, 117, 125, 0.7)',
                    'rgba(220, 53, 69, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 193, 7, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>
EOT;

include '../includes/footer.php';
?>

