<?php
require_once 'config.php';
require_once 'functions.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Session checks
$public_access = $settings['public_access'] ?? 1;
$is_logged_in  = isLoggedIn();
$user_role = $_SESSION['user']['role'] ?? null;
$user_barangay_id = $_SESSION['user']['barangay_id'] ?? null;
$user_barangay_name = $_SESSION['user']['barangay_name'] ?? null;

$residents     = [];
$search_term   = $_GET['search'] ?? '';
$selected_barangay = $_GET['barangay'] ?? null;
$selected_purok = $_GET['purok'] ?? null;

// Get all barangays for dropdown (only for super admin)
$barangays = [];
if ($user_role === 'super_admin') {
    $barangayQuery = "SELECT id, barangay_name FROM barangay_registration WHERE status = 'approved' ORDER BY barangay_name";
    $result = $conn->query($barangayQuery);
    if ($result) {
        $barangays = $result->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // For non-super admin users, get their specific barangay
    if ($user_barangay_id) {
        $barangayQuery = "SELECT id, barangay_name FROM barangay_registration WHERE id = ?";
        $stmt = $conn->prepare($barangayQuery);
        $stmt->bind_param("i", $user_barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $barangays = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Determine which barangay to show data for
$current_barangay_name = null;

if ($user_role === 'super_admin') {
    if ($selected_barangay) {
        // Find barangay name by ID
        foreach ($barangays as $barangay) {
            if ($barangay['id'] == $selected_barangay) {
                $current_barangay_name = $barangay['barangay_name'];
                break;
            }
        }
    }
} elseif (in_array($user_role, ['official', 'captain'])) {
    $current_barangay_name = $user_barangay_name;
}

// Get census data based on filters
try {
    // Get all census submissions with household members
    $census_data = getAllCensusSubmissions($current_barangay_name);
    
    // Process the data to create a residents list
    $residents = [];
    foreach ($census_data as $submission) {
        // Add the main household head
        $residents[] = [
            'id' => $submission['id'],
            'first_name' => $submission['first_name'] ?? 'N/A',
            'last_name' => $submission['last_name'] ?? 'N/A',
            'age' => $submission['age'] ?? 'N/A',
            'gender' => $submission['gender'] ?? 'N/A',
            'barangay' => $submission['barangay'] ?? 'N/A',
            'purok_name' => 'N/A', // Census submissions don't have purok info
            'barangay_name' => $submission['barangay'] ?? 'N/A',
            'is_household_head' => true,
            'household_id' => $submission['id'],
            'member_count' => $submission['member_count'] ?? 0,
            'submitted_at' => $submission['submitted_at'] ?? 'N/A'
        ];
        
        // Add household members
        foreach ($submission['members'] as $member) {
            $residents[] = [
                'id' => 'M' . $member['id'], // Prefix with M to distinguish from household IDs
                'first_name' => explode(' ', $member['member_name'])[0] ?? 'Unknown',
                'last_name' => implode(' ', array_slice(explode(' ', $member['member_name']), 1)) ?? 'Unknown',
                'age' => $member['age'] ?? 'N/A',
                'gender' => $member['sex'] ?? 'N/A',
                'barangay' => $submission['barangay'] ?? 'N/A',
                'purok_name' => 'N/A',
                'barangay_name' => $submission['barangay'] ?? 'N/A',
                'is_household_head' => false,
                'household_id' => $submission['id'],
                'relationship' => $member['relationship'] ?? 'N/A',
                'employment_status' => $member['employment_status'] ?? 'N/A'
            ];
        }
    }
    
    // Apply search filter
    if (!empty($search_term)) {
        $search_lower = strtolower($search_term);
        $residents = array_filter($residents, function($resident) use ($search_lower) {
            return strpos(strtolower($resident['first_name']), $search_lower) !== false ||
                   strpos(strtolower($resident['last_name']), $search_lower) !== false ||
                   strpos(strtolower($resident['barangay']), $search_lower) !== false;
        });
    }
    
} catch (Exception $e) {
    error_log("Error loading census data: " . $e->getMessage());
    $residents = [];
}

// Get puroks for dropdown (for future implementation)
$puroks = [];
$purokQuery = "SELECT * FROM puroks ORDER BY purok_name";
$result = $conn->query($purokQuery);
if ($result) {
    $puroks = $result->fetch_all(MYSQLI_ASSOC);
}

// Delete household (census submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && $is_logged_in) {
    $delete_id = intval($_POST['delete_id']);
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Delete household members first
        $stmt1 = $conn->prepare("DELETE FROM household_members WHERE household_id = ?");
        $stmt1->bind_param("i", $delete_id);
        $stmt1->execute();
        
        // Delete census submission
        $stmt2 = $conn->prepare("DELETE FROM census_submissions WHERE id = ?");
        $stmt2->bind_param("i", $delete_id);
        $stmt2->execute();
        
        $conn->commit();
        
        echo "<script>alert('Household and all members deleted successfully.'); window.location.href='resident.php';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Failed to delete household: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Resident Information - Census Data</title>
    <link rel="stylesheet" href="resident.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .filter-container select {
            padding: 10px 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 14px;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .filter-container select:hover { border-color: #1d3b71; }
        .filter-container select:focus {
            outline: none; border-color: #1d3b71;
            box-shadow: 0 0 0 2px rgba(29, 59, 113, 0.2);
        }
        .action-buttons { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
        .action-buttons .add-resident-btn {
            background-color: #1d3b71; color: white; border: none;
            padding: 10px 15px; border-radius: 4px; cursor: pointer;
            display: flex; align-items: center; gap: 5px; font-size: 14px;
            transition: background-color 0.3s;
        }
        .action-buttons .add-resident-btn:hover { background-color: #2c4d8a; }
        .fill-census-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .fill-census-btn:hover {
            background-color: #218838;
        }
        .purok-badge {
            display: inline-block; padding: 3px 8px;
            border-radius: 12px; background-color: #e0e0e0;
            color: #333; font-size: 12px; font-weight: 500;
        }
        .barangay-badge {
            display: inline-block; padding: 3px 8px;
            border-radius: 12px; background-color: #1d3b71;
            color: white; font-size: 11px; font-weight: 500;
            margin-left: 5px;
        }
        .household-head-badge {
            background-color: #1d3b71;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            margin-left: 5px;
        }
        .household-member-badge {
            background-color: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            margin-left: 5px;
        }
        .no-results { text-align: center; padding: 20px; color: #666; }
        /* Modal styles */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 9999; 
            left: 0; 
            top: 0;
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            overflow-y: auto;
        }
        .modal-content {
            background: #fff; 
            margin: 5% auto; 
            padding: 30px;
            border-radius: 8px; 
            width: 90%; 
            max-width: 1000px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .close-btn { 
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        .close-btn:hover { color: #000; }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .census-data-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .census-data-section h5 {
            margin-top: 0;
            color: #1d3b71;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        .census-item {
            display: flex;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        .census-label {
            font-weight: bold;
            min-width: 200px;
            color: #555;
        }
        .census-value {
            flex: 1;
            color: #333;
        }
        .no-census-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        .current-view-info {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
            border-left: 4px solid #1d3b71;
        }
        .data-source-notice {
            background-color: #e7f3ff;
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
        .household-member {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            background: white;
        }
        .member-header {
            font-weight: bold;
            color: #1d3b71;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .demographic-summary {
            background: #e8f4fd;
            border: 1px solid #b6d7f2;
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
        }
        .summary-item {
            display: inline-block;
            margin-right: 15px;
            font-size: 13px;
        }
        .summary-label {
            font-weight: bold;
            color: #1d3b71;
        }
        .household-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
        }
        .info-card h6 {
            margin-top: 0;
            color: #1d3b71;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .member-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }

        /* =========================================================
           MOBILE RESPONSIVE STYLES (phones & small screens)
           Applies only at max-width: 768px
        ========================================================= */
        @media (max-width: 768px) {
            /* -----------------------------------------
               Layout Adjustments
            ----------------------------------------- */
            .sidebar {
                position: fixed;
                width: 200px;
                left: -200px;
                transition: left 0.3s ease-in-out;
                z-index: 9999;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 15px;
            }

            /* Hamburger Menu */
            .mobile-menu-btn {
                display: inline-block;
                font-size: 26px;
                cursor: pointer;
                color: var(--dark-color);
                margin-right: 15px;
                background: none;
                border: none;
            }

            .resident-header h1 {
                font-size: 1.2rem;
            }

            /* -----------------------------------------
               Search and Filter Container
            ----------------------------------------- */
            .search-container {
                flex-direction: column;
                gap: 10px;
            }

            .search-box {
                width: 100%;
            }

            /* -----------------------------------------
               Action Buttons and Filters
            ----------------------------------------- */
            .action-buttons {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .filter-container {
                width: 100%;
            }

            .filter-container select {
                width: 100%;
                margin-right: 0;
                margin-bottom: 10px;
            }

            /* -----------------------------------------
               Table Responsive Design
            ----------------------------------------- */
            .resident-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .resident-table {
                min-width: 600px;
                font-size: 0.9rem;
            }

            .resident-table th,
            .resident-table td {
                padding: 10px 8px;
            }

            /* -----------------------------------------
               Current View Info
            ----------------------------------------- */
            .current-view-info {
                font-size: 12px;
                padding: 8px 12px;
            }

            /* -----------------------------------------
               Modal Adjustments
            ----------------------------------------- */
            .modal-content {
                margin: 10% auto;
                padding: 20px;
                width: 95%;
            }

            .census-label {
                min-width: 120px;
            }

            .household-info-grid {
                grid-template-columns: 1fr;
            }

            .member-details-grid {
                grid-template-columns: 1fr;
            }

            /* -----------------------------------------
               Sidebar Navigation Links
            ----------------------------------------- */
            .sidebar-nav a {
                font-size: 0.9rem;
                padding: 10px 15px;
            }

            .sidebar-header h2 {
                font-size: 0.85rem;
                text-align: center;
            }

            /* -----------------------------------------
               Utility Classes
            ----------------------------------------- */
            .welcome {
                font-size: 0.9rem;
            }
        }

        /* =========================================================
           EXTRA SMALL DEVICES (very small phones)
        ========================================================= */
        @media (max-width: 480px) {
            .resident-table {
                min-width: 500px;
                font-size: 0.8rem;
            }

            .resident-table th,
            .resident-table td {
                padding: 8px 6px;
            }

            .action-btn {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }

            .sidebar {
                width: 180px;
                left: -180px;
            }

            .sidebar.open {
                left: 0;
            }

            .mobile-menu-btn {
                font-size: 22px;
            }

            .modal-content {
                padding: 15px;
            }

            .census-item {
                flex-direction: column;
                margin-bottom: 10px;
            }

            .census-label {
                min-width: auto;
                margin-bottom: 2px;
            }
        }

        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            background: var(--light-blue);
            color: var(--dark-color);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 999;
            align-items: center;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .mobile-header {
                display: flex;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h2>Residents</h2>
        <div style="width: 40px;"></div> <!-- Spacer for balance -->
    </div>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Barangay Event And Program Planning System</h2>
            <?php if ($is_logged_in): ?>
                <div class="welcome">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'User'); ?></p>
                    <?php if ($user_role === 'captain' && $user_barangay_name): ?>
                        <p><small><?php echo htmlspecialchars($user_barangay_name); ?></small></p>
                    <?php endif; ?>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            <?php else: ?>
                <div class="welcome">
                    <a href="login.php" class="login-btn">Login</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        if (!isset($is_super_admin)) {
            $is_super_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'super_admin');
        }
        ?>

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

    <!-- Main Content -->
    <main class="main-content" style="margin-left: 280px; padding: 20px;">
        <div class="resident-header">
            <h1><i class="fas fa-users"></i> Residents & Households</h1>
        </div>

        <!-- Current View Info -->
        <div class="current-view-info">
            <strong>Currently Viewing:</strong> 
            <?php
            if ($user_role === 'super_admin') {
                if ($current_barangay_name) {
                    echo htmlspecialchars($current_barangay_name);
                } else {
                    echo "All Barangays";
                }
            } else {
                echo "Your Barangay";
                if ($user_barangay_name) {
                    echo " - " . htmlspecialchars($user_barangay_name);
                }
            }
            ?>
            | <strong>Total Individuals:</strong> <?php echo count($residents); ?>
            | <strong>Total Households:</strong> <?php echo count(array_filter($residents, function($r) { return $r['is_household_head']; })); ?>
        </div>

        <!-- Search -->
        <div class="search-container">
            <form method="GET" action="resident.php">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search residents or households..."
                           value="<?php echo htmlspecialchars($search_term); ?>" />
                    <button type="submit">Search</button>
                </div>
            </form>
        </div>

        <!-- Filters and Actions -->
        <div class="action-buttons">
            <div class="filter-container">
                <form method="GET" action="resident.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <?php if ($user_role === 'super_admin'): ?>
                        <select name="barangay" onchange="this.form.submit()">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?php echo $barangay['id']; ?>" 
                                    <?php echo $selected_barangay == $barangay['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>" />
                </form>
            </div>

            <?php if ($is_logged_in): ?>
                <button class="add-resident-btn" onclick="window.location.href='census.php'">
                    <i class="fas fa-user-plus"></i> Fill out Census
                </button>
            <?php endif; ?>
        </div>

        <!-- Residents Table -->
        <div class="resident-table-container">
            <table class="resident-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Role</th>
                        <th>Barangay</th>
                        <?php if ($is_logged_in): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($residents)): ?>
                        <?php foreach ($residents as $resident): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resident['id']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?>
                                    <?php if ($resident['is_household_head']): ?>
                                        <span class="household-head-badge" title="Household Head">H</span>
                                    <?php else: ?>
                                        <span class="household-member-badge" title="Household Member">M</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($resident['age']); ?></td>
                                <td><?php echo htmlspecialchars($resident['gender']); ?></td>
                                <td>
                                    <?php if ($resident['is_household_head']): ?>
                                        <span style="color: #1d3b71; font-weight: bold;">Household Head</span>
                                    <?php else: ?>
                                        <span style="color: #6c757d;"><?php echo htmlspecialchars($resident['relationship'] ?? 'Member'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="barangay-badge"><?php echo htmlspecialchars($resident['barangay_name']); ?></span>
                                </td>
                                <?php if ($is_logged_in): ?>
                                    <td class="actions">
                                        <?php if ($resident['is_household_head']): ?>
                                            <a href="javascript:void(0);" class="action-btn view" title="View Household Details"
                                               onclick="showHouseholdDetails(<?php echo $resident['household_id']; ?>, '<?php echo htmlspecialchars(addslashes($resident['first_name'] . ' ' . $resident['last_name'])); ?>')">
                                               <i class="fas fa-eye"></i></a>
                                            <form method="POST" action="resident.php" onsubmit="return confirm('Are you sure you want to delete this entire household? This will remove all household members.');" style="display:inline;">
                                                <input type="hidden" name="delete_id" value="<?php echo $resident['household_id']; ?>">
                                                <button type="submit" class="action-btn delete" title="Delete Household"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <a href="javascript:void(0);" class="action-btn view" title="View Household Details"
                                               onclick="showHouseholdDetails(<?php echo $resident['household_id']; ?>, '<?php echo htmlspecialchars(addslashes($resident['first_name'] . ' ' . $resident['last_name'])); ?>')">
                                               <i class="fas fa-eye"></i></a>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($is_logged_in ? 7 : 6); ?>" class="no-results">
                                <?php echo empty($search_term) ? 'No census data found. Start by filling out census forms.' : 'No matching residents or households found'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Household Details Modal -->
<div id="householdModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <h3 id="householdName"></h3>
    <div id="householdContainer"><!-- Household details will load here --></div>
  </div>
</div>

<script>
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

function showHouseholdDetails(householdId, name) {
    document.getElementById("householdName").innerText = name + " - Household Details";
    document.getElementById("householdContainer").innerHTML = "<p style='text-align: center; padding: 20px;'><i class='fas fa-spinner fa-spin'></i> Loading household data...</p>";
    
    fetch("get_household_details.php?household_id=" + householdId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayHouseholdData(data.data);
            } else {
                document.getElementById("householdContainer").innerHTML = 
                    "<div class='no-census-data'><i class='fas fa-info-circle'></i><br>" + 
                    (data.message || "No household data available.") + "</div>";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById("householdContainer").innerHTML = 
                "<div class='no-census-data'><i class='fas fa-exclamation-triangle'></i><br>Failed to load household data. Please try again.</div>";
        });
    
    document.getElementById("householdModal").style.display = "block";
}

function displayHouseholdData(data) {
    let html = '';
    
    // Household Head Information - Show ALL fields
    if (data.household_head) {
        html += '<div class="census-data-section">';
        html += '<h5><i class="fas fa-user"></i> Household Head Information</h5>';
        html += '<div class="household-info-grid">';
        
        // Personal Information
        html += '<div class="info-card">';
        html += '<h6>Personal Information</h6>';
        html += '<div class="census-item"><span class="census-label">First Name:</span><span class="census-value">' + (data.household_head.first_name || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Last Name:</span><span class="census-value">' + (data.household_head.last_name || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Age:</span><span class="census-value">' + (data.household_head.age || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Gender:</span><span class="census-value">' + (data.household_head.gender || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Birthdate:</span><span class="census-value">' + (data.household_head.birthdate || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Civil Status:</span><span class="census-value">' + (data.household_head.civil_status || 'N/A') + '</span></div>';
        html += '</div>';
        
        // Contact Information
        html += '<div class="info-card">';
        html += '<h6>Contact Information</h6>';
        html += '<div class="census-item"><span class="census-label">Contact Number:</span><span class="census-value">' + (data.household_head.contact_no || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Email:</span><span class="census-value">' + (data.household_head.email || 'N/A') + '</span></div>';
        html += '</div>';
        
        // Address Information
        html += '<div class="info-card">';
        html += '<h6>Address Information</h6>';
        html += '<div class="census-item"><span class="census-label">Barangay:</span><span class="census-value">' + (data.household_head.barangay || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Street/Sitio:</span><span class="census-value">' + (data.household_head.street_sitio || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">House Number:</span><span class="census-value">' + (data.household_head.house_no || 'N/A') + '</span></div>';
        html += '</div>';
        
        // Employment Information
        html += '<div class="info-card">';
        html += '<h6>Employment Information</h6>';
        html += '<div class="census-item"><span class="census-label">Employment Status:</span><span class="census-value">' + (data.household_head.employment_status || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Occupation:</span><span class="census-value">' + (data.household_head.occupation || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Monthly Income:</span><span class="census-value">' + (data.household_head.monthly_income || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Source of Income:</span><span class="census-value">' + (data.household_head.source_income || 'N/A') + '</span></div>';
        html += '</div>';
        
        // Education Information
        html += '<div class="info-card">';
        html += '<h6>Education Information</h6>';
        html += '<div class="census-item"><span class="census-label">Education Level:</span><span class="census-value">' + (data.household_head.education_level || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">School Name:</span><span class="census-value">' + (data.household_head.school_name || 'N/A') + '</span></div>';
        html += '</div>';
        
        // Additional Information
        html += '<div class="info-card">';
        html += '<h6>Additional Information</h6>';
        html += '<div class="census-item"><span class="census-label">PhilHealth Member:</span><span class="census-value">' + (data.household_head.philhealth_member || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">4Ps Member:</span><span class="census-value">' + (data.household_head.fourps_member || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">IPS Member:</span><span class="census-value">' + (data.household_head.ips_member || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Registered Voter:</span><span class="census-value">' + (data.household_head.registered_voter || 'N/A') + '</span></div>';
        html += '</div>';
        
        html += '</div>'; // Close household-info-grid
        html += '</div>'; // Close census-data-section
    }
    
    // Household Members
    if (data.household_members && data.household_members.length > 0) {
        html += '<div class="census-data-section">';
        html += '<h5><i class="fas fa-users"></i> Household Members (' + data.household_members.length + ' members)</h5>';
        
        data.household_members.forEach((member, index) => {
            html += '<div class="household-member">';
            html += '<div class="member-header">Member ' + (index + 1) + ': ' + (member.member_name || 'Unnamed') + '</div>';
            html += '<div class="member-details-grid">';
            html += '<div class="census-item"><span class="census-label">Age:</span><span class="census-value">' + (member.age || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Gender:</span><span class="census-value">' + (member.sex || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Relationship:</span><span class="census-value">' + (member.relationship || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Civil Status:</span><span class="census-value">' + (member.civil_status || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Employment Status:</span><span class="census-value">' + (member.employment_status || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Occupation:</span><span class="census-value">' + (member.occupation || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Monthly Income:</span><span class="census-value">' + (member.monthly_income || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">Education Level:</span><span class="census-value">' + (member.education_level || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">School Name:</span><span class="census-value">' + (member.school_name || 'N/A') + '</span></div>';
            html += '<div class="census-item"><span class="census-label">PhilHealth Member:</span><span class="census-value">' + (member.philhealth_member || 'N/A') + '</span></div>';
            html += '</div>';
            if (member.work_details) {
                html += '<div class="census-item"><span class="census-label">Work Details:</span><span class="census-value">' + member.work_details + '</span></div>';
            }
            html += '</div>';
        });
        html += '</div>';
    }
    
    // Additional Census Information
    if (data.census_info) {
        html += '<div class="census-data-section">';
        html += '<h5><i class="fas fa-home"></i> Household Facilities</h5>';
        html += '<div class="household-info-grid">';
        html += '<div class="info-card">';
        html += '<h6>Basic Facilities</h6>';
        html += '<div class="census-item"><span class="census-label">Water Supply:</span><span class="census-value">' + (data.census_info.water_supply || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Toilet Facility:</span><span class="census-value">' + (data.census_info.toilet_facility || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Garbage Disposal:</span><span class="census-value">' + (data.census_info.garbage_disposal || 'N/A') + '</span></div>';
        html += '</div>';
        html += '<div class="info-card">';
        html += '<h6>Additional Information</h6>';
        html += '<div class="census-item"><span class="census-label">Source of Income:</span><span class="census-value">' + (data.census_info.source_income || 'N/A') + '</span></div>';
        html += '<div class="census-item"><span class="census-label">Submitted At:</span><span class="census-value">' + (data.census_info.submitted_at || 'N/A') + '</span></div>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
    }
    
    document.getElementById("householdContainer").innerHTML = html;
}

function closeModal() {
    document.getElementById("householdModal").style.display = "none";
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById("householdModal");
    if (event.target === modal) {
        closeModal();
    }
}
</script>
</body>
</html>