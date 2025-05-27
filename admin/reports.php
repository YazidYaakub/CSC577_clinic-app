<?php
// Admin Reports Page
$pageTitle = 'Reports & Analytics';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure user is logged in and is an admin
requireLogin(ROLE_ADMIN, '../login.php');

$userId = getCurrentUserId();
$db = Database::getInstance();

// Get date range for reports (default to current month)
$startDate = sanitize($_GET['start_date'] ?? date('Y-m-01'));
$endDate = sanitize($_GET['end_date'] ?? date('Y-m-t'));
$reportType = sanitize($_GET['report_type'] ?? 'overview');

try {
    $currentDate = getCurrentDate();
    
    // Overview Statistics
    $overviewStats = [
        'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users"),
        'total_patients' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_PATIENT]),
        'total_doctors' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_DOCTOR]),
        'total_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments"),
        'completed_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed'"),
        'cancelled_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled'"),
        'pending_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'"),
        'upcoming_appointments' => $db->fetchOne(
            "SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= ? AND status IN ('pending', 'confirmed')",
            [$currentDate]
        )
    ];
    
    // Monthly Appointment Trends (Last 6 months)
    $monthlyTrends = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        
        $monthData = $db->fetchOne(
            "SELECT COUNT(*) as count FROM appointments WHERE appointment_date LIKE ?",
            [$month . '%']
        );
        
        $monthlyTrends[] = [
            'month' => $monthName,
            'count' => $monthData['count']
        ];
    }
    
    // Doctor Performance
    $doctorPerformance = $db->fetchAll(
        "SELECT u.first_name, u.last_name, dd.specialization,
                COUNT(a.id) as total_appointments,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                ROUND(AVG(dd.consultation_fee), 2) as avg_fee
         FROM users u 
         JOIN doctor_details dd ON u.id = dd.user_id 
         LEFT JOIN appointments a ON u.id = a.doctor_id 
         WHERE u.role = ?
         GROUP BY u.id, u.first_name, u.last_name, dd.specialization, dd.consultation_fee
         ORDER BY total_appointments DESC",
        [ROLE_DOCTOR]
    );
    
    // Appointment Status Distribution
    $statusDistribution = $db->fetchAll(
        "SELECT status, COUNT(*) as count 
         FROM appointments 
         WHERE appointment_date BETWEEN ? AND ?
         GROUP BY status 
         ORDER BY count DESC",
        [$startDate, $endDate]
    );
    
    // Daily Appointment Volume (Last 30 days)
    $dailyVolume = $db->fetchAll(
        "SELECT appointment_date, COUNT(*) as count 
         FROM appointments 
         WHERE appointment_date >= date(?, '-30 days')
         GROUP BY appointment_date 
         ORDER BY appointment_date",
        [$currentDate]
    );
    
    // Popular Appointment Times
    $popularTimes = $db->fetchAll(
        "SELECT appointment_time, COUNT(*) as count 
         FROM appointments 
         WHERE appointment_date BETWEEN ? AND ?
         GROUP BY appointment_time 
         ORDER BY count DESC 
         LIMIT 10",
        [$startDate, $endDate]
    );
    
    // Patient Registration Trends (Last 6 months)
    $registrationTrends = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        
        $regData = $db->fetchOne(
            "SELECT COUNT(*) as count FROM users WHERE role = ? AND created_at LIKE ?",
            [ROLE_PATIENT, $month . '%']
        );
        
        $registrationTrends[] = [
            'month' => $monthName,
            'count' => $regData['count']
        ];
    }
    
    // Recent Activity
    $recentActivity = $db->fetchAll(
        "SELECT 'appointment' as type, 
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                a.appointment_date, a.appointment_time, a.status, a.created_at
         FROM appointments a
         JOIN users p ON a.patient_id = p.id
         JOIN users d ON a.doctor_id = d.id
         ORDER BY a.created_at DESC
         LIMIT 10"
    );
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while generating reports.', 'danger');
    
    // Set defaults
    $overviewStats = [];
    $monthlyTrends = [];
    $doctorPerformance = [];
    $statusDistribution = [];
    $dailyVolume = [];
    $popularTimes = [];
    $registrationTrends = [];
    $recentActivity = [];
}

