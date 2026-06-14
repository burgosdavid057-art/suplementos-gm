<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();

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
