<?php
declare(strict_types=1);

/**
 * Sincroniza Alegra → BD local.
 *
 * Reglas (mismas que el Next.js):
 *   - Solo importa items type=product / consumer-good (no servicios).
 *   - Crea categorías locales bajo demanda con slug auto-generado.
 *   - Si el producto ya existe en local (por alegraId), actualiza name/desc/
 *     price/stock/categoryId. Respeta `featured` y `brand` ya editados manualmente.
 *   - Imágenes: Alegra es la fuente de verdad. Si el set de imágenes en Alegra
 *     cambió (detectado por firma sha1), se re-hace el mirror a Cloudinary.
 *     Excepción: productos con images_locked=1 (foto subida/curada a mano desde
 *     /admin) — esos NUNCA se sobrescriben. Si Alegra no trae imágenes, tampoco
 *     se toca lo que haya local.
 *   - Si Alegra dice status=inactive, oculta el producto local. No re-activa
 *     productos que la dueña haya pausado manualmente.
 */
class AlegraSync {
    private const LOCK_FILE = __DIR__ . '/../../data/.alegra-sync.lock';
    private const LOG_FILE  = __DIR__ . '/../../data/alegra-sync.log';
    private const STALE_LOCK_SECONDS = 1800; // 30 min — si el lock es más viejo, lo ignoramos

    /** True si hay un sync corriendo ahora mismo (lock fresco). */
    public static function isRunning(): bool {
        if (!is_file(self::LOCK_FILE)) return false;
        $age = time() - (int) filemtime(self::LOCK_FILE);
        return $age < self::STALE_LOCK_SECONDS;
    }

    /** Hora de inicio del sync en curso (o null). */
    public static function lockStartedAt(): ?int {
        if (!is_file(self::LOCK_FILE)) return null;
        return (int) filemtime(self::LOCK_FILE);
    }

    /** Devuelve las últimas N líneas del log de sync. */
    public static function tailLog(int $lines = 30): string {
        if (!is_file(self::LOG_FILE)) return '';
        $content = (string) file_get_contents(self::LOG_FILE);
        $arr = preg_split('/\r?\n/', $content) ?: [];
        $arr = array_slice($arr, -$lines);
        return implode("\n", $arr);
    }

    /**
     * @return array{
     *   fetched:int, created:int, updated:int, skipped:int,
     *   categories_created:int, images_imported:int,
     *   errors:array<int,array{id:string,message:string}>,
     *   duration_ms:int
     * }
     */
    public static function run(?callable $onProgress = null): array {
        // Adquirir lock — si ya hay uno fresco, abortar.
        @mkdir(dirname(self::LOCK_FILE), 0775, true);
        $lockFp = @fopen(self::LOCK_FILE, 'c');
        if ($lockFp === false || !@flock($lockFp, LOCK_EX | LOCK_NB)) {
            if ($lockFp) @fclose($lockFp);
            throw new RuntimeException('Ya hay un sync en curso. Espera a que termine.');
        }
        @ftruncate($lockFp, 0);
        @fwrite($lockFp, (string) getmypid() . "\n");
        @fflush($lockFp);
        // touch para que filemtime() refleje "ahora"
        @touch(self::LOCK_FILE);

        try {
            return self::doRun($onProgress);
        } finally {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
            @unlink(self::LOCK_FILE);
        }
    }

