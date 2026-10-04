<?php
require_once __DIR__ . '/../config/database.php';
if (!isLoggedIn()) {
    redirect(__DIR__ . '/../index.php');
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'نظام بنك الدم'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* ====== ثيم موحّد (نفس ألوان لوحة التحكم) ====== */
        body { font-family: "Cairo", sans-serif; background: #ffffff; }
        .sidebar { min-height: 100vh; height: 100vh; overflow-y: auto; background: #2C3E50; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .sidebar a { color: #fff; text-decoration: none; padding: 15px 20px; display: block; transition: 0.3s; border-right: 4px solid transparent; text-align: right; }
        .sidebar a:hover { background: rgba(255,255,255,.08); }
        .sidebar a.active { background: #3A4D63; border-right-color: #DC3545; font-weight: 700; }
        .sidebar .section-title { color: #aab7c4; font-size: .8rem; padding: 12px 20px 4px; text-align: right; }
        .sidebar .text-danger { color: #ff6b78 !important; }
        .main-content { margin-right: 260px; padding: 20px; }
        .stat-card { border-radius: 10px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon { font-size: 2.5rem; opacity: 0.8; }
        .blood-badge { font-size: 1rem; padding: 8px 15px; }
        .timeline-item { border-right: 3px solid #0d6efd; padding-right: 15px; margin-bottom: 15px; }
        .card { background: #ffffff; }
        .card-header.bg-dark { background: #212529 !important; }
    </style>
</head>
<body>
