<?php
// ===========================
// Barangay System Configuration
// ===========================

// Start session once at the very beginning
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Error Reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'barangay_system');

define('APP_NAME', 'Barangay Demographic Profiling System');

// ===========================
// Database Connection (mysqli)
// ===========================
if (!function_exists('get_db_connection')) {
    function get_db_connection(): mysqli {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            // Keep UI intact: do not echo here; log instead
            error_log('DB connection error: ' . $conn->connect_error);
            throw new RuntimeException('Database connection failed');
        }
        $conn->set_charset('utf8mb4');
        return $conn;
    }
}

// Create global connection instance
$conn = get_db_connection();

// ===========================
// Authentication Helpers
// ===========================
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return !empty($_SESSION['user']);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
}

// Adjust this to match roles you actually use, e.g. 'super_admin' or 'official'
if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin() {
        requireLogin();
        if (empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'super_admin') {
            header("Location: dashboard.php");
            exit();
        }
    }
}

// ===========================
// Utility Functions
// ===========================
if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header("Location: $url");
        exit();
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput(string $input): string {
        global $conn;
        return htmlspecialchars(trim($conn->real_escape_string($input)), ENT_QUOTES, 'UTF-8');
    }
}

// ===========================
// Data Retrieval Functions (mysqli) - UPDATED FOR CENSUS DATA
// ===========================

