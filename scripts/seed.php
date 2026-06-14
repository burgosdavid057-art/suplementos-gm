<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();

echo "→ Sembrando admin + flagship Body Builder 5000...\n";
echo "  (el resto de productos viene de Alegra: corre 'php scripts/sync-alegra.php')\n";

$pdo->beginTransaction();

$adminEmail = env('ADMIN_EMAIL', 'suplementosgm2@gmail.com');
$adminPass  = env('ADMIN_PASSWORD', 'ChangeMe123!');

$pdo->prepare('INSERT OR REPLACE INTO users (id, email, password, name, role) VALUES (?,?,?,?,?)')
    ->execute([db_id(), $adminEmail, password_hash($adminPass, PASSWORD_BCRYPT), 'Admin', 'ADMIN']);
echo "  · Admin: $adminEmail\n";

$catId = null;
$stmt  = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
$stmt->execute(['marca-propia']);
$row = $stmt->fetch();
if ($row) {
    $catId = $row['id'];
} else {
    $catId = db_id();
    $pdo->prepare('INSERT INTO categories (id, name, slug, description, "order") VALUES (?,?,?,?,?)')
        ->execute([$catId, 'Marca propia', 'marca-propia', 'Productos exclusivos de Suplementos GM.', 0]);
    echo "  · Categoría: Marca propia\n";
}

$stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
$stmt->execute(['body-builder-5000']);
if (!$stmt->fetch()) {
    $description = "Suplemento alto en grasa y proteína. Marca propia de Suplementos GM, "
        . "diseñado para mejorar la condición corporal sin poner en riesgo la salud del caballo.\n\n"
        . "Presentación: bolsa de 11 libras.\n"
        . "Dosis recomendada: 2 onzas en la mañana, 2 en la tarde, mezcladas con el concentrado.\n"
        . "Una bolsa rinde aproximadamente 1.5 meses.";

    $pdo->prepare('INSERT INTO products
            (id, name, slug, description, price, stock, images, active, featured, brand, category_id)
            VALUES (?,?,?,?,?,?,?,1,1,?,?)')
        ->execute([
            db_id(),
            'Body Builder 5000',
            'body-builder-5000',
            $description,
            285000,
            24,
            json_encode(['/assets/bodybuilder5000.jpg']),
            'Suplementos GM',
            $catId,
        ]);
    echo "  · Producto: Body Builder 5000 (marca propia)\n";
}

$pdo->commit();
echo "✓ Seed completo\n";
echo "\n";
echo "Siguientes pasos:\n";
echo "  1. Edita ~/.env y completa ALEGRA_EMAIL y ALEGRA_API_TOKEN\n";
echo "  2. Corre:  /opt/plesk/php/8.4/bin/php scripts/sync-alegra.php\n";
