<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Saguin Demographic System</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="landing-container">
        <div class="hero-section">
            <div class="logo-container">
                
            </div>
            <h1>A Web-Based Profiling System with Predictive Analytics for Future Events and Program Planning </h1>
            <p class="value-proposition">Empowering community planning through data-driven insights and predictive analytics</p>
            
            <div class="action-buttons">
                <a href="login.php" class="btn primary-btn">Login</a>
                
                <a href="register.php" class="btn tertiary-btn">Register</a>
            </div>
        </div>
        
        <div class="footer">
            <p class="copyright">&copy; <?php echo date('Y'); ?> Barangay Information System</p>
        </div>
    </div>
</body>
</html>