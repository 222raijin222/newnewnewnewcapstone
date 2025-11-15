<?php
require_once 'config.php';

// Session Check
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Extract session data
$user = $_SESSION['user'];
$userId = $user['id'] ?? $user['user_id'] ?? null;
$full_name = $user['full_name'] ?? 'User';
$message = "";

// Check if user is super admin
$is_super_admin = false;
if (isset($user['role']) && $user['role'] === 'super_admin') {
    $is_super_admin = true;
}

// Check if user is barangay captain
$is_captain = false;
if (isset($user['role']) && $user['role'] === 'captain') {
    $is_captain = true;
}

// Fetch user data for password verification
$stmt = $conn->prepare("SELECT password, username FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$current_password_hash = $userData['password'] ?? '';
$current_username = $userData['username'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update Personal Information
    if (isset($_POST['update_personal'])) {
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name=?, last_name=?, middle_name=?, birthdate=?, gender=?, civil_status=?, 
                address=?, contact_number=?, email=?, position=?, start_term=?, end_term=? 
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssssssssssssi",
            $_POST['first_name'], $_POST['last_name'], $_POST['middle_name'],
            $_POST['birthdate'], $_POST['gender'], $_POST['civil_status'],
            $_POST['address'], $_POST['contact_number'], $_POST['email'],
            $_POST['position'], $_POST['start_term'], $_POST['end_term'], $userId
        );
        $message = $stmt->execute() ? "✅ Personal information updated successfully." : "⚠️ Failed to update personal information.";
        
        // Update session data
        $_SESSION['user']['first_name'] = $_POST['first_name'];
        $_SESSION['user']['last_name'] = $_POST['last_name'];
        $_SESSION['user']['middle_name'] = $_POST['middle_name'];
        $_SESSION['user']['full_name'] = $_POST['first_name'] . ' ' . $_POST['last_name'];
        $_SESSION['user']['email'] = $_POST['email'];
        $_SESSION['user']['address'] = $_POST['address'];
        $_SESSION['user']['contact_number'] = $_POST['contact_number'];
        $_SESSION['user']['birthdate'] = $_POST['birthdate'];
        $_SESSION['user']['gender'] = $_POST['gender'];
        $_SESSION['user']['civil_status'] = $_POST['civil_status'];
        $_SESSION['user']['position'] = $_POST['position'];
        $_SESSION['user']['start_term'] = $_POST['start_term'];
        $_SESSION['user']['end_term'] = $_POST['end_term'];
    }

    // Change Password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $current_password_hash)) {
            $message = "❌ Current password is incorrect.";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "⚠️ New password and confirmation do not match.";
        } elseif (strlen($newPassword) < 8) {
            $message = "⚠️ New password must be at least 8 characters long.";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            $message = $stmt->execute() ? "✅ Password changed successfully!" : "⚠️ Failed to update password.";
        }
    }

    // Change Username
    if (isset($_POST['change_username'])) {
        $newUsername = $_POST['new_username'] ?? '';
        $confirmUsername = $_POST['confirm_username'] ?? '';

        if ($newUsername !== $confirmUsername) {
            $message = "⚠️ New username and confirmation do not match.";
        } elseif (strlen($newUsername) < 3) {
            $message = "⚠️ Username must be at least 3 characters long.";
        } else {
            // Check if username already exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->bind_param("si", $newUsername, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $message = "⚠️ Username already exists. Please choose a different username.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=? WHERE id=?");
                $stmt->bind_param("si", $newUsername, $userId);
                if ($stmt->execute()) {
                    $message = "✅ Username changed successfully!";
                    $_SESSION['user']['username'] = $newUsername;
                    $current_username = $newUsername;
                } else {
                    $message = "⚠️ Failed to update username.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings - <?php echo htmlspecialchars(APP_NAME); ?></title>
<link rel="stylesheet" href="settings.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root {
    --light-blue: #7da2ce;
}
body {
    background-color: var(--light-blue);
    color: #222;
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 0;
    padding: 0;
}
.main-content {
    flex: 1;
    padding: 40px;
    overflow-y: auto;
}
.settings-content {
    background: #fff;
    border-radius: 18px;
    margin: 0 auto;
    padding: 48px 60px 40px 60px;
    box-shadow: 0 4px 32px rgba(44,62,80,0.07);
    min-width: 420px;
    max-width: 700px;
    display: flex;
    flex-direction: column;
}
.settings-content h2 {
    color: #222;
    font-size: 1.7rem;
    font-weight: 700;
    margin-bottom: 28px;
    letter-spacing: -0.5px;
}
.settings-form {
    display: flex;
    flex-direction: column;
}
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 18px;
}
.form-group {
    flex: 1;
    min-width: 180px;
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-weight: 500;
    margin-bottom: 7px;
    color: #222;
    font-size: 15px;
    letter-spacing: 0.1px;
}
.settings-form input,
.settings-form select {
    border: 1.5px solid #e4e7ec;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 15px;
    background: #f8fafc;
    transition: border 0.2s;
}
.settings-form input:focus,
.settings-form select:focus {
    border-color: #1d3b71;
    background: #fff;
    outline: none;
}
.btn-update {
    background: #1d3b71;
    color: #fff;
    padding: 12px 32px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    width: fit-content;
    margin-top: 18px;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-update:hover {
    background: #16305a;
}
.alert {
    background: #e3f2fd;
    border-left: 4px solid #1d3b71;
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 22px;
    color: #333;
    font-size: 15px;
}
.settings-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 1px solid #e4e7ec;
    padding-bottom: 10px;
}
.tab-btn {
    padding: 12px 24px;
    background: #f8fafc;
    border: 1px solid #e4e7ec;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}
.tab-btn.active {
    background: #1d3b71;
    color: white;
    border-color: #1d3b71;
}
.tab-btn:hover {
    background: #e4e7ec;
}
.tab-btn.active:hover {
    background: #16305a;
}
.security-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e4e7ec;
}
.security-section:last-child {
    border-bottom: none;
}
.security-section h3 {
    color: #1d3b71;
    margin-bottom: 20px;
    font-size: 1.3rem;
}
.current-username {
    background: #f8fafc;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid #e4e7ec;
    margin-bottom: 15px;
    font-weight: 500;
}
.section-header {
    color: #1d3b71;
    font-size: 1.2rem;
    margin: 25px 0 15px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #e4e7ec;
}
.form-section {
    margin-bottom: 25px;
}
@media (max-width: 900px) {
    .dashboard-container {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
    }
    .settings-content {
        padding: 30px 20px;
        min-width: 0;
        max-width: 100%;
    }
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>
</head>
<body>
<div class="dashboard-container">

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Barangay Event And Program Planning System</h2>
            <div class="welcome">
                <p>Welcome, <?php echo htmlspecialchars($full_name); ?></p>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-house-user"></i> Dashboard</a></li>
                <li><a href="resident.php"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                <li><a href="predictive.php"><i class="fas fa-brain"></i> Predictive Models</a></li>
               
                
                <!-- Super Admin Only Links -->
                <?php if ($is_super_admin): ?>
                    <li><a href="superadmin.php"><i class="fas fa-inbox"></i> Requests</a></li>
                <?php endif; ?>
                
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="settings-content">
            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="settings-tabs">
                <button class="tab-btn active" onclick="toggleTab('account-settings')">Personal Info</button>
                <button class="tab-btn" onclick="toggleTab('security-settings')">Security</button>
            </div>

            <!-- Personal Info -->
            <div id="account-settings" class="dropdown-section">
                <h2>Personal Information</h2>
                <form method="POST" class="settings-form">
                    
                    <!-- Basic Information Section -->
                    <div class="section-header">Basic Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First name *</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last name *</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Middle name</label>
                            <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="section-header">Contact Information</div>
                    <div class="form-row">
                       <div class="form-group">
    <label>Contact Number</label>
    <div style="display: flex; align-items: center; gap: 5px;">
        <span>+63</span>
        <input 
            type="text" 
            name="contact_number" 
            maxlength="10"
            pattern="9[0-9]{9}" 
            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
            placeholder="9XXXXXXXXX"
            value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>"
            required
        >
    </div>
</div>

                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Personal Details Section -->
                    <div class="section-header">Personal Details</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Birthdate</label>
                            <input type="date" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="Male" <?php echo ($user['gender'] ?? '')=='Male'?'selected':''; ?>>Male</option>
                                <option value="Female" <?php echo ($user['gender'] ?? '')=='Female'?'selected':''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    

                    <!-- Position Information Section -->
                    <div class="section-header">Position Information</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start term</label>
                            <input type="date" name="start_term" value="<?php echo htmlspecialchars($user['start_term'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>End term</label>
                            <input type="date" name="end_term" value="<?php echo htmlspecialchars($user['end_term'] ?? ''); ?>">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:24px;">
                        <button type="reset" class="btn-update" style="background:#f5f5f5; color:#333; border:1px solid #e4e7ec;">Cancel</button>
                        <button type="submit" name="update_personal" class="btn-update">Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- Security Settings -->
            <div id="security-settings" class="dropdown-section" style="display:none;">
                <h2>Security Settings</h2>
                
                <!-- Change Username Section -->
                <div class="security-section">
                    <h3>Change Username</h3>
                    <div class="current-username">
                        Current Username: <strong><?php echo htmlspecialchars($current_username); ?></strong>
                    </div>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label>New username *</label>
                            <input type="text" name="new_username" minlength="3" required placeholder="Enter new username">
                        </div>
                        <div class="form-group">
                            <label>Confirm new username *</label>
                            <input type="text" name="confirm_username" minlength="3" required placeholder="Confirm new username">
                        </div>
                        <button type="submit" name="change_username" class="btn-update">Change Username</button>
                    </form>
                </div>

                <!-- Change Password Section -->
                <div class="security-section">
                    <h3>Change Password</h3>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label>Current password *</label>
                            <input type="password" name="current_password" required placeholder="Enter current password">
                        </div>
                        <div class="form-group">
                            <label>New password *</label>
                            <input type="password" name="new_password" minlength="8" required placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm new password *</label>
                            <input type="password" name="confirm_password" minlength="8" required placeholder="Confirm new password">
                        </div>
                        <button type="submit" name="change_password" class="btn-update">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
function toggleTab(tabId) {
    // Hide all sections
    document.querySelectorAll('.dropdown-section').forEach(el => {
        el.style.display = 'none';
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected section and activate tab
    document.getElementById(tabId).style.display = 'block';
    event.target.classList.add('active');
}

// Show personal info by default
document.addEventListener('DOMContentLoaded', function() {
    toggleTab('account-settings');
});
</script>
</body>
</html>