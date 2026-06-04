<?php
declare(strict_types=1);

// Punto de carga común a CLI y web. Lee .env, arranca sesión, registra
// autoload básico para src/.

date_default_timezone_set('America/Bogota');

// ─── .env loader (formato KEY="value" o KEY=value) ──────
function load_env(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ((str_starts_with($v, '"') && str_ends_with($v, '"'))
            || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}
load_env(dirname(__DIR__) . '/.env');

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

// ─── Autoload simple para src/{models,controllers} ──────
spl_autoload_register(function (string $class): void {
    $base = __DIR__;
    $relative = str_replace('\\', '/', $class) . '.php';
    foreach (['/models/', '/controllers/', '/lib/', '/'] as $sub) {
        $p = $base . $sub . $relative;
        if (is_file($p)) { require $p; return; }
    }
});

// ─── Cargas eager (funciones, no clases) ────────────────
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
// Icons.php registra una función global `icon()` que usan los templates,
// la autoloader solo dispara con classes — esta hay que cargarla eager.
require __DIR__ . '/lib/Icons.php';

// ─── Sesión ────────────────────────────────────────────
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name(env('SESSION_NAME', 'sgm_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
