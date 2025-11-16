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
if ($is_logged_in && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'super_admin') {
    $is_super_admin = true;
}

// Check if user is barangay captain
$is_captain = false;
$captain_barangay_id = null;
$captain_barangay_name = null;
if ($is_logged_in && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'captain') {
    $is_captain = true;
    $captain_barangay_id = $_SESSION['user']['barangay_id'] ?? null;
    $captain_barangay_name = $_SESSION['user']['barangay_name'] ?? null;
}

// Database query functions
function getResidentCount() {
    global $conn;
    $query = "SELECT COUNT(*) as total FROM census_submissions";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    return 0;
}

function getResidentCountByBarangay($barangay_name) {
    global $conn;
    $query = "SELECT COUNT(*) as total FROM census_submissions WHERE barangay = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    return 0;
}

function getHouseholdCount() {
    global $conn;
    $query = "SELECT COUNT(DISTINCT id) as total FROM census_submissions";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    return 0;
}

function getHouseholdCountByBarangay($barangay_name) {
    global $conn;
    $query = "SELECT COUNT(DISTINCT id) as total FROM census_submissions WHERE barangay = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    return 0;
}

function getAgeDistribution() {
    global $conn;
    $query = "SELECT 
        CASE 
            WHEN age < 18 THEN '0-17'
            WHEN age BETWEEN 18 AND 24 THEN '18-24'
            WHEN age BETWEEN 25 AND 34 THEN '25-34'
            WHEN age BETWEEN 35 AND 44 THEN '35-44'
            WHEN age BETWEEN 45 AND 59 THEN '45-59'
            ELSE '60+'
        END AS age_group,
        COUNT(*) as count
        FROM census_submissions 
        WHERE age IS NOT NULL
        GROUP BY age_group 
        ORDER BY 
            CASE age_group
                WHEN '0-17' THEN 1
                WHEN '18-24' THEN 2
                WHEN '25-34' THEN 3
                WHEN '35-44' THEN 4
                WHEN '45-59' THEN 5
                ELSE 6
            END";
    $result = $conn->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getAgeDistributionByBarangay($barangay_name) {
    global $conn;
    $query = "SELECT 
        CASE 
            WHEN age < 18 THEN '0-17'
            WHEN age BETWEEN 18 AND 24 THEN '18-24'
            WHEN age BETWEEN 25 AND 34 THEN '25-34'
            WHEN age BETWEEN 35 AND 44 THEN '35-44'
            WHEN age BETWEEN 45 AND 59 THEN '45-59'
            ELSE '60+'
        END AS age_group,
        COUNT(*) as count
        FROM census_submissions 
        WHERE barangay = ? AND age IS NOT NULL
        GROUP BY age_group 
        ORDER BY 
            CASE age_group
                WHEN '0-17' THEN 1
                WHEN '18-24' THEN 2
                WHEN '25-34' THEN 3
                WHEN '35-44' THEN 4
                WHEN '45-59' THEN 5
                ELSE 6
            END";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getGenderDistribution() {
    global $conn;
    $query = "SELECT 
        COALESCE(gender, 'Not Specified') as gender,
        COUNT(*) as count
        FROM census_submissions 
        WHERE gender IS NOT NULL AND gender != ''
        GROUP BY gender";
    $result = $conn->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getGenderDistributionByBarangay($barangay_name) {
    global $conn;
    $query = "SELECT 
        COALESCE(gender, 'Not Specified') as gender,
        COUNT(*) as count
        FROM census_submissions 
        WHERE barangay = ? AND gender IS NOT NULL AND gender != ''
        GROUP BY gender";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getEmploymentStatus() {
    global $conn;
    $query = "SELECT 
        COALESCE(status_work_business, 'Not Specified') as employment_status,
        COUNT(*) as count
        FROM census_submissions 
        WHERE status_work_business IS NOT NULL AND status_work_business != ''
        GROUP BY status_work_business";
    $result = $conn->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getEmploymentStatusByBarangay($barangay_name) {
    global $conn;
    $query = "SELECT 
        COALESCE(status_work_business, 'Not Specified') as employment_status,
        COUNT(*) as count
        FROM census_submissions 
        WHERE barangay = ? AND status_work_business IS NOT NULL AND status_work_business != ''
        GROUP BY status_work_business";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Get data from database using MySQLi based on user role
try {
    if ($is_super_admin) {
        // Super admin can see all data
        $total_residents = getResidentCount();
        $total_households = getHouseholdCount();
        $age_distribution = getAgeDistribution();
        $gender_distribution = getGenderDistribution();
        $employment_status = getEmploymentStatus();
    } elseif ($is_captain && $captain_barangay_name) {
        // Captain can only see data from their barangay
        $total_residents = getResidentCountByBarangay($captain_barangay_name);
        $total_households = getHouseholdCountByBarangay($captain_barangay_name);
        $age_distribution = getAgeDistributionByBarangay($captain_barangay_name);
        $gender_distribution = getGenderDistributionByBarangay($captain_barangay_name);
        $employment_status = getEmploymentStatusByBarangay($captain_barangay_name);
    } else {
        // Default data for public access or other roles
        $total_residents = getResidentCount();
        $total_households = getHouseholdCount();
        $age_distribution = getAgeDistribution();
        $gender_distribution = getGenderDistribution();
        $employment_status = getEmploymentStatus();
    }
} catch (Exception $e) {
    // Handle database errors gracefully
    error_log("Dashboard data error: " . $e->getMessage());
    $total_residents = 0;
    $total_households = 0;
    $age_distribution = [];
    $gender_distribution = [];
    $employment_status = [];
}

// Prepare data for charts - FIXED: Ensure arrays are properly initialized
$age_labels = [];
$age_data = [];
if (!empty($age_distribution)) {
    foreach ($age_distribution as $age) {
        $age_labels[] = $age['age_group'];
        $age_data[] = (int)$age['count'];
    }
} else {
    // Default empty data if no results
    $age_labels = ['0-17', '18-24', '25-34', '35-44', '45-59', '60+'];
    $age_data = [0, 0, 0, 0, 0, 0];
}

$gender_labels = [];
$gender_data = [];
if (!empty($gender_distribution)) {
    foreach ($gender_distribution as $gender) {
        $gender_labels[] = $gender['gender'];
        $gender_data[] = (int)$gender['count'];
    }
} else {
    // Default empty data if no results
    $gender_labels = ['Male', 'Female', 'Other'];
    $gender_data = [0, 0, 0];
}

$employment_labels = [];
$employment_data = [];
if (!empty($employment_status)) {
    foreach ($employment_status as $status) {
        $employment_labels[] = $status['employment_status'];
        $employment_data[] = (int)$status['count'];
    }
} else {
    // Default empty data if no results
    $employment_labels = ['Employed', 'Unemployed', 'Student', 'Retired', 'Self-Employed'];
    $employment_data = [0, 0, 0, 0, 0];
}

// Calculate population growth (placeholder - you can implement actual calculation)
$population_growth = "+5.2%";

// Debug: Check what data we're getting
error_log("Dashboard Data - Residents: " . $total_residents . ", Households: " . $total_households);
error_log("Age Distribution: " . json_encode($age_distribution));
error_log("Gender Distribution: " . json_encode($gender_distribution));
error_log("Employment Status: " . json_encode($employment_status));
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
    <style>
        /* Additional CSS for better alignment */
        .dashboard-charts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .card-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #495057;
        }
        
        .card-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chart-container {
            flex: 1;
            position: relative;
            height: 300px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #fff;
        }
        
        .stat-info h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #6c757d;
        }
        
        .stat-info p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #495057;
        }

        .data-source-notice {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #0066cc;
        }
        
        .data-source-notice i {
            margin-right: 8px;
        }

        /* UPDATED: Mobile header styles - Menu button on left side */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: var(--dark-color);
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 0;
        }

        .dashboard-header h2 {
            margin: 0;
            font-size: 2.0rem;
            color: var(--dark-color);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--dark-color);
            padding: 5px;
        }

        .admin-badge {
            background: var(--primary-blue);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .admin-notice {
            background: #e7f3ff;
            padding: 10px 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 14px;
            color: #0066cc;
        }

        .no-data-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            text-align: center;
        }

        .no-data-message i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        @media (max-width: 1200px) {
            .dashboard-charts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-charts-grid,
            .stats-container {
                grid-template-columns: 1fr;
            }

            .mobile-menu-btn {
                display: inline-block;
            }

            .dashboard-header {
                padding: 12px 15px;
            }

            .dashboard-header h2 {
                font-size: 1.3rem;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .stat-info p {
                font-size: 20px;
            }

            .chart-container {
                height: 250px;
            }
        }

        @media (max-width: 480px) {
            .stat-card {
                padding: 12px;
            }

            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 18px;
            }

            .stat-info p {
                font-size: 18px;
            }

            .chart-container {
                height: 200px;
            }

            .card-header h3 {
                font-size: 14px;
            }

            .dashboard-header {
                padding: 10px 12px;
            }

            .dashboard-header h2 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Barangay Event And Program Planning System</h2>
            <?php if ($is_logged_in): ?>
                <div class="welcome">
                    <?php if ($is_captain && $captain_barangay_name): ?>
                        <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?> of <?php echo htmlspecialchars($captain_barangay_name); ?></p>
                    <?php else: ?>
                        <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?></p>
                    <?php endif; ?>
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
               
               
                <!-- Super Admin Only Links -->
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
        <!-- UPDATED: Dashboard header with menu button on left side -->
        <div class="dashboard-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 ><i class="fas fa-house-user"></i> Dashboard</h2>
            </div>
            <div class="header-right">
                <?php if ($is_super_admin): ?>
                    <span class="admin-badge"><i class="fas fa-crown"></i> Super Admin</span>
                <?php elseif ($is_captain): ?>
                    <span class="admin-badge"><i class="fas fa-user-shield"></i> Barangay Captain</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$is_logged_in && !$public_access): ?>
            <div class="access-denied">
                <i class="fas fa-lock"></i>
                <h2>Public Access Disabled</h2>
                <p>Please login to view the dashboard</p>
                <a href="login.php" class="login-btn">Login</a>
            </div>
        <?php else: ?>
            <div class="dashboard-subheader">
                <h4>Overview - <?php echo date('F j, Y'); ?></h4>
                <?php if ($is_super_admin): ?>
                    <p class="admin-notice">You are viewing the system as Super Administrator</p>
                <?php elseif ($is_captain && $captain_barangay_name): ?>
                    <p class="admin-notice">You are viewing data for <?php echo htmlspecialchars($captain_barangay_name); ?> only</p>
                <?php endif; ?>
            </div>

            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #3498db;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Residents</h3>
                        <p><?php echo number_format($total_residents); ?></p>
                        <?php if ($is_super_admin): ?>
                            <small style="color: #6c757d; font-size: 12px;">Across all barangays</small>
                        <?php elseif ($is_captain && $captain_barangay_name): ?>
                            <small style="color: #6c757d; font-size: 12px;">In <?php echo htmlspecialchars($captain_barangay_name); ?></small>
                        <?php else: ?>
                            <small style="color: #6c757d; font-size: 12px;">Across all barangays</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #2ecc71;">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Households</h3>
                        <p><?php echo number_format($total_households); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #e74c3c;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Population Growth</h3>
                        <p><?php echo $population_growth; ?></p>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="dashboard-charts-grid">
                <!-- Column 1: Residents by Age Group -->
                <div class="card">
                    <div class="card-header">
                        <h3>Residents by Age Group</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <?php if (array_sum($age_data) > 0): ?>
                                <canvas id="ageChart"></canvas>
                            <?php else: ?>
                                <div class="no-data-message">
                                    <i class="fas fa-chart-bar"></i>
                                    <p>No age data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Gender Distribution -->
                <div class="card">
                    <div class="card-header">
                        <h3>Gender Distribution</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <?php if (array_sum($gender_data) > 0): ?>
                                <canvas id="genderChart"></canvas>
                            <?php else: ?>
                                <div class="no-data-message">
                                    <i class="fas fa-venus-mars"></i>
                                    <p>No gender data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Column 3: Employment Status -->
                <div class="card">
                    <div class="card-header">
                        <h3>Employment Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <?php if (array_sum($employment_data) > 0): ?>
                                <canvas id="employmentChart"></canvas>
                            <?php else: ?>
                                <div class="no-data-message">
                                    <i class="fas fa-briefcase"></i>
                                    <p>No employment data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Chart colors
    const chartColors = {
        blue: 'rgba(54, 162, 235, 0.7)',
        red: 'rgba(255, 99, 132, 0.7)',
        yellow: 'rgba(255, 206, 86, 0.7)',
        green: 'rgba(75, 192, 192, 0.7)',
        purple: 'rgba(153, 102, 255, 0.7)',
        orange: 'rgba(255, 159, 64, 0.7)',
        gray: 'rgba(201, 203, 207, 0.7)'
    };

    // Initialize charts only if data exists
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (array_sum($age_data) > 0): ?>
        // Age Distribution Chart
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        const ageChart = new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($age_labels); ?>,
                datasets: [{
                    label: 'Residents by Age Group',
                    data: <?php echo json_encode($age_data); ?>,
                    backgroundColor: chartColors.blue,
                    borderColor: chartColors.blue.replace('0.7', '1'),
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.raw} residents`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if (array_sum($gender_data) > 0): ?>
        // Gender Distribution Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($gender_labels); ?>,
                datasets: [{
                    label: 'Gender Distribution',
                    data: <?php echo json_encode($gender_data); ?>,
                    backgroundColor: [
                        chartColors.blue,
                        chartColors.red,
                        chartColors.yellow,
                        chartColors.green,
                        chartColors.purple
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '0%'
            }
        });
        <?php endif; ?>

        <?php if (array_sum($employment_data) > 0): ?>
        // Employment Status Chart
        const employmentCtx = document.getElementById('employmentChart').getContext('2d');
        const employmentChart = new Chart(employmentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($employment_labels); ?>,
                datasets: [{
                    label: 'Employment Status',
                    data: <?php echo json_encode($employment_data); ?>,
                    backgroundColor: [
                        chartColors.green,
                        chartColors.red,
                        chartColors.purple,
                        chartColors.orange,
                        chartColors.gray
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
        <?php endif; ?>
    });

    // Mobile sidebar functionality
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }

    // Close sidebar when clicking on a link (mobile)
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });

    // Close sidebar when pressing escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }
    });
</script>

</body>
</html>