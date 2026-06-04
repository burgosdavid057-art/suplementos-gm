<?php
declare(strict_types=1);

// PDO singleton sobre SQLite. La BD vive fuera del web root (../data/store.sqlite).

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $path = dirname(__DIR__) . '/data/store.sqlite';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // FK + WAL para concurrencia razonable.
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    // 30s: corridas largas del sync (mirror de imágenes) pueden chocar con
    // checkpoints del WAL en el filesystem de Plesk; darle margen para esperar
    // en vez de fallar con "database is locked".
    $pdo->exec('PRAGMA busy_timeout = 30000');
    // Sincronización normal (no FULL) — buen balance durabilidad/velocidad en WAL.
    $pdo->exec('PRAGMA synchronous = NORMAL');

    return $pdo;
}

function db_now(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function db_id(): string {
    // CUID-ish: prefijo c + 24 chars alfanuméricos (similar a Prisma cuid).
    return 'c' . bin2hex(random_bytes(12));
}
