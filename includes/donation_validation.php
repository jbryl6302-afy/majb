<?php
/**
 * Blood Bank 2 - Donation Validation & QR Code Functions
 */

/**
 * Check if donor can donate (last donation must be >= 3 months ago)
 */
function canDonate($lastDonationDate) {
    if (empty($lastDonationDate) || $lastDonationDate === '0000-00-00') {
        return [
            'can_donate' => true,
            'message' => 'متبرع جديد - مسموح بالتبرع',
            'days_remaining' => 0
        ];
    }

    $lastDate = new DateTime($lastDonationDate);
    $today = new DateTime();
    $interval = $lastDate->diff($today);

    $totalDays = ($interval->y * 365) + ($interval->m * 30) + $interval->d;
    $minDays = 90; // 3 months

    if ($totalDays < $minDays) {
        $remaining = $minDays - $totalDays;
        $nextDate = clone $lastDate;
        $nextDate->modify('+3 months');

        return [
            'can_donate' => false,
            'message' => 'لا يمكن التبرع الآن. اخر تبرع بتاريخ: ' . $lastDonationDate . 
                        '. يجب الانتظار 3 اشهر. التاريخ المسموح: ' . $nextDate->format('Y-m-d'),
            'days_remaining' => $remaining
        ];
    }

    return [
        'can_donate' => true,
        'message' => 'مسموح بالتبرع (اخر تبرع قبل ' . $interval->m . ' شهور و ' . $interval->d . ' يوم)',
        'days_remaining' => 0
    ];
}

/**
 * Generate QR Code URL using QRServer API
 */
function generateQRUrl($data, $size = 200) {
    $encoded = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}";
}

/**
 * Display QR Code card with download & print
 */
function displayQRCode($bagId, $bloodType, $donationDate = null) {
    $qrData = "BAG:$bagId|TYPE:$bloodType";
    if ($donationDate) {
        $qrData .= "|DATE:$donationDate";
    }
    $qrUrl = generateQRUrl($qrData, 250);

    echo '<div class="card mt-4">';
    echo '<div class="card-header bg-primary text-white">';
    echo '<h5 class="mb-0"><i class="fas fa-qrcode"></i> رمز QR - تتبع الكيس</h5>';
    echo '</div>';
    echo '<div class="card-body text-center">';
    echo '<img src="' . htmlspecialchars($qrUrl) . '" alt="QR Code" class="img-fluid border p-2" style="max-width:250px; background:white;">';
    echo '<p class="mt-2 text-muted font-monospace small">' . htmlspecialchars($qrData) . '</p>';
    echo '<a href="' . htmlspecialchars($qrUrl) . '" download="bag_' . $bagId . '_qr.png" class="btn btn-primary me-2">';
    echo '<i class="fas fa-download"></i> تحميل QR';
    echo '</a>';
    echo '<button onclick="window.print()" class="btn btn-outline-secondary">';
    echo '<i class="fas fa-print"></i> طباعة';
    echo '</button>';
    echo '<div class="alert alert-info mt-3 mb-0">';
    echo '<i class="fas fa-info-circle"></i> امسح الرمز باي تطبيق QR لفحص تفاصيل الكيس';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * Validate donor before adding to database
 */
function validateDonorEligibility($lastDonationDate) {
    return canDonate($lastDonationDate);
}
