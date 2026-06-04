<?php
declare(strict_types=1);

/**
 * Endpoints disparables por cron (Plesk Scheduled Tasks o crontab).
 *
 * Autenticación: ?secret=XXX o header X-Cron-Secret. Tiene que coincidir
 * con CRON_SECRET en .env. Si no está configurado, devuelve 503.
 *
 * También sirve para una corrida HTTP-disparada manual (admin) si hace
 * falta — el lock de AlegraSync evita corridas concurrentes.
 */
class Cron {
    private static function authorize(): void {
        $expected = env('CRON_SECRET');
        if (!$expected || $expected === 'change-me-to-a-random-string-before-deploy') {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo "CRON_SECRET no configurado en .env\n";
            exit;
        }
        // Saneamos el secret: solo alfanumérico (formato esperado de openssl
        // rand -hex). Limitamos longitud para que un atacante no pueda hacer
        // un DoS forzando comparaciones contra strings gigantes.
        $rawGet  = $_GET['secret'] ?? '';
        $rawHdr  = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
        $got     = (string)($rawGet !== '' ? $rawGet : $rawHdr);
        $got     = preg_replace('/[^a-zA-Z0-9]/', '', $got) ?? '';
        $got     = substr($got, 0, 128);
        if (!hash_equals($expected, $got)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "forbidden\n";
            exit;
        }
    }

    /** GET|POST /api/cron/sync-alegra?secret=XXX */
    public static function syncAlegra(): void {
        self::authorize();
        header('Content-Type: text/plain; charset=utf-8');

        if (!Alegra::isConfigured()) {
            http_response_code(500);
            echo "ALEGRA no configurado\n";
            return;
        }

        // Si ya hay un sync corriendo, salimos limpiamente — el cron dispara
        // cada X min y no queremos solapar.
        if (AlegraSync::isRunning()) {
            $started = AlegraSync::lockStartedAt();
            $age = $started ? (time() - $started) : 0;
            echo "skip: ya hay un sync corriendo (hace {$age}s)\n";
            return;
        }

        // Quitar límites de tiempo y memoria para esta request.
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        // Si el cliente cierra la conexión, seguimos.
        ignore_user_abort(true);

        try {
            $r = AlegraSync::run();
            echo sprintf(
                "OK · %d leídos · %d creados · %d actualizados · %d cat-nuevas · %d imágenes · %d errores · %dms\n",
                $r['fetched'], $r['created'], $r['updated'],
                $r['categories_created'], ($r['images_imported'] ?? 0),
                count($r['errors']), $r['duration_ms']
            );
        } catch (Throwable $e) {
            http_response_code(500);
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }

    /** GET /api/cron/status?secret=XXX — para el admin/monitor. */
    public static function status(): void {
        self::authorize();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'running'      => AlegraSync::isRunning(),
            'started_at'   => AlegraSync::lockStartedAt(),
            'last_log'     => AlegraSync::tailLog(20),
            'last_sync_at' => Order::lastSyncAt(),
        ]);
    }
}