// General Data Functions - UPDATED TO USE CENSUS DATA
if (!function_exists('getResidentCount')) {
    function getResidentCount(): int {
        global $conn;
        $result = $conn->query("SELECT COUNT(*) FROM household_members");
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getHouseholdCount')) {
    function getHouseholdCount(): int {
        global $conn;
        $result = $conn->query("SELECT COUNT(*) FROM census_submissions");
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getUpcomingEventsCount')) {
    function getUpcomingEventsCount(): int {
        global $conn;
        $result = $conn->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getAgeDistribution')) {
    function getAgeDistribution(): array {
        global $conn;
        $data = [];
        $query = "
            SELECT 
                CASE 
                    WHEN age < 18 THEN '0-17'
                    WHEN age BETWEEN 18 AND 24 THEN '18-24'
                    WHEN age BETWEEN 25 AND 34 THEN '25-34'
                    WHEN age BETWEEN 35 AND 44 THEN '35-44'
                    WHEN age BETWEEN 45 AND 59 THEN '45-59'
                    ELSE '60+'
                END AS age_group,
                COUNT(*) as count
            FROM household_members
            WHERE age IS NOT NULL
            GROUP BY age_group
            ORDER BY age_group
        ";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getGenderDistribution')) {
    function getGenderDistribution(): array {
        global $conn;
        $data = [];
        $result = $conn->query("SELECT sex as gender, COUNT(*) as count FROM household_members WHERE sex IS NOT NULL GROUP BY sex");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getEmploymentStatus')) {
    function getEmploymentStatus(): array {
        global $conn;
        $data = [];
        $result = $conn->query("SELECT employment_status, COUNT(*) as count FROM household_members WHERE employment_status IS NOT NULL GROUP BY employment_status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getUpcomingEvents')) {
    function getUpcomingEvents(int $limit = 5): array {
        global $conn;
        $data = [];
        $stmt = $conn->prepare("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

// ===========================
// Barangay-Specific Data Retrieval Functions - UPDATED FOR CENSUS DATA
// ===========================

if (!function_exists('getResidentCountByBarangay')) {
    function getResidentCountByBarangay($barangay_id): int {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM household_members hm 
                               JOIN census_submissions cs ON hm.household_id = cs.id 
                               WHERE cs.barangay = ?");
        $stmt->bind_param("s", $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getHouseholdCountByBarangay')) {
    function getHouseholdCountByBarangay($barangay_id): int {
        global $conn;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM census_submissions WHERE barangay = ?");
        $stmt->bind_param("s", $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getAgeDistributionByBarangay')) {
    function getAgeDistributionByBarangay($barangay_id): array {
        global $conn;
        $data = [];
        $query = "
            SELECT 
                CASE 
                    WHEN hm.age < 18 THEN '0-17'
                    WHEN hm.age BETWEEN 18 AND 24 THEN '18-24'
                    WHEN hm.age BETWEEN 25 AND 34 THEN '25-34'
                    WHEN hm.age BETWEEN 35 AND 44 THEN '35-44'
                    WHEN hm.age BETWEEN 45 AND 59 THEN '45-59'
                    ELSE '60+'
                END AS age_group,
                COUNT(*) as count
            FROM household_members hm
            JOIN census_submissions cs ON hm.household_id = cs.id
            WHERE cs.barangay = ? AND hm.age IS NOT NULL
            GROUP BY age_group
            ORDER BY age_group
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getGenderDistributionByBarangay')) {
    function getGenderDistributionByBarangay($barangay_id): array {
        global $conn;
        $data = [];
        $stmt = $conn->prepare("SELECT hm.sex as gender, COUNT(*) as count 
                               FROM household_members hm 
                               JOIN census_submissions cs ON hm.household_id = cs.id 
                               WHERE cs.barangay = ? AND hm.sex IS NOT NULL 
                               GROUP BY hm.sex");
        $stmt->bind_param("s", $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getEmploymentStatusByBarangay')) {
    function getEmploymentStatusByBarangay($barangay_id): array {
        global $conn;
        $data = [];
        $stmt = $conn->prepare("SELECT hm.employment_status, COUNT(*) as count 
                               FROM household_members hm 
                               JOIN census_submissions cs ON hm.household_id = cs.id 
                               WHERE cs.barangay = ? AND hm.employment_status IS NOT NULL 
                               GROUP BY hm.employment_status");
        $stmt->bind_param("s", $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

// ===========================
// Census Data Functions
// ===========================
if (!function_exists('getCensusData')) {
    function getCensusData($barangay_id = null): array {
        $conn = get_db_connection();
        $data = [];

        $where = '';
        $params = [];
        $types = '';

        if ($barangay_id !== null && $barangay_id !== '') {
            $where = " WHERE cs.barangay = ?";
            $params[] = $barangay_id;
            $types .= 's';
        }

        // Total submissions
        $sql = "SELECT COUNT(*) AS total FROM census_submissions cs" . $where;
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        $data['total_submissions'] = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
        if (isset($stmt)) { $stmt->close(); }

        // Total household members
        $sql = "SELECT COUNT(*) AS total FROM household_members hm
                JOIN census_submissions cs ON hm.household_id = cs.id" . $where;
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        $data['total_members'] = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
        if (isset($stmt)) { $stmt->close(); }

        // Gender distribution
        $sql = "SELECT hm.sex AS sex, COUNT(*) AS count FROM household_members hm
                JOIN census_submissions cs ON hm.household_id = cs.id" . $where . " GROUP BY hm.sex";
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        $data['gender_distribution'] = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $data['gender_distribution'][] = $r;
        }
        if (isset($stmt)) { $stmt->close(); }

        // Age distribution
        $sql = "SELECT
                    CASE
                        WHEN hm.age < 18 THEN '0-17'
                        WHEN hm.age BETWEEN 18 AND 24 THEN '18-24'
                        WHEN hm.age BETWEEN 25 AND 34 THEN '25-34'
                        WHEN hm.age BETWEEN 35 AND 44 THEN '35-44'
                        WHEN hm.age BETWEEN 45 AND 59 THEN '45-59'
                        ELSE '60+'
                    END AS age_group,
                    COUNT(*) AS count
                FROM household_members hm
                JOIN census_submissions cs ON hm.household_id = cs.id" . $where . " GROUP BY age_group ORDER BY age_group";
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        $data['age_distribution'] = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $data['age_distribution'][] = $r;
        }
        if (isset($stmt)) { $stmt->close(); }

        // Employment status
        $sql = "SELECT hm.employment_status AS employment_status, COUNT(*) AS count FROM household_members hm
                JOIN census_submissions cs ON hm.household_id = cs.id" . $where . " GROUP BY hm.employment_status";
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }
        $data['employment_status'] = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $data['employment_status'][] = $r;
        }
        if (isset($stmt)) { $stmt->close(); }

        return $data;
    }
}

if (!function_exists('getAllCensusSubmissions')) {
    function getAllCensusSubmissions($barangay_id = null): array {
        $conn = get_db_connection();

        $where = '';
        $params = [];
        $types = '';

        if ($barangay_id !== null && $barangay_id !== '') {
            $where = " WHERE cs.barangay = ?";
            $params[] = $barangay_id;
            $types .= 's';
        }

        $sql = "SELECT cs.*,
                       COUNT(hm.id) AS member_count,
                       GROUP_CONCAT(hm.member_name SEPARATOR ', ') AS member_names
                FROM census_submissions cs
                LEFT JOIN household_members hm ON cs.id = hm.household_id" .
                $where . " GROUP BY cs.id ORDER BY cs.submitted_at DESC";

        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sql);
        }

        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                // fetch detailed members for this household
                $memberStmt = $conn->prepare("SELECT * FROM household_members WHERE household_id = ?");
                $householdId = (int)$row['id'];
                $memberStmt->bind_param("i", $householdId);
                $memberStmt->execute();
                $memberRes = $memberStmt->get_result();
                $row['members'] = $memberRes ? $memberRes->fetch_all(MYSQLI_ASSOC) : [];
                $memberStmt->close();

                $data[] = $row;
            }
        }
        if (isset($stmt)) { $stmt->close(); }

        return $data;
    }
}

// ===========================
// Barangay Management Functions
// ===========================

if (!function_exists('getAllBarangays')) {
    function getAllBarangays(): array {
        global $conn;
        $data = [];
        $result = $conn->query("SELECT * FROM barangay_registration ORDER BY barangay_name");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getBarangayById')) {
    function getBarangayById($barangay_id): ?array {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM barangay_registration WHERE id = ?");
        $stmt->bind_param("i", $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
}

if (!function_exists('getBarangayByName')) {
    function getBarangayByName($barangay_name): ?array {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM barangay_registration WHERE barangay_name = ?");
        $stmt->bind_param("s", $barangay_name);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }
}

// ===========================
// Utility Functions for Age Calculation
// ===========================

if (!function_exists('calculateAge')) {
    function calculateAge($birthdate) {
        if (empty($birthdate)) return 'N/A';
        
        $birthDate = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        return $age->y;
    }
}

// ===========================
// CSRF Protection
// ===========================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ===========================
// Error Handling
// ===========================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false; // Let the PHP internal handler handle it
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// ===========================
// Initialize Default Settings
// ===========================
$settings = [
    'public_access' => 1 // Default to public access enabled
];
?>