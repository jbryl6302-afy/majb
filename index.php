<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        redirect('dashboard.php');
    } else {
        $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بنك الدم - تسجيل الدخول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Cairo", sans-serif; background: linear-gradient(135deg, #2C3E50 0%, #3A4D63 100%); min-height: 100vh; }
        .login-box { background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .btn-login { background: #2C3E50; border: none; }
        .btn-login:hover { background: #3A4D63; }
        .blood-icon { font-size: 3rem; color: #DC3545; }
        .login-box { position: relative; }
        .bushra-stamp {
            position: absolute;
            bottom: -18px;
            left: 25px;
            transform: rotate(-12deg);
            font-family: "Cairo", sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: #DC3545;
            border: 2px solid #DC3545;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.75;
            background: rgba(255,255,255,0.6);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-box p-5 mt-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-tint blood-icon"></i>
                        <h3 class="mt-2 text-primary fw-bold">نظام بنك الدم الذكي</h3>
                       
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" placeholder="ادخل البريد " required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-lock"></i> كلمة المرور</label>
                            <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required>
                        </div>
                        <button type="submit" class="btn btn-login text-white w-100 py-2 fw-bold">
                            تسجيل الدخول
                        </button>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</body>
</html>
