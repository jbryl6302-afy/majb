<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اعداد قاعدة بيانات - بنك الدم 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 50px; }
        .setup-box { max-width: 700px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="setup-box">
    <h2 class="text-center mb-4">بنك الدم 2 - اعداد قاعدة البيانات</h2>

    <?php
    $host = 'localhost';
    $dbname = 'bloodbank2_db';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo '<div class="alert alert-success">تم الاتصال بخادم MySQL</div>';

        $pdo->exec("DROP DATABASE IF EXISTS $dbname");
        $pdo->exec("CREATE DATABASE $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<div class="alert alert-success">تم انشاء قاعدة البيانات <strong>'.$dbname.'</strong></div>';

        $pdo->exec("USE $dbname");

        $sql = file_get_contents(__DIR__ . '/init.sql');
        if ($sql) {
            $sql = preg_replace('/CREATE DATABASE.*?;/', '', $sql);
            $sql = preg_replace('/USE.*?;/', '', $sql);
            $pdo->exec($sql);
            echo '<div class="alert alert-success">تم استيراد الجداول والبيانات</div>';
        }

        echo '<div class="alert alert-info mt-4">';
        echo '<h5>بيانات الدخول الافتراضية:</h5>';
        echo '<table class="table table-sm">';
        echo '<tr><td><strong>مدير النظام</strong></td><td>admin@bloodbank2.com</td><td>password</td></tr>';
        echo '<tr><td><strong>طبيب</strong></td><td>ahmed@hospital.com</td><td>password</td></tr>';
        echo '<tr><td><strong>فني المختبر</strong></td><td>sami@bloodbank2.com</td><td>password</td></tr>';
        echo '<tr><td><strong>اخصائي التخزين</strong></td><td>omar@bloodbank2.com</td><td>password</td></tr>';
        echo '</table>';
        echo '</div>';

        echo '<div class="text-center mt-4">';
        echo '<a href="index.php" class="btn btn-primary btn-lg">الذهاب لتسجيل الدخول</a>';
        echo '</div>';

    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><strong>خطأ:</strong> ' . $e->getMessage() . '</div>';
        echo '<div class="alert alert-warning">تأكد من تشغيل MySQL في XAMPP!</div>';
    }
    ?>
</div>
</body>
</html>
