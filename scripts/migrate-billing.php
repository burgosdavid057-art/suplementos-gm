<?php
declare(strict_types=1);

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
    "customer_type      TEXT DEFAULT 'natural'",   
    "customer_doc_type  TEXT DEFAULT 'CC'",         
    "billing_name       TEXT",                       
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
