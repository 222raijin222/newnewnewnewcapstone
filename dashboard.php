<?php
require_once 'config.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Check if public access is enabled
$public_access = $settings['public_access'] ?? 1;
$is_logged_in = isLoggedIn();

// Check if user is super admin
$is_super_admin = false;
if ($is_logged_in && isset($_SESSION['user']['position']) && $_SESSION['user']['position'] === 'super_admin') {
    $is_super_admin = true;
}

// Check if user is barangay captain
$is_captain = false;
$captain_barangay_name = null;
if ($is_logged_in && isset($_SESSION['user']['position']) && $_SESSION['user']['position'] === 'captain') {
    $is_captain = true;
    $captain_barangay_name = $_SESSION['user']['barangay_name'] ?? null;
}

// Get data based on role
if ($is_super_admin) {
    $total_residents = getResidentCount();
    $total_households = getHouseholdCount();
    $age_distribution = getAgeDistribution();
    $gender_distribution = getGenderDistribution();
    $employment_status = getEmploymentStatus();
} elseif ($is_captain && $captain_barangay_name) {
    $total_residents = getResidentCountByBarangay($captain_barangay_name);
    $total_households = getHouseholdCountByBarangay($captain_barangay_name);
} else {
    $total_residents = getResidentCount();
    $total_households = getHouseholdCount();
}

// Prepare data for charts
$age_labels = [];
$age_data = [];
foreach ($age_distribution as $age) {
    $age_labels[] = $age['age_group'];
    $age_data[] = (int)$age['count'];
}

$gender_labels = [];
$gender_data = [];
foreach ($gender_distribution as $gender) {
    $gender_labels[] = $gender['gender'];
    $gender_data[] = (int)$gender['count'];
}

$employment_labels = [];
$employment_data = [];
foreach ($employment_status as $status) {
    $employment_labels[] = $status['employment_status'];
    $employment_data[] = (int)$status['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo APP_NAME; ?></h2>
            <?php if ($is_logged_in): ?>
                <div class="welcome">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?></p>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            <?php else: ?>
                <div class="welcome">
                    <a href="login.php" class="login-btn">Login</a>
                </div>
            <?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-house-user"></i> Dashboard</a></li>
                <li><a href="resident.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'resident.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                <li><a href="predictive.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'predictive.php' ? 'active' : ''; ?>"><i class="fas fa-brain"></i> Predictive Models</a></li>
                <li><a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Events</a></li>
                <?php if ($is_super_admin): ?>
                    <li><a href="superadmin.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'superadmin.php' ? 'active' : ''; ?>"><i class="fas fa-inbox"></i> Requests</a></li>
                <?php endif; ?>
                <?php if ($is_logged_in): ?>
                    <li><a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <div class="main-content">
        <div class="dashboard-header">
            <h2><i class="fas fa-house-user"></i> Dashboard</h2>
            <?php if ($is_super_admin): ?>
                <span class="admin-badge"><i class="fas fa-crown"></i> Super Admin</span>
            <?php elseif ($is_captain): ?>
                <span class="admin-badge"><i class="fas fa-user-shield"></i> Barangay Captain</span>
            <?php endif; ?>
        </div>

        <?php if (!$is_logged_in && !$public_access): ?>
            <div class="access-denied">
                <i class="fas fa-lock"></i>
                <h2>Public Access Disabled</h2>
                <p>Please login to view the dashboard</p>
                <a href="login.php" class="login-btn">Login</a>
            </div>
        <?php else: ?>
            <div class="dashboard-header">
                <h4>Overview - <?php echo date('F j, Y'); ?></h4>
                <?php if ($is_super_admin): ?>
                    <p class="admin-notice">You are viewing the system as Super Administrator</p>
                <?php elseif ($is_captain): ?>
                    <p class="admin-notice">You are viewing data for your barangay only</p>
                <?php endif; ?>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3>Total Residents</h3>
                        <p><?php echo number_format($total_residents); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                    <div class="stat-info">
                        <h3>Total Households</h3>
                        <p><?php echo number_format($total_households); ?></p>
                    </div>
                </div>
            </div>

            <div class="dashboard-charts-grid">
                <div class="card">
                    <div class="card-header"><h3>Residents by Age Group</h3></div>
                    <div class="card-body"><canvas id="ageChart"></canvas></div>
                </div>
                <div class="card">
                    <div class="card-header"><h3>Gender Distribution</h3></div>
                    <div class="card-body"><canvas id="genderChart"></canvas></div>
                </div>
                <div class="card">
                    <div class="card-header"><h3>Employment Status</h3></div>
                    <div class="card-body"><canvas id="employmentChart"></canvas></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const chartColors = {
    blue: 'rgba(54, 162, 235, 0.7)',
    red: 'rgba(255, 99, 132, 0.7)',
    yellow: 'rgba(255, 206, 86, 0.7)',
    green: 'rgba(75, 192, 192, 0.7)',
    purple: 'rgba(153, 102, 255, 0.7)',
    orange: 'rgba(255, 159, 64, 0.7)',
    gray: 'rgba(201, 203, 207, 0.7)'
};

// Age Chart
new Chart(document.getElementById('ageChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($age_labels); ?>,
        datasets: [{ label: 'Residents by Age Group', data: <?php echo json_encode($age_data); ?>, backgroundColor: chartColors.blue }]
    },
    options: { responsive:true, plugins:{ legend:{ display:false } } }
});

// Gender Chart
new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($gender_labels); ?>,
        datasets: [{ data: <?php echo json_encode($gender_data); ?>, backgroundColor: [chartColors.red, chartColors.blue, chartColors.yellow] }]
    },
    options: { responsive:true }
});

// Employment Chart
new Chart(document.getElementById('employmentChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($employment_labels); ?>,
        datasets: [{ data: <?php echo json_encode($employment_data); ?>, backgroundColor: [chartColors.green, chartColors.red, chartColors.purple, chartColors.orange, chartColors.gray] }]
    },
    options: { responsive:true }
});
</script>
</body>
</html>
