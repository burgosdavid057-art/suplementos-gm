<?php
declare(strict_types=1);

class Product {
    public static function decode(array $row): array {
        $row['images']   = json_decode($row['images'] ?? '[]', true) ?: [];
        $row['active']   = (bool)$row['active'];
        $row['featured'] = (bool)$row['featured'];
        if (isset($row['images_locked'])) $row['images_locked'] = (bool)$row['images_locked'];
        $row['price']    = (int)$row['price'];
        $row['stock']    = (int)$row['stock'];
        return $row;
    }

    public static function findBySlug(string $slug): ?array {
        $s = db()->prepare('
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.slug = ? AND p.active = 1
            LIMIT 1
        ');
        $s->execute([$slug]);
        $row = $s->fetch();
        return $row ? self::decode($row) : null;
    }

    public static function findById(string $id): ?array {
        $s = db()->prepare('
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? LIMIT 1
        ');
        $s->execute([$id]);
        $row = $s->fetch();
        return $row ? self::decode($row) : null;
    }

    /**
     * Listado público con filtros opcionales.
     */
    public static function list(array $opts = []): array {
        $where = ['p.active = 1'];
        $args  = [];

        if (!empty($opts['category'])) {
            $where[] = 'c.slug = ?';
            $args[] = $opts['category'];
        }
        if (!empty($opts['brand'])) {
            $where[] = 'p.brand = ?';
            $args[] = $opts['brand'];
        }
        if (!empty($opts['q'])) {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $q = '%' . $opts['q'] . '%';
            $args[] = $q;
            $args[] = $q;
        }
        if (!empty($opts['min'])) {
            $where[] = 'p.price >= ?';
            $args[] = (int)$opts['min'];
        }
        if (!empty($opts['max'])) {
            $where[] = 'p.price <= ?';
            $args[] = (int)$opts['max'];
        }

        $page    = max(1, (int)($opts['page']    ?? 1));
        $perPage = max(1, (int)($opts['perPage'] ?? 24));
        $offset  = ($page - 1) * $perPage;
        $whereSql = implode(' AND ', $where);

        $stmt = db()->prepare("SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE $whereSql");
        $stmt->execute($args);
        $total = (int)$stmt->fetchColumn();

        $stmt = db()->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p JOIN categories c ON c.id = p.category_id
            WHERE $whereSql
            ORDER BY
              (p.stock > 0) DESC,   -- en stock primero, agotados al final
              p.featured DESC,       -- destacados antes que normales
              p.created_at DESC      -- más nuevos primero
            LIMIT ? OFFSET ?
        ");
        $i = 1;
        foreach ($args as $a) $stmt->bindValue($i++, $a);
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_map([self::class, 'decode'], $stmt->fetchAll());

        return [
            'items'   => $rows,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'pages'   => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Listado completo del admin (ignora active=1, permite filtrar por categoría/búsqueda).
     */
    public static function adminList(array $opts = []): array {
        $where = ['1=1'];
        $args  = [];
        if (!empty($opts['q'])) {
            $where[] = 'p.name LIKE ?';
            $args[] = '%' . $opts['q'] . '%';
        }
        if (!empty($opts['category'])) {
            $where[] = 'c.slug = ?';
            $args[] = $opts['category'];
        }
        $whereSql = implode(' AND ', $where);
        $stmt = db()->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p JOIN categories c ON c.id = p.category_id
            WHERE $whereSql
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($args);
        return array_map([self::class, 'decode'], $stmt->fetchAll());
    }

    public static function featured(int $limit = 4): array {
        $s = db()->prepare('
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p JOIN categories c ON c.id = p.category_id
            WHERE p.active = 1 AND p.featured = 1
            ORDER BY p.created_at DESC
            LIMIT ?
        ');
        $s->bindValue(1, $limit, PDO::PARAM_INT);
        $s->execute();
        return array_map([self::class, 'decode'], $s->fetchAll());
    }

    public static function brands(): array {
        return db()->query("
            SELECT DISTINCT brand FROM products
            WHERE active = 1 AND brand IS NOT NULL AND brand != ''
            ORDER BY brand
        ")->fetchAll(PDO::FETCH_COLUMN);
    }

    // ─── CRUD ────────────────────────────────────────────

    public static function create(array $data): string {
        $id = db_id();
        $stmt = db()->prepare('INSERT INTO products
            (id, name, slug, description, price, stock, images, active, featured, brand, category_id, images_locked)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $id, $data['name'], $data['slug'], $data['description'],
            (int)$data['price'], (int)$data['stock'],
            json_encode($data['images'] ?? []),
            !empty($data['active']) ? 1 : 0,
            !empty($data['featured']) ? 1 : 0,
            $data['brand'] ?: null,
            $data['category_id'],
            !empty($data['images_locked']) ? 1 : 0,
        ]);
        return $id;
    }

    public static function update(string $id, array $data): void {
        $stmt = db()->prepare('UPDATE products SET
            name=?, slug=?, description=?, price=?, stock=?, images=?,
            active=?, featured=?, brand=?, category_id=?, images_locked=?, updated_at=?
            WHERE id=?');
        $stmt->execute([
            $data['name'], $data['slug'], $data['description'],
            (int)$data['price'], (int)$data['stock'],
            json_encode($data['images'] ?? []),
            !empty($data['active']) ? 1 : 0,
            !empty($data['featured']) ? 1 : 0,
            $data['brand'] ?: null,
            $data['category_id'],
            !empty($data['images_locked']) ? 1 : 0,
            db_now(),
            $id,
        ]);
    }

    public static function toggleActive(string $id): void {
        $stmt = db()->prepare('UPDATE products SET active = 1 - active, updated_at = ? WHERE id = ?');
        $stmt->execute([db_now(), $id]);
    }

    public static function delete(string $id): void {
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    }

    public static function slugExists(string $slug, ?string $excludeId = null): bool {
        $sql = 'SELECT 1 FROM products WHERE slug = ?';
        $args = [$slug];
        if ($excludeId) { $sql .= ' AND id != ?'; $args[] = $excludeId; }
        $stmt = db()->prepare($sql);
        $stmt->execute($args);
        return (bool)$stmt->fetchColumn();
    }
}
