<?php
declare(strict_types=1);

/**
 * Auditoría de imágenes: compara la foto que tiene cada producto en la BD local
 * contra la que entrega Alegra AHORA, y reporta desajustes.
 *
 * NO escribe nada (solo lectura). Uso:
 *   php scripts/audit-images.php          → resumen + lista de desajustes
 *   php scripts/audit-images.php --csv    → salida CSV para revisar en detalle
 *
 * Categorías:
 *   OK            → la firma local coincide con la de Alegra (foto al día)
 *   DESACTUALIZADO→ Alegra tiene imágenes pero la firma local no coincide
 *                   (el próximo sync lo arregla; si persiste, ver --reset abajo)
 *   BLOQUEADO     → images_locked=1: se conserva la foto manual a propósito
 *   SIN_FOTO_ALEGRA→ Alegra no tiene imágenes para ese producto
 *   FALTA_EN_BD   → el item de Alegra no existe como producto local
 */

require_once __DIR__ . '/../src/bootstrap.php';

if (!Alegra::isConfigured()) {
    fwrite(STDERR, "Alegra no configurado\n");
    exit(1);
}

// Misma firma ESTABLE que usa AlegraSync (id de imagen / ruta sin query firmada).
function img_sig(array $item): string {
    return Alegra::imagesFingerprint($item);
}

$csv = in_array('--csv', $argv, true);

fwrite(STDERR, "Trayendo items de Alegra...\n");
$items = Alegra::fetchAllItems();
fwrite(STDERR, "Items: " . count($items) . "\n\n");

$pdo = db();
$sel = $pdo->prepare('SELECT id, slug, images_locked, alegra_images_sig, images FROM products WHERE alegra_id = ? LIMIT 1');

$counts = ['OK'=>0,'DESACTUALIZADO'=>0,'BLOQUEADO'=>0,'SIN_FOTO_ALEGRA'=>0,'FALTA_EN_BD'=>0];
$problemas = [];

if ($csv) echo "estado,alegra_id,slug,nombre,alegra_imgs,sig_local,sig_alegra\n";

foreach ($items as $item) {
    $type = $item['type'] ?? 'product';
    if ($type !== 'product' && $type !== 'consumer-good') continue;

    $aid  = (string)($item['id'] ?? '');
    if ($aid === '') continue;
    $name = (string)($item['name'] ?? '');
    $alegraUrls = Alegra::extractImageUrls($item);
    $sigAlegra  = img_sig($item);

    $sel->execute([$aid]);
    $row = $sel->fetch();

    if (!$row) {
        $estado = 'FALTA_EN_BD';
    } elseif ((int)$row['images_locked'] === 1) {
        $estado = 'BLOQUEADO';
    } elseif (empty($alegraUrls)) {
        $estado = 'SIN_FOTO_ALEGRA';
    } elseif (($row['alegra_images_sig'] ?? null) === $sigAlegra) {
        $estado = 'OK';
    } else {
        $estado = 'DESACTUALIZADO';
    }
    $counts[$estado]++;

    if ($csv) {
        printf("%s,%s,%s,\"%s\",%d,%s,%s\n",
            $estado, $aid, $row['slug'] ?? '-', str_replace('"','',$name),
            count($alegraUrls), $row['alegra_images_sig'] ?? '-', $sigAlegra);
    } elseif (in_array($estado, ['DESACTUALIZADO','BLOQUEADO','FALTA_EN_BD'], true)) {
        $problemas[] = sprintf("  [%s] id=%s %s (%s) · Alegra=%d foto(s)",
            $estado, $aid, $row['slug'] ?? '-', mb_substr($name,0,30), count($alegraUrls));
    }
}

if (!$csv) {
    echo "── Resumen auditoría de imágenes ──\n";
    foreach ($counts as $k=>$v) printf("  %-16s %d\n", $k, $v);
    if ($problemas) {
        echo "\n── A revisar ──\n" . implode("\n", $problemas) . "\n";
    } else {
        echo "\n✓ Todos los productos con foto en Alegra están al día.\n";
    }
}
