<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/auth.php';
start_session();
$t = $_GET['t'] ?? '';
if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
    redirect('admin/dashboard.php');
}
logout();
redirect('admin/index.php');