    private static function log(string $msg): void {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
        @file_put_contents(self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }

    private static function doRun(?callable $onProgress): array {
        $t0 = microtime(true);
        $result = [
            'fetched' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
            'categories_created' => 0, 'images_imported' => 0,
            'errors' => [], 'duration_ms' => 0,
        ];

        self::log('=== Sync iniciado ===');

        // Wrapper del progress que loguea cada página y propaga al callback original.
        $progressLog = function (int $start, int $count) use ($onProgress) {
            self::log("Página start=$start: $count items recibidos");
            if ($onProgress) $onProgress($start, $count);
        };

        try {
            $items = Alegra::fetchAllItems($progressLog);
        } catch (Throwable $e) {
            self::log('ERROR fetchAllItems: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con Alegra: ' . $e->getMessage());
        }
        $result['fetched'] = count($items);
        self::log("Total items leídos: {$result['fetched']}");

        $pdo = db();
        $categoryCache = [];
        $processed = 0;

        foreach ($items as $item) {
            $alegraId = (string) ($item['id'] ?? '');
            if ($alegraId === '') continue;

            // Checkpoint WAL periódico: en corridas largas (mirror de imágenes)
            // el WAL puede crecer y disparar contención de checkpoint. Lo vaciamos
            // cada 40 items con un checkpoint pasivo (no bloquea lectores).
            if ($processed > 0 && $processed % 40 === 0) {
                try { $pdo->exec('PRAGMA wal_checkpoint(PASSIVE)'); } catch (Throwable $e) {}
            }
            $processed++;

            try {
                // Saltar servicios — solo productos físicos.
                $type = $item['type'] ?? 'product';
                if ($type !== 'product' && $type !== 'consumer-good') {
                    $result['skipped']++;
                    continue;
                }

                $catName    = $item['category']['name'] ?? null;
                $categoryId = self::resolveCategoryId($catName, $categoryCache, $result);

                $price       = Alegra::pickPrice($item);
                $stock       = max(0, (int) round((float)($item['inventory']['availableQuantity'] ?? 0)));
                $brand       = Alegra::extractBrand($item);
                $description = trim((string)($item['description'] ?? '')) ?: ($item['name'] ?? '');
                $isActiveAle = (($item['status'] ?? 'active') === 'active');
                $alegraImgs  = Alegra::extractImageUrls($item);

                // Buscar por alegraId (incluyendo images/lock/firma para decidir mirror)
                $stmt = $pdo->prepare('SELECT id, brand, active, images, images_locked, alegra_images_sig FROM products WHERE alegra_id = ? LIMIT 1');
                $stmt->execute([$alegraId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Solo campos del ERP. Featured/brand-manual se respetan.
                    $newBrand  = $existing['brand'] ?: $brand;
                    $newActive = $isActiveAle ? (int)$existing['active'] : 0;

                    // ── Imágenes ──────────────────────────────────────
                    // Refrescamos desde Alegra cuando:
                    //   - el producto NO está bloqueado a mano (images_locked=0), y
                    //   - Alegra trae imágenes, y
                    //   - el set de imágenes cambió respecto al último mirror.
                    // La firma evita re-subir a Cloudinary en cada corrida del cron.
                    $locked    = ((int)($existing['images_locked'] ?? 0)) === 1;
                    $sig       = Alegra::imagesFingerprint($item);
                    $storedSig = $existing['alegra_images_sig'] ?? null;
                    $needMirror = !$locked && !empty($alegraImgs) && $sig !== $storedSig;

                    if ($needMirror) {
                        $mirrored = self::mirrorImages($alegraImgs, $existing['id']);
                        if (!empty($mirrored)) {
                            $stmt2 = $pdo->prepare('UPDATE products SET
                                name=?, description=?, price=?, stock=?, brand=?,
                                category_id=?, active=?, images=?, alegra_images_sig=?,
                                synced_at=?, updated_at=?
                                WHERE id=?');
                            self::runStmt($stmt2, [
                                $item['name'], $description, $price, $stock, $newBrand,
                                $categoryId, $newActive, json_encode(array_values($mirrored)), $sig,
                                db_now(), db_now(), $existing['id'],
                            ]);
                            $result['images_imported'] += count($mirrored);
                            $result['updated']++;
                            continue;
                        }
                        // Si el mirror falló (descarga/upload), NO tocamos imágenes
                        // ni firma: caemos al UPDATE normal y reintentamos luego.
                    }

                    $stmt = $pdo->prepare('UPDATE products SET
                        name = ?, description = ?, price = ?, stock = ?, brand = ?,
                        category_id = ?, active = ?, synced_at = ?, updated_at = ?
                        WHERE id = ?');
                    self::runStmt($stmt, [
                        $item['name'], $description, $price, $stock, $newBrand,
                        $categoryId, $newActive, db_now(), db_now(),
                        $existing['id'],
                    ]);
                    $result['updated']++;
                } else {
                    $newId = db_id();
                    $slug  = self::ensureUniqueSlug((string) $item['name'], $alegraId);

                    $imagesJson = '[]';
                    $sigToStore = null;
                    if (!empty($alegraImgs)) {
                        $mirrored = self::mirrorImages($alegraImgs, $newId);
                        if (!empty($mirrored)) {
                            $imagesJson = json_encode(array_values($mirrored));
                            $sigToStore = Alegra::imagesFingerprint($item);
                            $result['images_imported'] += count($mirrored);
                        }
                    }

                    $stmt = $pdo->prepare('INSERT INTO products
                        (id, name, slug, description, price, stock, images, active, featured,
                         brand, alegra_id, category_id, synced_at, alegra_images_sig)
                        VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?,?)');
                    self::runStmt($stmt, [
                        $newId, $item['name'], $slug, $description, $price, $stock,
                        $imagesJson, $isActiveAle ? 1 : 0,
                        $brand, $alegraId, $categoryId, db_now(), $sigToStore,
                    ]);
                    $result['created']++;
                }
            } catch (Throwable $e) {
                $result['errors'][] = ['id' => $alegraId, 'message' => $e->getMessage()];
            }
        }

        $result['duration_ms'] = (int) round((microtime(true) - $t0) * 1000);
        self::log(sprintf(
            '=== Sync OK · %d leídos · %d creados · %d actualizados · %d cat-nuevas · %d imágenes · %d errores · %dms ===',
            $result['fetched'], $result['created'], $result['updated'],
            $result['categories_created'], $result['images_imported'],
            count($result['errors']), $result['duration_ms']
        ));
        return $result;
    }

    /**
     * Descarga imágenes desde Alegra y las espeja a Cloudinary.
     * Retorna el array de URLs definitivas en Cloudinary (en el mismo orden).
     * Si Cloudinary no está configurado o falla todo, retorna [] y la dueña
     * tendrá que subir las fotos manualmente desde /admin.
     */
    private static function mirrorImages(array $alegraUrls, string $productId): array {
        if (!Cloudinary::isFullyConfigured()) return [];

        $out = [];
        $idx = 0;
        foreach ($alegraUrls as $url) {
            try {
                $bytes = Alegra::downloadImage($url);
                if ($bytes === null || $bytes === '') { $idx++; continue; }

                $publicId = "p-{$productId}-{$idx}";
                $mime     = Cloudinary::guessMime($bytes);
                $secure   = Cloudinary::uploadBytes($bytes, $publicId, $mime, 'alegra-sync');
                if ($secure) $out[] = $secure;
            } catch (Throwable $e) {
                // No interrumpir el sync por una imagen fallida.
            }
            $idx++;
            // Pequeña pausa para no saturar Cloudinary.
            usleep(120_000);
        }
        return $out;
    }

    /**
     * Ejecuta un statement de escritura con reintentos ante "database is locked".
     * El busy_timeout del PDO ya espera dentro de cada execute(); esto añade
     * reintentos con backoff para los casos de contención de WAL/checkpoint que
     * devuelven BUSY inmediato sin invocar el busy handler.
     */
    private static function runStmt(PDOStatement $stmt, array $params, int $tries = 5): void {
        for ($i = 0; ; $i++) {
            try {
                $stmt->execute($params);
                return;
            } catch (PDOException $e) {
                $msg = strtolower($e->getMessage());
                $isLock = str_contains($msg, 'database is locked')
                       || str_contains($msg, 'database is busy')
                       || str_contains($msg, 'database table is locked');
                if (!$isLock || $i >= $tries - 1) throw $e;
                // backoff: 200ms, 400, 800, 1600 ms
                usleep(200_000 * (1 << $i));
            }
        }
    }

    private static function resolveCategoryId(?string $alegraName, array &$cache, array &$result): string {
        $name = trim((string)($alegraName ?? '')) ?: 'Sin categoría';

        if (isset($cache[$name])) return $cache[$name];

        $slug = slug($name) ?: 'sin-categoria';

        $stmt = db()->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        $stmt->execute([$name, $slug]);
        $existing = $stmt->fetch();
        if ($existing) {
            $cache[$name] = $existing['id'];
            return $existing['id'];
        }

        // Crear
        $id = db_id();
        $stmt = db()->prepare('INSERT INTO categories (id, name, slug, description, "order")
                               VALUES (?,?,?,?,100)');
        $stmt->execute([$id, $name, $slug, 'Importada desde Alegra']);
        $result['categories_created']++;
        $cache[$name] = $id;
        return $id;
    }

    /**
     * Asegura que el slug sea único. Si choca con OTRO producto, agrega sufijo numérico.
     */
    private static function ensureUniqueSlug(string $name, string $alegraId): string {
        $root = slug($name) ?: ('producto-' . $alegraId);
        $slug = $root;
        $stmt = db()->prepare('SELECT alegra_id FROM products WHERE slug = ? LIMIT 1');
        $n = 0;
        while (true) {
            $stmt->execute([$slug]);
            $found = $stmt->fetch();
            if (!$found || $found['alegra_id'] === $alegraId) return $slug;
            $n++;
            $slug = "$root-$n";
            if ($n > 50) return "$root-$alegraId";
        }
    }
}
