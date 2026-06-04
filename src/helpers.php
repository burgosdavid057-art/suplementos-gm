<?php
declare(strict_types=1);

// ─── HTML / output ──────────────────────────────────────
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int $cents): string {
    // Precios en COP enteros (no usamos centavos en COP).
    return '$' . number_format($cents, 0, ',', '.');
}

function slug(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? '';
    return trim($s, '-');
}

function order_number(): string {
    // SGM-YYYYMMDD-XXXX (random)
    return 'SGM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function asset(string $path): string {
    return rtrim(env('APP_URL', '') ?? '', '/') . '/' . ltrim($path, '/');
}

/**
 * Optimiza una URL de Cloudinary aplicando transformaciones:
 *   - f_auto: mejor formato (webp/avif/jpeg) según el navegador
 *   - q_auto:good: calidad automática
 *   - w_{N}: ancho objetivo (no escala hacia arriba si la fuente es más chica
 *     gracias a c_limit, así no pixelea fotos pequeñas)
 *
 * Si la URL no es de Cloudinary, devuelve la URL tal cual (no rompe nada
 * con imágenes externas o assets locales).
 */
function cld_image(?string $url, int $width = 800, string $crop = 'limit'): string {
    if (!$url || !is_string($url)) return '';
    if (!str_contains($url, 'res.cloudinary.com') && !str_contains($url, '/cloudinary')) {
        return $url;
    }
    // Insertar las transformaciones justo después de /upload/ — solo si
    // no las hay ya (idempotente).
    $w = max(80, min(2000, $width));
    $crop = preg_match('/^[a-z_]+$/', $crop) ? $crop : 'limit';
    $tx = "f_auto,q_auto:good,c_$crop,w_$w";
    return preg_replace(
        '#/upload/(?!(?:[a-z]_[a-z0-9_]+,?)+/)#',
        "/upload/$tx/",
        $url,
        1,
    ) ?? $url;
}

/**
 * Versión "thumbnail" de cld_image — ancho menor para tarjetas/listados.
 */
function cld_thumb(?string $url, int $width = 480): string {
    return cld_image($url, $width);
}

function url(string $path = '/'): string {
    return rtrim(env('APP_URL', '') ?? '', '/') . '/' . ltrim($path, '/');
}

// ─── HTTP ───────────────────────────────────────────────
function redirect(string $location, int $code = 302): never {
    header('Location: ' . $location, true, $code);
    exit;
}

function not_found(string $msg = 'No encontrado'): never {
    http_response_code(404);
    render('layouts/error', ['title' => '404', 'message' => $msg]);
    exit;
}

function bad_request(string $msg): never {
    http_response_code(400);
    render('layouts/error', ['title' => 'Solicitud inválida', 'message' => $msg]);
    exit;
}

function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Vistas ────────────────────────────────────────────
function render(string $view, array $data = []): void {
    $__view = $view;
    $__data = $data;
    extract($data, EXTR_SKIP);
    $viewPath = dirname(__DIR__) . '/src/views/' . $__view . '.phtml';
    if (!is_file($viewPath)) {
        throw new RuntimeException("Vista no existe: $__view");
    }
    require $viewPath;
}

function render_layout(string $view, array $data = [], string $layout = 'layouts/store'): void {
    ob_start();
    render($view, $data);
    $content = ob_get_clean();
    render($layout, array_merge($data, ['content' => $content]));
}

// ─── Forms / CSRF ───────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
        bad_request('Token CSRF inválido. Recarga la página y vuelve a intentar.');
    }
}

// ─── Flash messages ────────────────────────────────────
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}

// ─── Request helpers ───────────────────────────────────
function request_path(): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';
    return '/' . trim($path, '/');
}

function method(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function input(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
