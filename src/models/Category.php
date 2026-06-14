<?php
declare(strict_types=1);

class Category {
    public static function all(): array {
        return db()->query('SELECT * FROM categories ORDER BY "order", name')->fetchAll();
    }

    public static function findBySlug(string $slug): ?array {
        $s = db()->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
        $s->execute([$slug]);
        return $s->fetch() ?: null;
    }

    public static function findById(string $id): ?array {
        $s = db()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public static function withCounts(): array {
        return db()->query("
            SELECT c.*, COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.active = 1
            GROUP BY c.id
            ORDER BY c.\"order\", c.name
        ")->fetchAll();
    }

    public static function create(array $data): string {
        $id = db_id();
        db()->prepare('INSERT INTO categories (id, name, slug, description, image_url, "order") VALUES (?,?,?,?,?,?)')
            ->execute([
                $id, $data['name'], $data['slug'],
                $data['description'] ?: null,
                $data['image_url'] ?: null,
                (int)($data['order'] ?? 100),
            ]);
        return $id;
    }

    public static function update(string $id, array $data): void {
        db()->prepare('UPDATE categories SET name=?, slug=?, description=?, image_url=?, "order"=?, updated_at=? WHERE id=?')
            ->execute([
                $data['name'], $data['slug'],
                $data['description'] ?: null,
                $data['image_url'] ?: null,
                (int)($data['order'] ?? 100),
                db_now(),
                $id,
            ]);
    }

    public static function delete(string $id): bool {
        
        $cnt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) return false;
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        return true;
    }

    public static function slugExists(string $slug, ?string $excludeId = null): bool {
        $sql = 'SELECT 1 FROM categories WHERE slug = ?';
        $args = [$slug];
        if ($excludeId) { $sql .= ' AND id != ?'; $args[] = $excludeId; }
        $stmt = db()->prepare($sql);
        $stmt->execute($args);
        return (bool)$stmt->fetchColumn();
    }
}
