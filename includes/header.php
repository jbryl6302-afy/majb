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
        body { font-family: "Cairo", sans-serif; background: #F5E9DD; }
        .sidebar { min-height: 100vh; background: #8B5E3C; position: fixed; right: 0; width: 260px; }
        .sidebar a { color: #FBF3E8; text-decoration: none; padding: 15px 20px; display: block; transition: 0.3s; border-right: 4px solid transparent; text-align: right; }
        .sidebar a:hover, .sidebar a.active { background: #6E4A2E; border-right-color: #D8A47F; }
        .main-content { margin-right: 260px; padding: 20px; }
        .stat-card { border-radius: 10px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon { font-size: 2.5rem; opacity: 0.8; }
        .blood-badge { font-size: 1rem; padding: 8px 15px; }
        .timeline-item { border-right: 3px solid #C19A6B; padding-right: 15px; margin-bottom: 15px; }
        .card { background: #FFFBF5; }
        .card-header.bg-dark { background: #8B5E3C !important; }
        .stat-card.bg-primary { background: #C19A6B !important; }
        .stat-card.bg-success { background: #A9784D !important; }
        .stat-card.bg-dark { background: #6E4A2E !important; }
        .stat-card.bg-danger { background: #B5764A !important; }
        .stat-card.bg-warning { background: #D8A47F !important; color: #4A2C17 !important; }
    </style>
</head>
<body>
