<?php
/**
 * Blood Bank 2 - Database Configuration
 * Supports both local (XAMPP/MySQL) and Render (Docker/PostgreSQL) environments
 */

// ======================== 🌍 ENVIRONMENT DETECTION ========================

$isRender = false;

// Render بيحط DATABASE_URL أو DB_HOST كـ Environment Variable
if (getenv('DATABASE_URL') || getenv('RENDER')) {
    $isRender = true;
}

// ======================== 🔗 DATABASE CONNECTION ========================

try {
    if ($isRender) {
        // ====== RENDER (PostgreSQL) ======
        $dbUrl = getenv('DATABASE_URL');
        
        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'];
            $port = $parsed['port'] ?? '5432';
            $user = $parsed['user'];
            $pass = $parsed['pass'];
            $dbname = ltrim($parsed['path'], '/');
        } else {
            // لو المتغيرات منفصلة
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $user = getenv('DB_USER') ?: 'postgres';
            $pass = getenv('DB_PASSWORD') ?: '';
            $dbname = getenv('DB_NAME') ?: 'bloodbank2_db';
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

    } else {
        // ====== LOCAL (XAMPP/MySQL) ======
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'bloodbank2_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // If database doesn't exist, try to create it (Local only)
            try {
                $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE $dbname");
            } catch (PDOException $e2) {
                die("Database connection failed: " . $e2->getMessage());
            }
        }
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ======================== 🛠️ DATABASE HELPER FUNCTIONS ========================

/**
 * Get the database driver type (mysql or pgsql)
 * Use this when writing SQL that differs between MySQL and PostgreSQL
 */
function dbDriver() {
    global $pdo;
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/**
 * Check if running on Render
 */
function isRender() {
    return (getenv('DATABASE_URL') || getenv('RENDER'));
}

/**
 * Auto-increment column syntax
 * MySQL: AUTO_INCREMENT
 * PostgreSQL: SERIAL
 */
function autoIncrement() {
    return isRender() ? 'SERIAL' : 'AUTO_INCREMENT';
}

/**
 * Current timestamp default
 * MySQL: CURRENT_TIMESTAMP
 * PostgreSQL: CURRENT_TIMESTAMP (same)
 */
function currentTimestamp() {
    return 'CURRENT_TIMESTAMP';
}

/**
 * Quote identifier (table/column names)
 * MySQL: `name`
 * PostgreSQL: "name"
 */
function quoteId($name) {
    return isRender() ? '"' . $name . '"' : '`' . $name . '`';
}

/**
 * LIMIT syntax (same in both, but useful for OFFSET)
 * MySQL: LIMIT offset, count
 * PostgreSQL: LIMIT count OFFSET offset
 */
function limitSql($count, $offset = null) {
    if ($offset !== null) {
        return isRender() 
            ? "LIMIT $count OFFSET $offset" 
            : "LIMIT $offset, $count";
    }
    return "LIMIT $count";
}

/**
 * String concatenation
 * MySQL: CONCAT(a, b)
 * PostgreSQL: a || b
 */
function concatSql(...$parts) {
    if (isRender()) {
        return implode(' || ', $parts);
    }
    return 'CONCAT(' . implode(', ', $parts) . ')';
}

/**
 * Random ordering
 * MySQL: ORDER BY RAND()
 * PostgreSQL: ORDER BY RANDOM()
 */
function randomOrder() {
    return isRender() ? 'RANDOM()' : 'RAND()';
}

/**
 * Date formatting
 * MySQL: DATE_FORMAT(date, format)
 * PostgreSQL: TO_CHAR(date, format)
 */
function dateFormatSql($column, $format) {
    if (isRender()) {
        // Convert MySQL format to PostgreSQL format if needed
        $pgFormat = str_replace(['%Y', '%m', '%d', '%H', '%i', '%s'], ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'], $format);
        return "TO_CHAR($column, '$pgFormat')";
    }
    return "DATE_FORMAT($column, '$format')";
}

// ======================== 🔐 SESSION & AUTH ========================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Flash message helper
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}