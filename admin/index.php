<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
require_installed();
if (is_admin()) {
    redirect('admin/dashboard.php');
}
$error = '';
$lock = login_locked(ip_bucket());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($lock > 0) {
        $error = 'Çok fazla hatalı deneme. ' . (int) ceil($lock / 60) . ' dakika sonra tekrar deneyin.';
    } elseif (attempt_login(post('password'))) {
        redirect('admin/dashboard.php');
    } else {
        $lock = login_locked(ip_bucket());
        $error = $lock > 0 ? 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.' : 'Şifre hatalı.';
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Yönetim Girişi · <?= e(setting('site_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=13">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1><?= e(setting('site_name')) ?></h1>
    <p class="muted">Yönetim paneline giriş</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Admin şifresi
            <input type="password" name="password" required autofocus autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
    </form>
    <p class="muted" style="text-align:center;margin-top:16px;font-size:12px"><a href="<?= e(url('')) ?>">← Menüye dön</a></p>
</div>
</body>
</html>
