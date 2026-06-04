<?php
declare(strict_types=1);

/**
 * Backfill ONE-TIME de la firma estable de imágenes.
 *
 * Tras cambiar la firma de "URL completa (volátil)" a "id/ruta estable", las
 * firmas guardadas quedaron obsoletas. Como las imágenes locales YA están al día
 * (se acaba de correr un sync que las espejó), aquí solo recalculamos y guardamos
 * la firma estable — SIN re-descargar/re-subir nada. Así el próximo sync ve que
 * la firma coincide y no re-espeja en vano.
 *
 * Solo toca productos: no bloqueados, con imagen local presente y con imágenes en
 * Alegra. Idempotente.
 */

require_once __DIR__ . '/../src/bootstrap.php';

if (!Alegra::isConfigured()) {
    fwrite(STDERR, "Alegra no configurado\n");
    exit(1);
}

echo "Trayendo items de Alegra...\n";
$items = Alegra::fetchAllItems();
echo "Items: " . count($items) . "\n";

$pdo = db();
$sel = $pdo->prepare('SELECT id, images_locked, images FROM products WHERE alegra_id = ? LIMIT 1');
$upd = $pdo->prepare('UPDATE products SET alegra_images_sig = ? WHERE id = ?');

$updated = 0; $skipLocked = 0; $skipNoImg = 0; $skipNoLocal = 0;

foreach ($items as $item) {
    $type = $item['type'] ?? 'product';
    if ($type !== 'product' && $type !== 'consumer-good') continue;
    $aid = (string)($item['id'] ?? '');
    if ($aid === '') continue;

    $sel->execute([$aid]);
    $row = $sel->fetch();
    if (!$row) { $skipNoLocal++; continue; }
    if ((int)$row['images_locked'] === 1) { $skipLocked++; continue; }

    $alegraUrls = Alegra::extractImageUrls($item);
    $localImgs  = json_decode((string)($row['images'] ?? '[]'), true);
    if (!is_array($localImgs)) $localImgs = [];

    // Solo si Alegra tiene imágenes Y el producto local ya tiene su mirror.
    if (empty($alegraUrls) || empty($localImgs)) { $skipNoImg++; continue; }

    $upd->execute([Alegra::imagesFingerprint($item), $row['id']]);
    $updated++;
}

echo "\n✓ Firmas estables guardadas: $updated\n";
echo "  · bloqueados (sin tocar):     $skipLocked\n";
echo "  · sin foto local/Alegra:      $skipNoImg\n";
echo "  · sin producto local:         $skipNoLocal\n";
echo "\nEl próximo sync ya no re-espejará salvo cambios reales en Alegra.\n";
