<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

current_lang('tr');   // admin paneli her zaman Turkce

const LOGIN_GLOBAL_KEY = '*';   // login_attempts tablosunda tum adreslerin ortak sayaci

function is_admin(): bool
{
    start_session();
    if (empty($_SESSION['admin'])) {
        return false;
    }
    // Uzun sure hareketsiz kalan ya da sifre degistikten once acilmis oturumlar gecersizdir
    $idle = time() - (int) ($_SESSION['seen'] ?? 0);
    if ($idle > SESSION_IDLE_SECONDS || (string) ($_SESSION['epoch'] ?? '') !== setting('session_epoch')) {
        $_SESSION = [];
        return false;
    }
    $_SESSION['seen'] = time();
    return true;
}

function require_admin(): void
{
    require_installed();
    if (!is_admin()) {
        redirect('admin/index.php');
    }
}

/** Girisi basarili sayip admin oturumunu baslatir (kurulum ekrani da kullanir). */
function admin_session_begin(): void
{
    start_session();
    session_regenerate_id(true);
    unset($_SESSION['csrf']);   // giris oncesi verilmis CSRF anahtari gecersiz olsun
    $_SESSION['admin'] = true;
    $_SESSION['seen'] = time();
    $_SESSION['epoch'] = setting('session_epoch');
}

function login_locked(string $key): int
{
    $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE ip = ?');
    $stmt->execute([$key]);
    $until = (int) ($stmt->fetchColumn() ?: 0);
    return $until > time() ? $until - time() : 0;
}

/**
 * Bir giris denemesini SIFRE KONTROLUNDEN ONCE, tek bir yazma kilidi altinda kaydeder; boylece ayni anda gelen
 * istekler siniri asamaz. Adres kilitliyse false doner. Tum adreslerin ortak sayaci (15 dk'lik pencere) esigi
 * asarsa her adrese tek deneme hakki kalir: dagitik denemeler yavaslar ama dogru sifreyi bilen yine girebilir.
 */
function login_register_attempt(string $key): bool
{
    $pdo = db();
    $now = time();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $row = $pdo->prepare('SELECT attempts, locked_until FROM login_attempts WHERE ip = ?');
        $row->execute([LOGIN_GLOBAL_KEY]);
        $global = $row->fetch() ?: ['attempts' => 0, 'locked_until' => 0];
        if ((int) $global['locked_until'] <= $now) {
            $global = ['attempts' => 0, 'locked_until' => $now + LOGIN_LOCK_SECONDS];   // yeni pencere
        }
        $underAttack = (int) $global['attempts'] >= LOGIN_GLOBAL_MAX;

        $row->execute([$key]);
        $mine = $row->fetch() ?: ['attempts' => 0, 'locked_until' => 0];
        if ((int) $mine['locked_until'] > $now) {
            $pdo->exec('COMMIT');
            return false;
        }
        $attempts = (int) $mine['attempts'] + 1;
        $lockedUntil = 0;
        if ($attempts >= ($underAttack ? 1 : LOGIN_MAX_ATTEMPTS)) {
            $attempts = 0;
            $lockedUntil = $now + LOGIN_LOCK_SECONDS;
        }
        $save = $pdo->prepare('INSERT OR REPLACE INTO login_attempts (ip, attempts, locked_until) VALUES (?, ?, ?)');
        $save->execute([$key, $attempts, $lockedUntil]);
        $save->execute([LOGIN_GLOBAL_KEY, (int) $global['attempts'] + 1, (int) $global['locked_until']]);
        $pdo->exec('COMMIT');
        return true;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        error_log('Giris siniri hatasi: ' . $t->getMessage());
        return false;   // sayac yazilamiyorsa deneme kabul edilmez
    }
}

function login_success(string $key): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$key]);
    $pdo->prepare('UPDATE login_attempts SET attempts = MAX(attempts - 1, 0) WHERE ip = ?')->execute([LOGIN_GLOBAL_KEY]);
    admin_session_begin();
}

function attempt_login(string $password): bool
{
    $key = ip_bucket();
    if (!login_register_attempt($key)) {
        return false;
    }
    $hash = setting('admin_password_hash');
    if ($hash !== '' && password_verify($password, $hash)) {
        login_success($key);
        return true;
    }
    return false;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