// Include header
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Reports & Analytics</h1>
            <div class="d-flex gap-2">
                <input type="date" id="start_date" class="form-control" value="<?php echo $startDate; ?>" style="width: auto;">
                <input type="date" id="end_date" class="form-control" value="<?php echo $endDate; ?>" style="width: auto;">
                <button class="btn btn-primary" onclick="updateReports()">
                    <i class="fas fa-sync-alt me-1"></i> Update
                </button>
            </div>
        </div>
        
        <!-- Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <div class="stat-value"><?php echo number_format($overviewStats['total_users']['count']); ?></div>
                        <p class="card-text">All registered users</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Appointments</h5>
                        <div class="stat-value"><?php echo number_format($overviewStats['total_appointments']['count']); ?></div>
                        <p class="card-text">All time bookings</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Completed</h5>
                        <div class="stat-value"><?php echo number_format($overviewStats['completed_appointments']['count']); ?></div>
                        <p class="card-text">Successful consultations</p>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stat-card bg-warning text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Upcoming</h5>
                        <div class="stat-value"><?php echo number_format($overviewStats['upcoming_appointments']['count']); ?></div>
                        <p class="card-text">Scheduled appointments</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Monthly Trends Chart -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Appointment Trends (Last 6 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendsChart" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Doctor Performance Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-md text-primary me-2"></i>
                            Doctor Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Specialization</th>
                                        <th>Total Appointments</th>
                                        <th>Completed</th>
                                        <th>Cancelled</th>
                                        <th>Success Rate</th>
                                        <th>Avg Fee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctorPerformance as $doctor): 
                                        $successRate = $doctor['total_appointments'] > 0 ? 
                                            round(($doctor['completed'] / $doctor['total_appointments']) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td>Dr. <?php echo $doctor['first_name'] . ' ' . $doctor['last_name']; ?></td>
                                            <td><?php echo $doctor['specialization']; ?></td>
                                            <td><?php echo $doctor['total_appointments']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $doctor['completed']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $doctor['cancelled']; ?></span></td>
                                            <td><?php echo $successRate; ?>%</td>
                                            <td>$<?php echo number_format($doctor['avg_fee'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Status Distribution -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie text-primary me-2"></i>
                            Status Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" height="200"></canvas>
                        <div class="mt-3">
                            <?php foreach ($statusDistribution as $status): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-<?php 
                                        echo $status['status'] === 'pending' ? 'warning' : 
                                            ($status['status'] === 'confirmed' ? 'success' : 
                                                ($status['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($status['status']); ?>
                                    </span>
                                    <span><?php echo $status['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Popular Times -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock text-primary me-2"></i>
                            Popular Appointment Times
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($popularTimes, 0, 5) as $time): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo formatTime($time['appointment_time']); ?></span>
                                <span class="badge bg-primary"><?php echo $time['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-primary me-2"></i>
                            Recent Activity
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="mb-3 p-2 border-bottom">
                                <small class="text-muted"><?php echo formatDate($activity['created_at'], 'M j, g:i A'); ?></small>
                                <div>
                                    <strong><?php echo $activity['patient_name']; ?></strong>
                                    booked with <strong><?php echo $activity['doctor_name']; ?></strong>
                                </div>
                                <div class="text-muted">
                                    <?php echo formatDate($activity['appointment_date']); ?> at <?php echo formatTime($activity['appointment_time']); ?>
                                    <span class="badge bg-<?php 
                                        echo $activity['status'] === 'pending' ? 'warning' : 
                                            ($activity['status'] === 'confirmed' ? 'success' : 
                                                ($activity['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                                    ?> ms-2">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
    // Monthly Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: [
EOT;

foreach ($monthlyTrends as $trend) {
    $extraScripts .= "'" . $trend['month'] . "',";
}

$extraScripts .= <<<EOT
            ],
            datasets: [{
                label: 'Appointments',
                data: [
EOT;

foreach ($monthlyTrends as $trend) {
    $extraScripts .= $trend['count'] . ",";
}

$extraScripts .= <<<EOT
                ],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [
EOT;

foreach ($statusDistribution as $status) {
    $extraScripts .= "'" . ucfirst($status['status']) . "',";
}

$extraScripts .= <<<EOT
            ],
            datasets: [{
                data: [
EOT;

foreach ($statusDistribution as $status) {
    $extraScripts .= $status['count'] . ",";
}

$extraScripts .= <<<EOT
                ],
                backgroundColor: [
                    '#ffc107', '#28a745', '#dc3545', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    function updateReports() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        if (startDate && endDate) {
            window.location.href = `?start_date=\${startDate}&end_date=\${endDate}`;
        }
    }
</script>
EOT;

// Include footer with extra scripts
include '../includes/footer.php';
?>
