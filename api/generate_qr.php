<?php
require_once '../config/database.php';
header('Content-Type: image/png');

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$bag_id = $_GET['bag_id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM BloodBags WHERE bag_id = ?");
$stmt->execute([$bag_id]);
$bag = $stmt->fetch();

if (!$bag) {
    http_response_code(404);
    exit('Bag not found');
}

$qrData = json_encode([
    'bag_id' => $bag['bag_id'],
    'qr_code' => $bag['qr_code'],
    'blood_type' => $bag['blood_type'],
    'donation_date' => $bag['donation_date']
]);

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);
$image = @file_get_contents($qrUrl);

if ($image) {
    echo $image;
} else {
    $im = imagecreatetruecolor(300, 300);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $white);
    imagerectangle($im, 10, 10, 290, 290, $black);
    imagestring($im, 5, 50, 130, $bag['qr_code'], $black);
    imagestring($im, 3, 80, 160, $bag['blood_type'], $black);
    imagepng($im);
    imagedestroy($im);
}
