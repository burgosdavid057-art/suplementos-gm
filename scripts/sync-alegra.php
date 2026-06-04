<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (!Alegra::isConfigured()) {
    fwrite(STDERR, "✗ Faltan ALEGRA_EMAIL y/o ALEGRA_API_TOKEN en .env\n");
    fwrite(STDERR, "  Edita ~/.env y vuelve a correr este script.\n");
    exit(1);
}

echo "→ Conectando con Alegra y trayendo todos los items...\n";

$onProgress = function (int $start, int $count) {
    echo "  · página start=$start: $count items\n";
};

try {
    $r = AlegraSync::run($onProgress);
} catch (Throwable $e) {
    fwrite(STDERR, "✗ ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n";
echo "✓ Sync completo en {$r['duration_ms']}ms\n";
echo "  · items leídos:        {$r['fetched']}\n";
echo "  · productos creados:   {$r['created']}\n";
echo "  · productos actualizados: {$r['updated']}\n";
echo "  · saltados (servicios):{$r['skipped']}\n";
echo "  · categorías nuevas:   {$r['categories_created']}\n";
echo "  · imágenes importadas: " . ($r['images_imported'] ?? 0) . "\n";
if (!empty($r['errors'])) {
    echo "\n  ⚠ Errores ({" . count($r['errors']) . "}):\n";
    foreach ($r['errors'] as $err) {
        echo "    - id={$err['id']}: {$err['message']}\n";
    }
}
