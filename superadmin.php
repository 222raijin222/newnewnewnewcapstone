<?php
session_start();
require_once 'config.php';

// Check if user is super admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "barangay_system";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Approve or reject action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        // Update registration status
        $conn->prepare("UPDATE barangay_registration SET status='approved' WHERE id=?")->execute([$id]);

        // Create 5 census accounts automatically
        for ($i = 1; $i <= 5; $i++) {
            $username = "census_user_" . $id . "_" . $i;
            $password = password_hash("123456", PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO census_accounts (official_id, username, password, role) VALUES (?, ?, ?, 'census_user')");
            $stmt->execute([$id, $username, $password]);
        }

        echo "<script>alert('Registration approved and 5 census accounts created.');window.location='superadmin.php';</script>";
        exit;
    } elseif ($action === 'reject') {
        $conn->prepare("UPDATE barangay_registration SET status='rejected' WHERE id=?")->execute([$id]);
        echo "<script>alert('Registration rejected.');window.location='superadmin.php';</script>";
        exit;
    }
}

// Fetch registration details for modal
$registration_details = null;
if (isset($_GET['view_id'])) {
    $stmt = $conn->prepare("SELECT * FROM barangay_registration WHERE id = ?");
    $stmt->execute([$_GET['view_id']]);
    $registration_details = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all registrations
$stmt = $conn->query("SELECT * FROM barangay_registration ORDER BY created_at DESC");
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Base Styles */
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        /* Header Styles */
        h1 {
            background: #1d3b71;
            color: white;
            padding: 15px;
            text-align: center;
            margin: 0;
            font-size: 1.5rem;
        }
        
        /* Button Container */
        .button-container {
            margin: 20px;
            display: flex;
            justify-content: flex-start;
        }
        
        .back-button {
            background-color: #1d3b71;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
        }
        
        /* Table Container */
        .table-container {
            width: 95%;
            margin: 20px auto;
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #1d3b71;
            color: white;
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f2f5fc;
        }
        
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .actions a {
            padding: 6px 10px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 0.85em;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
        
        .approve {
            background: #28a745;
        }
        
        .reject {
            background: #dc3545;
        }
        
        .view {
            background: #17a2b8;
        }
        
        .status {
            font-weight: bold;
            text-transform: capitalize;
        }
        
        .status.pending {
            color: #ff9800;
        }
        
        .status.approved {
            color: #28a745;
        }
        
        .status.rejected {
            color: #dc3545;
        }
        
        .no-id {
            color: #dc3545;
            font-style: italic;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .modal-section h3 {
            color: #1d3b71;
            margin-top: 0;
            border-bottom: 2px solid #1d3b71;
            padding-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
        }
        
        @media (min-width: 768px) {
            .detail-row {
                flex-direction: row;
            }
        }
        
        .detail-label {
            font-weight: bold;
            color: #555;
            min-width: 150px;
            margin-bottom: 5px;
        }
        
        @media (min-width: 768px) {
            .detail-label {
                margin-bottom: 0;
            }
        }
        
        .detail-value {
            flex: 1;
        }
        
        .id-image {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 767px) {
            h1 {
                font-size: 1.3rem;
                padding: 12px;
            }
            
            .button-container {
                margin: 15px;
            }
            
            .back-button {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
            
            .table-container {
                width: 98%;
                margin: 15px auto;
            }
            
            th, td {
                padding: 8px 6px;
                font-size: 0.9em;
            }
            
            .actions a {
                padding: 5px 8px;
                font-size: 0.8em;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 15px;
                width: 95%;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 480px) {
            h1 {
                font-size: 1.2rem;
                padding: 10px;
            }
            
            .button-container {
                margin: 10px;
            }
            
            .back-button {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            
            .table-container {
                width: 99%;
                margin: 10px auto;
            }
            
            th, td {
                padding: 6px 4px;
                font-size: 0.85em;
            }
            
            .actions a {
                padding: 4px 6px;
                font-size: 0.75em;
            }
        }
    </style>
</head>
<body>

    <h1>Super Admin Dashboard - Registration Requests</h1>
    <div class="button-container">
        <button type="button" 
                onclick="window.location.href='dashboard.php';"  
                class="back-button">
            ← Back to Dashboard
        </button>
    </div> 

    <div class="table-container">
        <table>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Barangay</th>
                <th>Position</th>
                <th>Status</th>
                <th>Official ID</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($registrations as $reg): ?>
            <tr>
                <td><?= htmlspecialchars($reg['id']) ?></td>
                <td><?= htmlspecialchars($reg['full_name']) ?></td>
                <td><?= htmlspecialchars($reg['barangay_name']) ?></td>
                <td><?= htmlspecialchars($reg['position']) ?></td>
                <td class="status <?= htmlspecialchars($reg['status']) ?>"><?= htmlspecialchars($reg['status']) ?></td>
                <td>
                    <?php if (!empty($reg['id_path'])): ?>
                        <a href="#" onclick="viewDetails(<?= $reg['id'] ?>)">View ID & Details</a>
                    <?php else: ?>
                        <span class="no-id">No ID Uploaded</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if ($reg['status'] == 'pending'): ?>
                        <a href="?action=approve&id=<?= $reg['id'] ?>" class="approve" onclick="return confirm('Approve this registration?')"><i class="fas fa-check"></i> Approve</a>
                        <a href="?action=reject&id=<?= $reg['id'] ?>" class="reject" onclick="return confirm('Reject this registration?')"><i class="fas fa-times"></i> Reject</a>
                    <?php else: ?>
                        <span><?= ucfirst($reg['status']) ?></span>
                    <?php endif; ?>
                    <a href="#" onclick="viewDetails(<?= $reg['id'] ?>)" class="view"><i class="fas fa-eye"></i> View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Modal for viewing details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Registration Details</h2>
            
            <?php if ($registration_details): ?>
                <!-- Personal Information -->
                <div class="modal-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="detail-row">
                        <div class="detail-label">Full Name:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['full_name']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Birthdate:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['birthdate']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Gender:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['gender']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Address:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['address']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Contact Number:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['contact_number']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['email']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Username:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['username']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Position:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['position']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Term:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['start_term']) ?> to <?= htmlspecialchars($registration_details['end_term']) ?></div>
                    </div>
                </div>

                <!-- Barangay Information -->
                <div class="modal-section">
                    <h3><i class="fas fa-landmark"></i> Barangay Information</h3>
                    <div class="detail-row">
                        <div class="detail-label">Barangay Name:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['barangay_name']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Municipality/City:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['municipality']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Province:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['province']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Barangay Hall Address:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['barangay_hall']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Barangay Contact:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['barangay_contact']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Barangay Email:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['barangay_email']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Total Population:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['total_population']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Total Households:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['total_households']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Purok Count:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['purok_count']) ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Purok Names:</div>
                        <div class="detail-value"><?= htmlspecialchars($registration_details['purok_names']) ?></div>
                    </div>
                </div>

                <!-- Official ID -->
                <div class="modal-section">
                    <h3><i class="fas fa-id-card"></i> Official ID</h3>
                    <?php if (!empty($registration_details['id_path'])): ?>
                        <img src="<?= htmlspecialchars($registration_details['id_path']) ?>" alt="Official ID" class="id-image">
                    <?php else: ?>
                        <p class="no-id">No ID uploaded</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewDetails(id) {
            window.location.href = 'superadmin.php?view_id=' + id;
        }

        function closeModal() {
            window.location.href = 'superadmin.php';
        }

        // Show modal if view_id parameter is present
        <?php if (isset($_GET['view_id'])): ?>
            document.getElementById('detailsModal').style.display = 'block';
        <?php endif; ?>

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>