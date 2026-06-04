<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();

echo "→ Creando schema...\n";

$pdo->exec(<<<'SQL'
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id          TEXT PRIMARY KEY,
    email       TEXT NOT NULL UNIQUE,
    password    TEXT NOT NULL,
    name        TEXT,
    role        TEXT NOT NULL DEFAULT 'ADMIN',
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE categories (
    id          TEXT PRIMARY KEY,
    name        TEXT NOT NULL UNIQUE,
    slug        TEXT NOT NULL UNIQUE,
    description TEXT,
    image_url   TEXT,
    "order"     INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE products (
    id          TEXT PRIMARY KEY,
    name        TEXT NOT NULL,
    slug        TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price       INTEGER NOT NULL,
    stock       INTEGER NOT NULL DEFAULT 0,
    images      TEXT NOT NULL DEFAULT '[]',  -- JSON array de URLs
    active      INTEGER NOT NULL DEFAULT 1,
    featured    INTEGER NOT NULL DEFAULT 0,
    brand       TEXT,
    alegra_id   TEXT UNIQUE,
    category_id TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    synced_at   TEXT,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_active   ON products(active);
CREATE INDEX idx_products_brand    ON products(brand);

CREATE TABLE orders (
    id                TEXT PRIMARY KEY,
    order_number      TEXT NOT NULL UNIQUE,
    customer_name     TEXT NOT NULL,
    customer_email    TEXT NOT NULL,
    customer_phone    TEXT NOT NULL,
    customer_doc_id   TEXT,
    shipping_method   TEXT NOT NULL DEFAULT 'interrapidisimo'
                      CHECK(shipping_method IN ('interrapidisimo','bus','oriente_cercano')),
    shipping_terminal TEXT,                            -- 'norte' | 'sur' (solo si method=bus)
    shipping_address  TEXT NOT NULL,
    shipping_city     TEXT NOT NULL,
    shipping_state    TEXT NOT NULL,
    shipping_notes    TEXT,
    subtotal          INTEGER NOT NULL,
    shipping_cost     INTEGER NOT NULL DEFAULT 0,
    total             INTEGER NOT NULL,
    status            TEXT NOT NULL DEFAULT 'PENDING'
                      CHECK(status IN ('PENDING','PAID','FAILED','SHIPPED','DELIVERED','CANCELLED')),
    payment_method    TEXT,
    payment_ref       TEXT,
    payment_tx_id     TEXT,
    tracking_number   TEXT,
    tracking_carrier  TEXT,
    admin_notes       TEXT,
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now')),
    paid_at           TEXT,
    shipped_at        TEXT,
    delivered_at      TEXT
);
CREATE INDEX idx_orders_status      ON orders(status);
CREATE INDEX idx_orders_created_at  ON orders(created_at);

CREATE TABLE order_items (
    id            TEXT PRIMARY KEY,
    order_id      TEXT NOT NULL,
    product_id    TEXT NOT NULL,
    product_name  TEXT NOT NULL,
    product_price INTEGER NOT NULL,
    quantity      INTEGER NOT NULL,
    subtotal      INTEGER NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);
CREATE INDEX idx_order_items_order ON order_items(order_id);
SQL);

echo "✓ Schema creado\n";
