<?php
declare(strict_types=1);
/**
 * Migración aditiva: añade campos de facturación electrónica a `orders`.
 *
 * - customer_type:   natural | juridica (default 'natural')
 * - customer_doc_type: CC | CE | NIT | PP | TI (default 'CC')
 * - billing_*:       campos separados de la dirección de envío
 *
 * Es seguro correr múltiples veces — chequea si las columnas ya existen.
 */
require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();

function column_exists(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->prepare("PRAGMA table_info($table)");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (strcasecmp($row['name'], $col) === 0) return true;
    }
    return false;
}

$additions = [
    "customer_type      TEXT DEFAULT 'natural'",   // natural | juridica
    "customer_doc_type  TEXT DEFAULT 'CC'",         // CC | CE | NIT | PP | TI
    "billing_name       TEXT",                       // Nombre o razón social
    "billing_address    TEXT",
    "billing_city       TEXT",
    "billing_state      TEXT",
];

foreach ($additions as $colDef) {
    $colName = strtok($colDef, ' ');
    if (column_exists($pdo, 'orders', $colName)) {
        echo "  · ya existe: $colName (skip)\n";
        continue;
    }
    $pdo->exec("ALTER TABLE orders ADD COLUMN $colDef");
    echo "  ✓ añadido: $colName\n";
}

echo "\n✓ Migración de facturación completa.\n";
