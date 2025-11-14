<?php
// ===========================
// Barangay System Configuration
// ===========================

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Error Reporting (development)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// ===========================
// Database Constants
// ===========================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barangay_system');

define('APP_NAME', 'Barangay Demographic Profiling System');

// ===========================
// Database Connection (mysqli)
// ===========================
function get_db_connection(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

$conn = get_db_connection();

// ===========================
// Authentication Helpers
// ===========================
function isLoggedIn(): bool {
    return isset($_SESSION['user']);
}

// ===========================
// General Data Functions
// ===========================

// Total residents for the user's barangay
function getResidentCountByBarangay($barangay_name): int {
    global $conn;
    $stmt = $conn->prepare("
        SELECT IFNULL(total_population,0) AS total 
        FROM barangay_registration 
        WHERE barangay_name = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? (int)$res->fetch_assoc()['total'] : 0;
}

// Total households for the user's barangay
function getHouseholdCountByBarangay($barangay_name): int {
    global $conn;
    $stmt = $conn->prepare("
        SELECT IFNULL(total_households,0) AS total 
        FROM barangay_registration 
        WHERE barangay_name = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $barangay_name);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? (int)$res->fetch_assoc()['total'] : 0;
}

// Total residents (for current user's barangay)
function getResidentCount(): int {
    global $conn;

    // Check if user is logged in and has a barangay assigned
    $barangay_name = $_SESSION['user']['barangay_name'] ?? null;

    if ($barangay_name) {
        $stmt = $conn->prepare("
            SELECT IFNULL(total_population,0) AS total 
            FROM barangay_registration 
            WHERE barangay_name = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $barangay_name);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }

    // Fallback: return 0 if no barangay assigned
    return 0;
}

// Total households (for current user's barangay)
function getHouseholdCount(): int {
    global $conn;

    // Check if user is logged in and has a barangay assigned
    $barangay_name = $_SESSION['user']['barangay_name'] ?? null;

    if ($barangay_name) {
        $stmt = $conn->prepare("
            SELECT IFNULL(total_households,0) AS total 
            FROM barangay_registration 
            WHERE barangay_name = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $barangay_name);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? (int)$res->fetch_assoc()['total'] : 0;
    }

    // Fallback: return 0 if no barangay assigned
    return 0;
}



// ===========================
// Default Settings
// ===========================
$settings = [
    'public_access' => 1
];
?>
