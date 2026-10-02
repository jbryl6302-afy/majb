<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
if (!isLoggedIn() || !hasRole('Admin')) redirect('../../dashboard.php');

$request_id = $_GET['request_id'] ?? 0;

if ($request_id) {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE BloodRequests SET status='Approved' WHERE request_id=?")->execute([$request_id]);
        $bags = $pdo->prepare("SELECT bag_id FROM RequestAllocations WHERE request_id=?");
        $bags->execute([$request_id]);
        while ($bag = $bags->fetch()) {
            $pdo->prepare("UPDATE BloodBags SET status='Used' WHERE bag_id=?")->execute([$bag['bag_id']]);
            logTracking($pdo, $bag['bag_id'], 'تم الاستخدام للطلب #' . $request_id, 'المستشفى', $_SESSION['user_id']);
        }
        $pdo->prepare("UPDATE BloodRequests SET status='Fulfilled', fulfilled_date=NOW() WHERE request_id=?")->execute([$request_id]);
        $pdo->commit();
        setFlash('success', "تمت الموافقة على الطلب #$request_id وتنفيذه!");
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', "خطأ: " . $e->getMessage());
    }
}
redirect('list.php');
