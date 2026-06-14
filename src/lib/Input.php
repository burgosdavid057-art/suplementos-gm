<?php
declare(strict_types=1);

class Input {
    
    private static function raw(string $key, array $src = []): ?string {
        if (!empty($src)) {
            $v = $src[$key] ?? null;
        } else {
            $v = $_POST[$key] ?? $_GET[$key] ?? null;
        }
        return is_string($v) ? $v : null;
    }

    
    private static function stripControl(string $s, bool $allowNewlines = false): string {
        $pattern = $allowNewlines
            ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'
            : '/[\x00-\x1F\x7F]/u';
        return preg_replace($pattern, '', $s) ?? $s;
    }

    
    public static function text(string $key, int $maxLen = 255, array $src = []): ?string {
        $v = self::raw($key, $src);
        if ($v === null) return null;
        $v = self::stripControl($v, false);
        $v = trim($v);
        if ($v === '') return null;
        return mb_substr($v, 0, $maxLen, 'UTF-8');
    }

    
    public static function textRequired(string $key, int $maxLen = 255): ?string {
        return self::text($key, $maxLen);
    }

    
    public static function textArea(string $key, int $maxLen = 2000, array $src = []): ?string {
        $v = self::raw($key, $src);
        if ($v === null) return null;
        $v = self::stripControl($v, true);
        
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = trim($v);
        if ($v === '') return null;
        return mb_substr($v, 0, $maxLen, 'UTF-8');
    }

    
    public static function email(string $key, array $src = []): ?string {
        $v = self::text($key, 320, $src);
        if ($v === null) return null;
        $v = mb_strtolower($v, 'UTF-8');
        $valid = filter_var($v, FILTER_VALIDATE_EMAIL);
        return $valid === false ? null : (string) $valid;
    }

    
    public static function phone(string $key, int $maxLen = 20, array $src = []): ?string {
        $v = self::raw($key, $src);
        if ($v === null) return null;
        $v = preg_replace('/[^\d+]/', '', $v) ?? '';
        $v = trim($v);
        
        if ($v !== '' && strpos($v, '+') !== false) {
            $v = '+' . str_replace('+', '', $v);
        }
        if ($v === '' || $v === '+') return null;
        return substr($v, 0, $maxLen);
    }

    
    public static function phoneDigits(string $key, int $maxLen = 15, array $src = []): ?string {
        $v = self::raw($key, $src);
        if ($v === null) return null;
        $v = preg_replace('/\D/', '', $v) ?? '';
        if ($v === '') return null;
        return substr($v, 0, $maxLen);
    }

    
    public static function docId(string $key, int $maxLen = 30, array $src = []): ?string {
        $v = self::raw($key, $src);
        if ($v === null) return null;
        $v = preg_replace('/[^0-9A-Za-z\-]/', '', $v) ?? '';
        if ($v === '') return null;
        return substr($v, 0, $maxLen);
    }

    
    public static function int(string $key, ?int $default = null, ?int $min = null, ?int $max = null, array $src = []): ?int {
        $v = !empty($src) ? ($src[$key] ?? null) : ($_POST[$key] ?? $_GET[$key] ?? null);
        if ($v === null || $v === '' || !is_numeric($v)) return $default;
        $n = (int) $v;
        if ($min !== null && $n < $min) $n = $min;
        if ($max !== null && $n > $max) $n = $max;
        return $n;
    }

    
    public static function bool(string $key, array $src = []): bool {
        $v = !empty($src) ? ($src[$key] ?? null) : ($_POST[$key] ?? $_GET[$key] ?? null);
        if ($v === null) return false;
        if (is_bool($v)) return $v;
        if (is_int($v))  return $v !== 0;
        if (!is_string($v)) return false;
        $v = strtolower(trim($v));
        return in_array($v, ['1', 'true', 'on', 'yes'], true);
    }

    
    public static function enum(string $key, array $allowed, ?string $default = null, array $src = []): ?string {
        $v = self::text($key, 64, $src);
        if ($v === null) return $default;
        return in_array($v, $allowed, true) ? $v : $default;
    }

    
    public static function slug(string $key, int $maxLen = 120, array $src = []): ?string {
        $v = self::raw($key, $src);
        if ($v === null) return null;
        $v = mb_strtolower(trim($v), 'UTF-8');
        
        $v = strtr($v, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        $v = preg_replace('/[^a-z0-9\-]/', '-', $v) ?? '';
        $v = preg_replace('/-+/', '-', $v) ?? '';
        $v = trim($v, '-');
        if ($v === '') return null;
        return substr($v, 0, $maxLen);
    }

    
    public static function url(string $key, int $maxLen = 2048, array $src = []): ?string {
        $v = self::text($key, $maxLen, $src);
        if ($v === null) return null;
        $valid = filter_var($v, FILTER_VALIDATE_URL);
        if ($valid === false) return null;
        $scheme = strtolower((string) parse_url($valid, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) return null;
        return $valid;
    }

    
    public static function stringArray(string $key, int $itemMaxLen = 2048, int $maxItems = 50): array {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        if (!is_array($v)) return [];
        $out = [];
        foreach (array_slice($v, 0, $maxItems) as $item) {
            if (!is_string($item)) continue;
            $clean = self::stripControl($item, false);
            $clean = trim($clean);
            if ($clean === '') continue;
            $out[] = mb_substr($clean, 0, $itemMaxLen, 'UTF-8');
        }
        return $out;
    }

    
    public static function urlInHosts(string $key, array $allowedHosts, int $maxLen = 2048, array $src = []): ?string {
        $u = self::url($key, $maxLen, $src);
        if ($u === null) return null;
        $host = strtolower((string) parse_url($u, PHP_URL_HOST));
        foreach ($allowedHosts as $allowed) {
            if ($host === strtolower($allowed) || str_ends_with($host, '.' . strtolower($allowed))) {
                return $u;
            }
        }
        return null;
    }
}
