<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS email_log (
    id          TEXT PRIMARY KEY,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    kind        TEXT NOT NULL,
    to_address  TEXT NOT NULL,
    from_address TEXT NOT NULL,
    subject     TEXT NOT NULL,
    status      TEXT NOT NULL DEFAULT 'pending'
                CHECK(status IN ('pending','sent','failed')),
    provider_id TEXT,
    error       TEXT,
    ref_id      TEXT,
    ref_type    TEXT
);
SQL);
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_log_created   ON email_log(created_at)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_log_ref       ON email_log(ref_id, ref_type)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_log_status    ON email_log(status)");

echo "✓ Tabla email_log creada (o ya existía)\n";
