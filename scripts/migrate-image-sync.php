<?php
declare(strict_types=1);

/**
 * Migración aditiva: habilita la re-sincronización de imágenes desde Alegra.
 *
 *   - images_locked      (INTEGER 0/1)  → si 1, el sync NO toca las imágenes.
 *   - alegra_images_sig  (TEXT)         → firma del último set de imágenes
 *                                          mirror-eado desde Alegra (evita
 *                                          re-subir a Cloudinary en cada cron).
 *
 * Backfill: bloquea (images_locked=1) los productos cuyas imágenes NO provienen
 * del mirror de Alegra (carpeta 'alegra-sync'), es decir, fotos subidas a mano
 * o assets locales — para no sobrescribirlas en el primer sync.
 *
 * Idempotente: se puede correr varias veces sin daño.
 */

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();

// ─── Columnas existentes ────────────────────────────────
$cols = [];
foreach ($pdo->query('PRAGMA table_info(products)') as $row) {
    $cols[$row['name']] = true;
}

if (!isset($cols['images_locked'])) {
    echo "→ Agregando columna images_locked...\n";
    $pdo->exec('ALTER TABLE products ADD COLUMN images_locked INTEGER NOT NULL DEFAULT 0');
    echo "✓ images_locked agregada\n";
} else {
    echo "· images_locked ya existe\n";
}

if (!isset($cols['alegra_images_sig'])) {
    echo "→ Agregando columna alegra_images_sig...\n";
    $pdo->exec('ALTER TABLE products ADD COLUMN alegra_images_sig TEXT');
    echo "✓ alegra_images_sig agregada\n";
} else {
    echo "· alegra_images_sig ya existe\n";
}

// ─── Backfill: bloquear fotos NO provenientes de Alegra ──
// Las imágenes mirror-eadas viven en la carpeta 'alegra-sync' de Cloudinary.
// Cualquier otra cosa (foto manual, asset local) se considera manual → bloquear.
$stmt = $pdo->prepare("
    UPDATE products
    SET images_locked = 1
    WHERE images != '[]'
      AND images NOT LIKE '%alegra-sync%'
      AND images_locked = 0
");
$stmt->execute();
$locked = $stmt->rowCount();
echo "✓ Productos bloqueados (foto manual/local protegida): $locked\n";

// ─── Resumen ─────────────────────────────────────────────
$total   = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$lockedN = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE images_locked = 1')->fetchColumn();
$freeImg = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE images_locked = 0 AND images != '[]'")->fetchColumn();
$empty   = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE images = '[]'")->fetchColumn();

echo "\n── Resumen ──\n";
echo "  Total productos:                  $total\n";
echo "  Bloqueados (no sync imágenes):    $lockedN\n";
echo "  Con foto, refrescables de Alegra: $freeImg\n";
echo "  Sin foto (se llenan si Alegra tiene): $empty\n";
echo "\n✓ Migración completa. El próximo sync refrescará imágenes desde Alegra.\n";
