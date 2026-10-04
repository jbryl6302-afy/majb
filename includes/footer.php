
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php'): ?>
<style>
.back-home {
    position: fixed !important;
    bottom: 16px !important;
    left: 16px !important;
    right: auto !important;
    z-index: 1050;
    background: #5A3921; color: #F5E9DC;
    padding: 10px 18px; border-radius: 999px;
    box-shadow: 0 4px 12px rgba(0,0,0,.3);
    text-decoration: none; font-weight: 600;
}
.back-home:hover { background: #3B2410; color: #fff; }
body { padding-bottom: 70px; }
</style>
<a href="/dashboard.php" class="back-home">
    <i class="fas fa-home"></i> الرئيسية
</a>
<?php endif; ?>
</body>
</html>
