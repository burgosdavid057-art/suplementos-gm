<?php
declare(strict_types=1);

function admin_user(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    static $cache = null;
    if ($cache !== null && $cache['id'] === $_SESSION['admin_id']) return $cache;
    $stmt = db()->prepare('SELECT id, email, name, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $u = $stmt->fetch();
    return $cache = ($u ?: null);
}

function require_admin(): array {
    $u = admin_user();
    if (!$u) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/admin';
        redirect('/admin/login');
    }
    return $u;
}

function attempt_login(string $email, string $password): bool {
    $stmt = db()->prepare('SELECT id, password FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) return false;
    if (!password_verify($password, $u['password'])) return false;

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $u['id'];
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
