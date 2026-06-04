<?php
declare(strict_types=1);

/**
 * Cloudinary helpers.
 *
 * Dos modalidades de uso:
 *   1. Browser (unsigned upload widget): usa cloud_name + upload_preset.
 *      Lo usa el admin para subir imágenes manualmente.
 *   2. Server-side (signed upload): usa cloud_name + api_key + api_secret.
 *      Lo usa el sync de Alegra para mirroring de imágenes del ERP.
 */
class Cloudinary {
    public static function isConfigured(): bool {
        return !empty(env('CLOUDINARY_CLOUD_NAME')) && !empty(env('CLOUDINARY_UPLOAD_PRESET'));
    }

    /** True si tenemos las llaves para hacer upload firmado server-side. */
    public static function isFullyConfigured(): bool {
        return !empty(env('CLOUDINARY_CLOUD_NAME'))
            && !empty(env('CLOUDINARY_API_KEY'))
            && !empty(env('CLOUDINARY_API_SECRET'));
    }

    public static function cloudName(): ?string {
        return env('CLOUDINARY_CLOUD_NAME');
    }

    public static function uploadPreset(): ?string {
        return env('CLOUDINARY_UPLOAD_PRESET');
    }

    public static function apiKey(): ?string {
        return env('CLOUDINARY_API_KEY');
    }

    public static function apiSecret(): ?string {
        return env('CLOUDINARY_API_SECRET');
    }

    /**
     * Sube una imagen a Cloudinary firmando la petición con el api_secret.
     *
     * $source puede ser:
     *   - URL pública: "https://..."           (Cloudinary la descarga)
     *   - Data URI:    "data:image/...;base64,..."
     *
     * Devuelve secure_url o null si falla.
     */
    public static function uploadSigned(string $source, string $publicId, string $folder = 'alegra-sync'): ?string {
        if (!self::isFullyConfigured()) return null;

        $cloud     = (string) self::cloudName();
        $apiKey    = (string) self::apiKey();
        $apiSecret = (string) self::apiSecret();
        $timestamp = (string) time();

        // Cloudinary firma con los parámetros que NO sean api_key, file,
        // signature, resource_type ni cloud_name, ordenados alfabéticamente.
        $params = [
            'folder'           => $folder,
            'overwrite'        => 'true',
            'public_id'        => $publicId,
            'timestamp'        => $timestamp,
            'unique_filename'  => 'false',
        ];
        ksort($params);
        $signStr = '';
        foreach ($params as $k => $v) {
            if ($signStr !== '') $signStr .= '&';
            $signStr .= "$k=$v";
        }
        $signature = sha1($signStr . $apiSecret);

        $post = $params + [
            'api_key'   => $apiKey,
            'signature' => $signature,
            'file'      => $source,
        ];

        $ch = curl_init("https://api.cloudinary.com/v1_1/$cloud/image/upload");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) return null;

        $data = json_decode((string) $body, true);
        if (!is_array($data)) return null;
        $url = $data['secure_url'] ?? $data['url'] ?? null;
        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Sube bytes binarios codificándolos como data URI base64.
     * Útil cuando descargamos la imagen con auth (Alegra) y queremos
     * pasarla a Cloudinary sin re-exponer el endpoint protegido.
     */
    public static function uploadBytes(string $bytes, string $publicId, string $mime = 'image/jpeg', string $folder = 'alegra-sync'): ?string {
        if ($bytes === '') return null;
        $b64 = 'data:' . $mime . ';base64,' . base64_encode($bytes);
        return self::uploadSigned($b64, $publicId, $folder);
    }

    /** Heurística simple para mime-type a partir de los magic bytes. */
    public static function guessMime(string $bytes): string {
        if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0)  return 'image/jpeg';
        if (strncmp($bytes, "\x89PNG", 4) === 0)        return 'image/png';
        if (strncmp($bytes, "GIF8", 4) === 0)           return 'image/gif';
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') return 'image/webp';
        return 'image/jpeg';
    }
}
