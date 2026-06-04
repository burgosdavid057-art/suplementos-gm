<?php
declare(strict_types=1);

/**
 * Cliente HTTP para la API de Alegra (Colombia).
 * Docs: https://developer.alegra.com/reference
 *
 * Auth: Basic con email:api_token (lo mismo que el Next.js).
 */
class Alegra {
    private const API_BASE   = 'https://api.alegra.com/api/v1';
    private const PAGE_LIMIT = 30; // máximo permitido por Alegra

    public static function isConfigured(): bool {
        return !empty(env('ALEGRA_EMAIL')) && !empty(env('ALEGRA_API_TOKEN'));
    }

    private static function authHeader(): string {
        $email = env('ALEGRA_EMAIL');
        $token = env('ALEGRA_API_TOKEN');
        if (!$email || !$token) {
            throw new RuntimeException('ALEGRA_EMAIL o ALEGRA_API_TOKEN no están configurados en .env');
        }
        return 'Basic ' . base64_encode("$email:$token");
    }

    /** GET genérico, retorna JSON decodificado (array). */
    private static function call(string $path): array {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . self::authHeader(),
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Alegra HTTP error: $err");
        }
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            throw new RuntimeException("Alegra $code: " . substr((string)$body, 0, 200));
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Alegra: respuesta no JSON válida');
        }
        return $data;
    }

    /**
     * Trae TODOS los items paginando hasta agotar.
     * Cada item = array asociativo según el shape de Alegra.
     *
     * @param callable|null $onProgress fn(int $start, int $count): void
     */
    public static function fetchAllItems(?callable $onProgress = null): array {
        $all   = [];
        $start = 0;

        while (true) {
            $page = self::call(sprintf(
                '/items?start=%d&limit=%d&order_direction=ASC&order_field=id',
                $start, self::PAGE_LIMIT
            ));
            $count = count($page);
            $all = array_merge($all, $page);
            if ($onProgress) $onProgress($start, $count);
            if ($count < self::PAGE_LIMIT) break;
            $start += self::PAGE_LIMIT;
            // Pausa para no saturar rate-limits.
            usleep(150_000);
        }
        return $all;
    }

    /** Devuelve el precio "main" del item, o el primero, redondeado a int. */
    public static function pickPrice(array $item): int {
        $prices = $item['price'] ?? [];
        if (!is_array($prices) || empty($prices)) return 0;
        $main = null;
        foreach ($prices as $p) {
            if (!empty($p['main'])) { $main = $p; break; }
        }
        $price = $main['price'] ?? $prices[0]['price'] ?? 0;
        return (int) round((float) $price);
    }

    /** Extrae la marca de los customFields (key=brand|marca) o null. */
    public static function extractBrand(array $item): ?string {
        $cfs = $item['customFields'] ?? [];
        if (!is_array($cfs)) return null;
        foreach ($cfs as $cf) {
            $k = strtolower(trim((string)($cf['key'] ?? '')));
            if ($k === 'brand' || $k === 'marca') {
                $v = trim((string)($cf['value'] ?? ''));
                return $v !== '' ? $v : null;
            }
        }
        return null;
    }

    /**
     * Devuelve un array de URLs de imágenes adjuntas al item de Alegra.
     * Soporta varias formas posibles de la respuesta:
     *   images: [{id,name,url}, ...]   ← más común
     *   images: ["https://...", ...]
     *   image:  "https://..."
     */
    public static function extractImageUrls(array $item): array {
        $urls = [];

        $imgs = $item['images'] ?? null;
        if (is_array($imgs)) {
            foreach ($imgs as $img) {
                if (is_string($img) && $img !== '') {
                    $urls[] = $img;
                } elseif (is_array($img)) {
                    $u = $img['url'] ?? $img['secureUrl'] ?? $img['secure_url'] ?? null;
                    if (is_string($u) && $u !== '') $urls[] = $u;
                }
            }
        }

        // Algunos endpoints exponen 'image' singular
        $single = $item['image'] ?? null;
        if (is_string($single) && $single !== '') $urls[] = $single;
        elseif (is_array($single)) {
            $u = $single['url'] ?? null;
            if (is_string($u) && $u !== '') $urls[] = $u;
        }

        // Dedup conservando orden
        return array_values(array_unique($urls));
    }

    /**
     * Huella ESTABLE del set de imágenes de un item, para detectar cambios reales
     * entre syncs.
     *
     * OJO: las URLs de imagen de Alegra son URLs firmadas de CloudFront — el
     * `?Expires=...&Signature=...` cambia en CADA llamada a la API. Por eso NO
     * se puede firmar la URL completa (daría distinto siempre). Usamos:
     *   - el `id` de la imagen en Alegra (estable; cambia al reemplazar la foto), o
     *   - en su defecto, la ruta de la URL sin query (contiene hash+timestamp del
     *     archivo, también estable por versión).
     */
    public static function imagesFingerprint(array $item): string {
        $tokens = [];
        $push = static function ($img) use (&$tokens): void {
            if (is_string($img)) {
                if ($img !== '') $tokens[] = self::urlPath($img);
            } elseif (is_array($img)) {
                if (isset($img['id']) && $img['id'] !== '' && $img['id'] !== null) {
                    $tokens[] = 'id:' . (string) $img['id'];
                } else {
                    $u = $img['url'] ?? $img['secureUrl'] ?? $img['secure_url'] ?? null;
                    if (is_string($u) && $u !== '') $tokens[] = self::urlPath($u);
                }
            }
        };

        $imgs = $item['images'] ?? null;
        if (is_array($imgs)) {
            foreach ($imgs as $img) $push($img);
        }
        $single = $item['image'] ?? null;
        if ($single !== null) $push($single);

        return sha1((string) json_encode($tokens));
    }

    /** host + path de una URL, descartando el query (parámetros volátiles). */
    private static function urlPath(string $url): string {
        $p = parse_url($url);
        if ($p === false) return $url;
        return ($p['host'] ?? '') . ($p['path'] ?? $url);
    }

    /**
     * Descarga los bytes de una imagen. Si la URL es de Alegra, agrega Basic
     * auth; si no, hace un GET plano. Devuelve null si falla.
     */
    public static function downloadImage(string $url): ?string {
        $headers = ['Accept: image/*,*/*;q=0.8'];
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (str_contains($host, 'alegra.com')) {
            $headers[] = 'Authorization: ' . self::authHeader();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400 || $body === '') return null;
        return (string) $body;
    }
}
