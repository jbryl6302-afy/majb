<?php
/**
 * Helper Functions
 */

/**
 * Generate unique QR code string
 */
function generateQRCode() {
    return 'BAG-' . strtoupper(uniqid()) . '-' . date('Ymd');
}

/**
 * Calculate expiry date (42 days from donation)
 */
function calculateExpiry($donationDate) {
    return date('Y-m-d H:i:s', strtotime($donationDate . ' +42 days'));
}

/**
 * Get blood type compatibility
 */
function getCompatibleTypes($bloodType) {
    $map = [
        'A+'  => ['A+', 'A-', 'O+', 'O-'],
        'A-'  => ['A-', 'O-'],
        'B+'  => ['B+', 'B-', 'O+', 'O-'],
        'B-'  => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+'  => ['O+', 'O-'],
        'O-'  => ['O-']
    ];
    return $map[$bloodType] ?? ['O-'];
}

/**
 * Log tracking action
 */
function logTracking($pdo, $bagId, $action, $location, $userId) {
    $stmt = $pdo->prepare("INSERT INTO TrackingLog (bag_id, action, location, performed_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$bagId, $action, $location, $userId]);
}

/**
 * Get status badge HTML
 */
function statusBadge($status) {
    $colors = [
        'Available' => 'success',
        'Reserved'  => 'warning',
        'Used'      => 'info',
        'Expired'   => 'danger',
        'Discarded' => 'dark',
        'Pending'   => 'warning',
        'Approved'  => 'primary',
        'Fulfilled' => 'success',
        'Cancelled' => 'danger',
        'Normal'    => 'info',
        'Urgent'    => 'warning',
        'Critical'  => 'danger'
    ];
    $labels = [
        'Available' => 'متاحة',
        'Reserved'  => 'محجوزة',
        'Used'      => 'مستخدمة',
        'Expired'   => 'منتهية الصلاحية',
        'Discarded' => 'تالفة / مستبعدة',
        'Pending'   => 'قيد الانتظار',
        'Approved'  => 'معتمدة',
        'Fulfilled' => 'تم الصرف',
        'Cancelled' => 'ملغاة',
        'Normal'    => 'عادي',
        'Urgent'    => 'عاجل',
        'Critical'  => 'حرج'
    ];
    $color = $colors[$status] ?? 'secondary';
    $label = $labels[$status] ?? htmlspecialchars($status);
    return "<span class='badge bg-{$color}'>{$label}</span>";
}

/**
 * Get QR code image URL
 * ملاحظة: دي بتولّد QR من سيرفر خارجي، فمحتاجة إنترنت.
 * لو الشبكة الداخلية من غير نت، قولي وأديك نسخة بتولّد QR محلياً.
 */
function getQrCodeUrl($data, $size = 100) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size='
           . $size . 'x' . $size
           . '&data=' . urlencode($data);
}

/**
 * Format date
 */
function formatDate($date) {
    return date('d M Y, H:i', strtotime($date));
}

/**
 * Get list of column names for a table (for backward compatibility)
 */
function tableColumns($pdo, $table) {
    static $cache = [];
    if (!isset($cache[$table])) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $cache[$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cache[$table];
}