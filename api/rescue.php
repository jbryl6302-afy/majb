<?php
require_once '../config/database.php';
require_once '../includes/rescue_functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$action = $_GET['action'] ?? 'status';
switch ($action) {
    case 'status':
        echo json_encode(rescueCounts($pdo));
        break;
    case 'suggestions':
        $stmt = $pdo->query("SELECT t.transfer_id, t.priority_score, t.status, b.qr_code, b.blood_type,
                             DATEDIFF(b.expiry_date, NOW()) AS days_left, f.name AS facility_name
                             FROM Transfers t JOIN BloodBags b ON t.bag_id = b.bag_id
                             LEFT JOIN Facilities f ON t.to_facility_id = f.facility_id
                             WHERE t.status='Suggested' ORDER BY t.priority_score DESC");
        echo json_encode($stmt->fetchAll());
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
