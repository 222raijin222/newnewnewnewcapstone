<?php
/**
 * Helper Functions for Barangay Profiling System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Calculate age from birthdate
 */
if (!function_exists('calculateAge')) {
    function calculateAge($birthdate) {
        if (empty($birthdate)) return 'N/A';
        try {
            $birthDate = new DateTime($birthdate);
            $today = new DateTime();
            return $today->diff($birthDate)->y;
        } catch (Exception $e) {
            return 'N/A';
        }
    }
}

/**
 * Authenticate by username or email.
 * Checks users (barangay officials) first, then super_admin table.
 */
if (!function_exists('authenticate_user')) {
    function authenticate_user(string $login, string $password) {
        require_once __DIR__ . '/config.php';

        try {
            // Use constants from config.php
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            return false;
        }

        // 🔹 Check in `users` table (barangay officials)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1");
        $stmt->execute([':u' => $login]);
        $userRow = $stmt->fetch();

        if ($userRow && password_verify($password, $userRow['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $userRow;
            return $userRow;
        }

        // 🔹 If not found, check `super_admin` table
        $stmt2 = $pdo->prepare("SELECT * FROM superadmin WHERE username = :u OR email = :u LIMIT 1");
        $stmt2->execute([':u' => $login]);
        $superAdmin = $stmt2->fetch();

        if ($superAdmin && password_verify($password, $superAdmin['password'])) {
            session_regenerate_id(true);
            $superAdmin['role'] = 'superadmin';
            $_SESSION['user'] = $superAdmin;
            return $superAdmin;
        }

        return false; // ❌ Invalid login
    }
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return !empty($_SESSION['user']);
    }
}

/**
 * Check if user is admin or superadmin
 */
if (!function_exists('isAdmin')) {
    function isAdmin(): bool {
        if (empty($_SESSION['user'])) return false;
        $role = $_SESSION['user']['role'] ?? $_SESSION['user']['user_role'] ?? $_SESSION['user']['type'] ?? null;
        return in_array($role, ['admin', 'superadmin'], true);
    }
}

/**
 * Check if user is superadmin
 */
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin(): bool {
        if (empty($_SESSION['user'])) return false;
        $role = $_SESSION['user']['role'] ?? $_SESSION['user']['user_role'] ?? $_SESSION['user']['type'] ?? null;
        return $role === 'superadmin';
    }
}

/**
 * Require superadmin access
 */
if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin(): void {
        if (empty($_SESSION['user'])) {
            header('Location: login.php');
            exit();
        }
        if (!isSuperAdmin()) {
            header('Location: dashboard.php');
            exit();
        }
    }
}
?>
