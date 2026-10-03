<?php
/**
 * Blood Bank 2 - Database Configuration
 * الافتراضي: MySQL (محلي XAMPP أو TiDB Cloud / أي MySQL بعيد)
 * PostgreSQL يشتغل فقط لو حددت DB_DRIVER=pgsql صراحةً
 *
 * متغيرات البيئة (للاستضافة):
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
 *   DB_SSL=1            -> تفعيل تشفير SSL (مطلوب لـ TiDB Cloud)
 *   DB_SSL_CA=/path     -> (اختياري) مسار شهادة CA، الافتراضي شهادات النظام
 *   DB_DRIVER=pgsql     -> (اختياري) لاستخدام PostgreSQL
 */

// ======================== 🌍 DRIVER SELECTION ========================

$dbDriverName = strtolower(getenv('DB_DRIVER') ?: 'mysql');
$usePg = ($dbDriverName === 'pgsql');
$remoteHost = getenv('DB_HOST'); // لو موجود = اتصال بعيد (TiDB وغيره)

// ======================== 🔗 DATABASE CONNECTION ========================

try {
    if ($usePg) {
        // ====== PostgreSQL (اختياري) ======
        $dbUrl = getenv('DATABASE_URL');

        if ($dbUrl) {
            $parsed = parse_url($dbUrl);
            $host = $parsed['host'];
            $port = $parsed['port'] ?? '5432';
            $user = $parsed['user'];
            $pass = $parsed['pass'];
            $dbname = ltrim($parsed['path'], '/');
        } else {
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
        // ====== MySQL (محلي أو بعيد مثل TiDB Cloud) ======
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'bloodbank2_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // SSL (مطلوب لـ TiDB Cloud)
        if (getenv('DB_SSL') === '1') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('DB_SSL_CA') ?: '/etc/ssl/certs/ca-certificates.crt';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // إنشاء الداتابيس تلقائياً فقط في الوضع المحلي (بدون DB_HOST)
            if (!$remoteHost) {
                try {
                    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$dbname`");
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                } catch (PDOException $e2) {
                    die("Database connection failed.");
                }
            } else {
                // لا تعرض تفاصيل الخطأ للزوار (فيها معلومات اتصال). التفاصيل في سجل السيرفر.
                error_log("DB connection failed: " . $e->getMessage());
                die("Database connection failed.");
            }
        }
    }

} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    die("Database connection failed.");
}

// ======================== 🛠️ DATABASE HELPER FUNCTIONS ========================

/**
 * Get the database driver type (mysql or pgsql)
 */
function dbDriver() {
    global $pdo;
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/**
 * ملاحظة: اسم الدالة موروث من الكود القديم. معناها الآن "هل نستخدم PostgreSQL؟"
 * (كانت تعني "هل نحن على Render؟"). تم الإبقاء على الاسم حتى لا ينكسر باقي الكود.
 */
function isRender() {
    return strtolower(getenv('DB_DRIVER') ?: 'mysql') === 'pgsql';
}

/**
 * Auto-increment column syntax
 */
function autoIncrement() {
    return isRender() ? 'SERIAL' : 'AUTO_INCREMENT';
}

function currentTimestamp() {
    return 'CURRENT_TIMESTAMP';
}

/**
 * Quote identifier (table/column names)
 */
function quoteId($name) {
    return isRender() ? '"' . $name . '"' : '`' . $name . '`';
}

/**
 * LIMIT syntax
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
 */
function concatSql(...$parts) {
    if (isRender()) {
        return implode(' || ', $parts);
    }
    return 'CONCAT(' . implode(', ', $parts) . ')';
}

/**
 * Random ordering
 */
function randomOrder() {
    return isRender() ? 'RANDOM()' : 'RAND()';
}

/**
 * Date formatting
 */
function dateFormatSql($column, $format) {
    if (isRender()) {
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